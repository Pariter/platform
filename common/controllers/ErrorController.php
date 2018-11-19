<?php

namespace Pariter\Common\Controllers;

use Dugwood\Core\Security\Crawler;
use Dugwood\Core\Server;
use PDOException;

trait ErrorController {

	public function initNotFound($exception = null) {
		$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
		$this->response->setStatusCode(404, 'Not Found');

		$requestUri = $this->request->getServer('REQUEST_URI');
		if ($exception) {
			trigger_error('Exception: ' . $exception->getMessage(), E_USER_WARNING);
		}
		trigger_error('404: ' . $requestUri);

		/* Security attacks */
		Crawler::check(true);
	}

	public function initUnauthorized() {
		$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
		$this->response->setStatusCode(401, 'Unauthorized');
	}

	public function initException($exception = null) {
		if ($exception instanceof PDOException) {
			/* Unknown command (PXC response for not synchronous node) */
			if (strpos($exception->getMessage(), ' 1047 ') !== false && !empty($this->getDI()->getDb()->_host)) {
				/* Server is down, shut it down for 30 seconds */
				apcu_store('pxc-node-down-' . $this->getDI()->getDb()->_host, 'down', 30);
			}
		}
		$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
		if ($this->config->debug === false || $this->config->environment !== 'dev') {
			$this->response->setStatusCode(500);
		}

		/* Stop sending more data (else main error may be hidden) */
		$this->view->disable();

		if (strpos($this->dispatcher->getPreviousActionName(), 'Ajax') !== false) {
			$this->response->setContentType('application/json');
			$this->response->setContent(json_encode(false));
		} else {
			$this->response->setContent('An error has occurred');
		}
		if (is_object($exception)) {
			trigger_error('Exception: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString(), E_USER_WARNING);
		} else {
			trigger_error('Exception sans objet...', E_USER_WARNING);
		}
		$this->response->send();
	}

	public function initJavascript() {
		$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
		$this->response->setStatusCode(200, 'OK');
		$get = $this->request->getQuery();
		$e = isset($get['e']) ? $get['e'] : '';
		$f = isset($get['f']) ? $get['f'] : false;
		$l = isset($get['l']) ? $get['l'] : '_';
		$c = isset($get['c']) ? $get['c'] : '_';
		$u = isset($get['u']) ? $get['u'] : false;
		trigger_error('JS ' . mb_convert_encoding($e, 'UTF-8', 'UTF-8,ISO-8859-1') . ' ' . $f . '@' . $l . ':' . $c . ' (ip: ' . Server::getRemoteAddr() . ') (u-a: ' . $u . ')', E_USER_NOTICE);
	}

}
