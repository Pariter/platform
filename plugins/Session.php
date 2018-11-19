<?php

namespace Pariter\Plugins;

use Exception;
use Pariter\Library\Url;
use Pariter\Models\Session as SessionModel;
use Pariter\Models\User;
use Phalcon\Db\RawValue;
use Phalcon\DI;
use Phalcon\Session\Adapter;
use stdClass;

class Session extends Adapter {

	private $user = false;

	public function __construct() {
		$di = DI::getDefault();
		$config = $di->getConfig();
		/* Initialize session name */
		if ($di->module === 'backend') {
			session_name($config->session . 'BO');
		} else {
			session_name($config->session);
		}
		session_set_cookie_params(0, '/', '.' . Url::getHost('cookie'));

		/* Disabled on Debian as a default, but necessary for MySQL garbage collector */
		ini_set('session.gc_probability', '1');

		session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'], [$this, 'write'], [$this, 'destroy'], [$this, 'gc']);
	}

	/**
	 * Check if session's cookie exists, prevents a call to the database for nothing
	 */
	public function exists() {
		return isset($_COOKIE[session_name()]) && $_COOKIE[session_name()];
	}

	public function open() {
		return true;
	}

	public function close() {
		return true;
	}

	public function read($sessionId) {
		$session = SessionModel::findFirst(['[id] = :APR0:', 'bind' => ['APR0' => $sessionId]]);
		if ($session) {
			return $session->data;
		}
		return false;
	}

	public function write($sessionId, $data) {
		$session = SessionModel::findFirst(['[id] = :APR0:', 'bind' => ['APR0' => $sessionId]]);
		if ($session && !empty($data['_delete'])) {
			return $session->delete() === true;
		}
		if (!$session) {
			$session = new SessionModel();
			$session->id = $sessionId;
			$session->expires = new RawValue('NOW()');
		}

		if ($session->data === $data) {
			return true;
		}
		$session->data = $data;
		return $session->save() !== false;
	}

	/**
	 * @param  string  $sessionId
	 * @return boolean
	 */
	public function destroy($sessionId = null) {
		if ($sessionId === null) {
			$sessionId = $this->getId();
		}

		/* Delete from database (see write() function below) */
		$this->set('_delete', true);

		/* Remove associated user (see getUser()) */
		$this->user = false;

		/* PHP ignores the cookie deletion, must do it ourselves */
		$params = session_get_cookie_params();
		setcookie(session_name(), '', $_SERVER['REQUEST_TIME'] - 86400 * 365, $params['path'], $params['domain']);
		setcookie(DI::getDefault()->getConfig()->cookiePrefix . 'U', '', $_SERVER['REQUEST_TIME'] - 86400 * 365, $params['path'], $params['domain']);
	}

	public function gc($maxlifetime) {
		$maxlifetime = (int) ceil(max($maxlifetime, 3600) / 86400);
		foreach (SessionModel::find(['[expires] <= SUBDATE(NOW(), :APR0:)', 'bind' => ['APR0' => $maxlifetime]]) as $session) {
			$session->delete();
		}
		return true;
	}

	public function setUser(User $user) {
		if (!$this->isStarted()) {
			$this->start();
		}
		$this->user = $user;
		$this->setUserDataCookie($user);
		return $this->set('userId', (int) $user->id);
	}

	public function getUser() {
		if ($this->user) {
			return $this->user;
		}
		try {
			if (!$this->exists()) {
				return false;
			}
			if (!$this->isStarted()) {
				$this->start();
			}
			if (($id = (int) $this->get('userId')) && ($user = User::findFirst($id))) {
				$this->user = $user;
				$this->setUserDataCookie($user);
				return $user;
			}
			/* No user data: data was lost, delete everything */
			$this->destroy();
			return false;
		} catch (Exception $e) {
			trigger_error('Exception occurred: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update session's data
	 *
	 * @param User $user
	 */
	private function setUserDataCookie(User $user) {
		$config = DI::getDefault()->getConfig();
		/* Objet for cookie */
		$data = new stdClass();
		$data->id = (int) $user->id;
		$data->displayName = $user->displayName;
		$data = json_encode($data);

		/* Check if cookie is up to date */
		$cookieName = $config->cookiePrefix . 'U';
		$cookie = isset($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : false;
		if ($cookie !== $data) {
			$params = session_get_cookie_params();
			setcookie($cookieName, $data, $params['lifetime'], $params['path'], $params['domain']);
		}
	}

}
