<?php

namespace Pariter\Frontend\Controllers;

use Dugwood\Core\Server;
use Exception;
use Hybrid_Auth;
use Hybrid_Endpoint;
use Pariter\Library\Url;
use Pariter\Models\User;
use Phalcon\Exception as PException;

class AuthController extends Controller {

	/**
	 * @CacheControl(surrogate=0)
	 * @GetParameters(any)
	 */
	public function hybridAction() {
		$config = $this->di->getConfig();
		require_once $config->composer . 'autoload.php';

		$provider = preg_replace('~[^a-z0-9]+~i', '', $this->request->getQuery('provider', 'string'));

		$providers = parse_ini_file($config->root . 'config/providers.ini', true);
		if (!isset($providers[$provider])) {
			throw new PException('Unknown provider: ' . $provider);
		}

		$providerConfiguration = [];
		foreach ($providers[$provider] as $key => $value) {
			if (($pos = strpos($key, '.')) !== false) {
				$providerConfiguration[substr($key, 0, $pos)][substr($key, $pos + 1)] = $value;
			} else {
				$providerConfiguration[$key] = $value === 'true' ? true : $value;
			}
		}

		if (isset($providerConfiguration['wrapper']['class'])) {
			if (isset($providerConfiguration['wrapper']['path'])) {
				$providerConfiguration['wrapper']['path'] = $config->composer . 'hybridauth/hybridauth/additional-providers/' . $providerConfiguration['wrapper']['path'] . '/Providers/' . $providerConfiguration['wrapper']['class'] . '.php';
			}
			$providerConfiguration['wrapper']['class'] = 'Hybrid_Providers_' . $providerConfiguration['wrapper']['class'];
		}

		$hybridConfig = [
			'base_url' => Url::get('auth', ['action' => 'endpoint', 'absolute' => true]),
			'providers' => [
				$provider => $providerConfiguration
			],
			'debug_mode' => $config->environment === 'dev',
			'debug_file' => $config->root . 'cache/hybridauth_debug.log'
		];
		$hybridauth = new Hybrid_Auth($hybridConfig);
		$adapter = $hybridauth->authenticate($provider);
		$profile = $adapter->getUserProfile();
		/* Destroy HybridAuth session */
		session_destroy();

		try {
			/* Look for the user in the database */
			$user = User::upsertByProvider($provider, $profile->identifier);
			if ($user) {
				$this->session->setUser($user);
				$this->session->set('profile', json_encode($profile));
				$this->view->setVar('registered', true);
			}
		} catch (Exception $e) {
			$this->view->setVar('registered', false);
			trigger_error($e->getMessage(), E_USER_WARNING);
		}
	}

	/**
	 * @CacheControl(surrogate=0)
	 * @GetParameters(any)
	 */
	public function endpointAction() {
		$this->view->disable();
		require_once $this->di->getConfig()->composer . 'autoload.php';
		Hybrid_Endpoint::process();
		exit;
	}

	/**
	 * @CacheControl(surrogate=0)
	 */
	public function profileAction() {
		/* Init session */
		$user = $this->session->getUser();
		$profile = json_decode($this->session->get('profile') ?: '{}');

		if ($this->request->isPost()) {
			$this->view->disable();

			$email = $this->request->getPost('email', 'email');
			$displayName = $this->request->getPost('displayName', 'string');
			if (!$email || !$displayName) {
				$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
				$this->response->redirect(Url::get('auth', ['action' => 'profile']), true, 302);
				return false;
			}
			$user = User::findFirst($user->id);
			$user->email = $email;
			$user->displayName = $displayName;
			if (!$user->save()) {
				throw new Exception('Can\'t save user');
			}

			$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
			$this->response->redirect(Url::get('auth', ['action' => 'thanks']), true, 302);
			return false;
		}

		$this->view->setVar('email', $user->email ?: $profile->email ?? '');
		$this->view->setVar('displayName', $user->displayName ?: $profile->displayName ?? '');
		return true;
	}

	/**
	 * @CacheControl(surrogate=0)
	 */
	public function thanksAction() {

	}

}
