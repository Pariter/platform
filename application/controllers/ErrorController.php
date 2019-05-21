<?php

namespace Pariter\Application\Controllers;

use Dugwood\Core\Server;
use Pariter\Common\Controllers\ErrorController as CommonEC;

class ErrorController extends Controller {

	use CommonEC;

	public function notFoundAction() {
		$uri = $this->request->getServer('REQUEST_URI');
		if (($pos = strpos($uri, '?')) !== false) {
			$queryString = substr($uri, $pos); // keep «?»
			$uri = substr($uri, 0, $pos);
		} else {
			$queryString = '';
		}

		/* Missing/useless slash at the end? */
		if (strpos($uri, '.') === false) {
			if (substr($uri, -1) === '/') {
				$route = substr($uri, 0, -1);
			} else {
				$route = $uri . '/';
			}
			$this->router->handle($route);
			if ($this->router->wasMatched() === true) {
				return $this->redirect($route . $queryString);
			}
		}

		/* Double-slash */
		if (strpos($uri, '//') === 0) {
			$uri = substr($uri, 1);
			return $this->redirect($uri . $queryString);
		}

		return $this->initNotFound();
	}

	private function redirect($route, $code = 301) {
		$this->view->disable();
		$this->response->setHeader('Cache-Control', Server::getCacheHeaders(3600, 0));
		if ($this->config->environment === 'dev') {
			$this->view->disable();
			echo 'Redirection vers <a href="' . htmlspecialchars($route) . '">' . $route . '</a>';
			return true;
		}
		/* Forcing absolute url to avoid Url::get() prefixing it with slash */
		return $this->response->redirect($route, true, $code);
	}

	public function unauthorizedAction() {
		return $this->initUnauthorized();
	}

	public function unauthorizedIPAction() {
		return $this->initUnauthorizedIP();
	}

	public function exceptionAction($exception = null) {
		return $this->initException($exception);
	}

	public function javascriptAction() {
		return $this->initJavascript();
	}

}
