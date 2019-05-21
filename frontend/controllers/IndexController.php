<?php

namespace Pariter\Frontend\Controllers;

use Dugwood\Core\Server;
use Phalcon\Exception;
use Pariter\Library\Url;

/**
 * @Auth(anonymous, {login, redirect})
 */
class IndexController extends Controller {

	/**
	 * @CacheControl(surrogate=3600)
	 * @GetParameters({from})
	 */
	public function loginAction() {

		$config = $this->di->getConfig();
		$providers = parse_ini_file($config->root . 'config/providers.ini', true);
		$this->view->setVar('providers', array_keys($providers));

		switch ($this->request->getQuery('from')) {
			case 'l':
				$this->view->setVar('_ignoreNav', true);
				$this->view->setVar('from', 'application');
				$this->view->setVar('origin', 'http://localhost:8100/');
				break;

			case 'd':
				$this->view->setVar('_ignoreNav', true);
				$this->view->setVar('from', 'application');
				$this->view->setVar('origin', 'https://dev.application.pariter.io/');
				break;

			default:
				$this->view->setVar('from', 'index');
				$this->view->setVar('origin', 'https://application.pariter.io/');
				break;
		}

		/* Alternative urls */
		$hreflang = [];
		foreach ($this->config->languages as $language => $locale) {
			if (($url = Url::get('login', ['language' => $language, 'absolute' => true]))) {
				$hreflang[$language] = $url;
			}
		}
		$this->view->setVar('_hreflang', $hreflang);

		/* Varnish headers (cache) */
		$this->cache->addBanHeader('pariter-login');

		return true;
	}

	/**
	 * @GetParameters(any)
	 */
	public function redirectAction() {
		$redirect = $this->request->getQuery('_url');
		$query = $this->request->getQuery();
		$hash = [];
		unset($query['_url']);

		foreach ($this->request->getQuery() as $key => $value) {
			if (strpos($key, 'utm_') === 0) {
				$hash[$key] = $value;
				unset($query[$key]);
			}
		}

		/* If only «/», redirect based on browser's language */
		if ($redirect === '/') {
			$language = strtolower(substr($this->request->getServer('HTTP_ACCEPT_LANGUAGE') ?: '', 0, 2));
			if (!isset($this->config->languages[$language])) {
				$language = 'fr';
			}
			$redirect = Url::get('login', ['language' => $language]);
			$this->response->setHeader('Vary', 'Accept-Encoding, Accept-Language');
		}

		if (count($query) > 0) {
			$redirect .= '?' . http_build_query($query);
		}
		if (count($hash) > 0) {
			$redirect .= '#' . http_build_query($hash);
		}
		if ($redirect === $this->request->getServer('REQUEST_URI')) {
			/* Loop? */
			throw new Exception('Loop detected: ' . json_encode($this->request->getQuery()));
		}

		$this->view->disable();
		if ($this->config->environment === 'dev' && !$this->request->getHeader('X-PHPUNIT')) {
			echo 'Redirect to <a href="' . htmlspecialchars($redirect) . '">' . $redirect . '</a>';
			return true;
		}

		/* Redirection on same page (enables caching) */
		$this->view->disable();
		$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
		$this->response->redirect($redirect, true, 302);
	}

}
