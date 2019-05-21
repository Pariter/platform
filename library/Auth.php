<?php

namespace Pariter\Library;

use Dugwood\Core\Cache;
use Dugwood\Core\Server;
use Phalcon\DI;
use Phalcon\DI\Injectable;
use Phalcon\Exception;
use Pariter\Models\User;

class Auth extends Injectable {

	static public function getAssociatedModel($controller) {
		return str_replace(['\\Controllers', '\\Backend', '\\Frontend', 'Controller'], ['\\Models', '', '', ''], $controller);
	}

	static public function getRoles($controller) {
		$di = DI::getDefault();
		$cacheKey = 'roles-for-' . $controller . '-in-' . $di->module;
		$return = $di->getCache()->get($cacheKey);
		if (is_null($return)) {
			$return = ['list' => [], 'edit' => []]; // Pour les voir en premier dans les menus
			$annotations = $di->getAnnotations()->get($controller)->getClassAnnotations();
			if ($annotations) {
				foreach ($annotations as $annotation) {
					if ($annotation->getName() === 'Auth') {
						$roles = $annotation->getArgument(0);
						$actions = $annotation->getArgument(1);
						$effect = $annotation->getArgument(2) ?: 'unauthorized';
						if (!$roles || !$actions || $effect !== 'redirect' && $effect !== 'unauthorized') {
							throw new Exception('@Auth needs 2 or 3 parameters : @Auth(admin|{admin, user}, view|{view, list}, (redirect|unauthorized)) (controller: ' . $controller . ', parameters: ' . json_encode($annotation->getArguments()) . ')');
						}
						if (!is_array($actions)) {
							$actions = [$actions];
						}
						if (!is_array($roles)) {
							$roles = [$roles];
						}
						$roles = array_combine($roles, array_fill(0, count($roles), false));
						foreach ($actions as $a) {
							if (empty($return[$a])) {
								$return[$a] = ['effect' => $effect, 'roles' => []];
							}
							$return[$a]['roles'] = array_merge($return[$a]['roles'], $roles);
						}
					}
				}
			}
			/* Pour les droits de lecture/affichage/édition, on va confirmer l'information dans le modèle associé */
			$associatedModel = self::getAssociatedModel($controller);
			if (class_exists($associatedModel)) {
				$actions = [
					false => ['list', 'listAjax', 'show', 'suggestAjax'],
					true => ['list', 'listAjax', 'show', 'suggestAjax', 'slugAjax', 'edit'],
				];
				$classAnnotations = $di->getAnnotations()->get($associatedModel)->getClassAnnotations();
				if ($classAnnotations->has('Clonable')) {
					$actions[true][] = 'clone';
				}
				if ($classAnnotations->has('Deletable')) {
					$actions[true][] = 'delete';
				}
				foreach ($actions[true] as $action) {
					if (!empty($return[$action])) {
						throw new Exception('Overloaded right: ' . $controller . '::' . $action);
					}
					$return[$action] = ['effect' => 'unauthorized', 'roles' => []];
				}
				$roles = [];
				foreach ($di->getAnnotations()->get($associatedModel)->getPropertiesAnnotations() as $annotation) {
					if ($annotation->has('Form')) {
						foreach ($annotation->get('Form')->getArgument(0) as $role => $edit) {
							$roles[$role] = ($roles[$role] ?? false) || $edit;
						}
					}
				}
				foreach ($classAnnotations as $annotation) {
					if ($annotation->getName() === 'HasManyForm') {
						foreach ($annotation->getArgument(0) as $role => $edit) {
							$roles[$role] = ($roles[$role] ?? false) || $edit;
						}
					}
				}
				foreach ($roles as $role => $right) {
					foreach ($actions[$right] as $action) {
						$return[$action]['roles'][$role] = $right;
					}
				}
			}
			foreach ($return as $action => $data) {
				if (empty($data['roles'])) {
					unset($return[$action]);
				}
			}
			$di->getCache()->set($cacheKey, $return, Cache::short);
		}
		return $return;
	}

	static public function getRights($controller) {
		$roles = self::getRoles($controller);
		$return = [];
		foreach ($roles as $action => $data) {
			if (self::hasRole($data['roles']) === true) {
				$return[] = $action;
			}
		}
		return $return;
	}

	static public function hasRight($controller, $action) {
		$roles = self::getRoles($controller);
		if (!isset($roles[$action])) {
			return 'unauthorized';
		}
		if (self::hasRole($roles[$action]['roles']) === true) {
			return true;
		}
		return $roles[$action]['effect'];
	}

	static public function hasRole($roles, $writeRight = false) {
		$allowedRoles = 0;
		if (!is_array($roles)) {
			$roles = [$roles => false];
		}
		foreach ($roles as $role => $edit) {
			if ($writeRight === true && $edit !== true) {
				continue;
			}
			$role = constant(User::class . '::role' . ucfirst($role));
			if ($role === User::roleAnonymous) {
				/* First try anonymous role to avoid session loading */
				return true;
			}
			$allowedRoles |= $role;
		}
		$di = DI::getDefault();
		if ($di->getConfig()->debug === true) {
			$di->getConfig()->needAcl = true;
		}
		$session = $di->getSession();
		if (!($user = $session->getUser())) {
			return false;
		}
		$userRoles = $user->roles ? (int) $user->roles : 0;
		if (($userRoles & $allowedRoles) > 0) {
			return true;
		}
		return false;
	}

}
