<?php

namespace Pariter\Frontend\Controllers;

use Pariter\Library\Url;
use Pariter\Models\User;
use Phalcon\Exception;

/**
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

}
