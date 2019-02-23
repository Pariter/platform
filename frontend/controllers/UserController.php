<?php

namespace Pariter\Frontend\Controllers;

use Dugwood\Core\Configuration;
use Pariter\Library\Url;
use Pariter\Models\User;
use Phalcon\Exception;
use Phalcon\Mvc\Dispatcher;

/**
 * @Auth(user, {edit, editAjax})
 * @Auth(anonymous, {view, list})
 */
class UserController extends Controller {

	/**
	 * @CacheControl(surrogate=3600)
	 */
	public function viewAction() {

		$user = User::findFirst($this->dispatcher->getParam('id'));
		if (!$user) {
			throw new Exception('User not found', Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}

		$this->view->setVar('user', $user);

		/* Alternative urls */
		$hreflang = [];
		foreach ($this->config->languages as $language => $locale) {
			if (($url = Url::get('view', ['controller' => 'user', 'id' => $user->id, 'language' => $language, 'absolute' => true]))) {
				$hreflang[$language] = $url;
			}
		}
		$this->view->setVar('_hreflang', $hreflang);

		/* Varnish headers (cache) */
		$this->cache->addBanHeader('pariter-user-' . $user->id);

		return true;
	}

	/**
	 * @CacheControl(surrogate=0)
	 */
	public function editAction() {

		$user = User::findFirst((int) $this->dispatcher->getParam('id'));
		if (!$user) {
			throw new Exception('User not found', Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}
		$sessionUser = $this->session->exists() ? $this->session->getUser() : false;
		if (!$sessionUser || $user->id !== $sessionUser->id) {
			throw new Exception('Invalid user', 1000);
		}

		$this->view->setVar('user', $user);

		return null;
	}

	/**
	 * @CacheControl(surrogate=0)
	 */
	public function editAjaxAction() {

		$user = User::findFirst((int) $this->request->getPost('id'));
		if (!$user) {
			return $this->sendJson(400, ['message' => 'User not found']);
		}
		$sessionUser = $this->session->exists() ? $this->session->getUser() : false;
		if (!$sessionUser || $user->id !== $sessionUser->id) {
			return $this->sendJson(400, ['message' => 'Invalid user']);
		}

		$email = $this->request->getPost('email', 'email');
		$displayName = $this->request->getPost('displayName', 'string');
		if ($email && $displayName) {
			$user->email = $email;
			$user->displayName = $displayName;
			if ($user->save()) {
				return $this->sendJson(200, ['id' => $user->id, 'message' => 'Okay!']);
			}
		}

		return $this->sendJson(400, ['message' => 'Save failed']);
	}

	/**
	 * @CacheControl(surrogate=3600)
	 */
	public function listAction() {

		$users = User::getAllByParameters();
		if (count($users) === 0) {
			throw new Exception('Users not found', Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}

		$this->view->setVar('users', $users);

		/* Alternative urls */
		$hreflang = [];
		foreach ($this->config->languages as $language => $locale) {
			if (($url = Url::get('list', ['controller' => 'user', 'language' => $language, 'absolute' => true]))) {
				$hreflang[$language] = $url;
			}
		}
		$this->view->setVar('_hreflang', $hreflang);

		/* Varnish headers (cache) */
		$this->cache->addBanHeader('pariter-users');

		return true;
	}

	private function sendJson($code, $json) {
		$this->view->disable();
		if (Configuration::dev === false || is_null(error_get_last())) {
			$this->response->setContentType('application/json');
		}
		$this->response->setStatusCode($code);
		$this->response->setContent(json_encode($json));
		if (!$this->response->isSent()) {
			$this->response->send();
		}
		return null;
	}

}
