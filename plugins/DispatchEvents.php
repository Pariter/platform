<?php

namespace Pariter\Plugins;

use Dugwood\Core\Server;
use Exception;
use Pariter\Common\Kernel;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\User\Plugin;

class DispatchEvents extends Plugin {

	public function beforeException(Event $event, Dispatcher $dispatcher, $exception) {
		switch ($exception->getCode()) {
			case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
			case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
				$dispatcher->forward([
					'module' => $dispatcher->getModuleName(),
					'namespace' => $dispatcher->getNamespaceName(),
					'controller' => 'error',
					'action' => 'notFound',
					'params' => [$exception]
				]);
				return false;
		}
		$dispatcher->forward([
			'module' => $dispatcher->getModuleName(),
			'namespace' => $dispatcher->getNamespaceName(),
			'controller' => 'error',
			'action' => 'exception',
			'params' => [$exception]
		]);
		return false;
	}

	public function beforeDispatch(Event $event, Dispatcher $dispatcher) {
		static $sMaxAge = 0, $maxAge = 0, $last = false;
		try {
			Kernel::setLanguage($dispatcher->getDI(), $dispatcher->getParam('language', 'string', 'en'));

			$controllerName = ucfirst($dispatcher->getControllerName());
			$actionName = $dispatcher->getActionName();

			$controller = $dispatcher->getNamespaceName() . '\\' . $controllerName . 'Controller';
			$annotations = $this->annotations->getMethod($controller, $actionName . 'Action');

			if ($last === false && $annotations->has('CacheControl')) {
				$cacheAnnotation = $annotations->get('CacheControl');
				$last = (bool) ($cacheAnnotation->getNamedArgument('last') ?: false);
				$surrogate = $cacheAnnotation->getNamedArgument('surrogate') ?: 0;
				$browser = $cacheAnnotation->getNamedArgument('browser') ?: 0;
				$midnight = (bool) ($cacheAnnotation->getNamedArgument('midnight') ?: false);

				$sMaxAge = (int) $surrogate;
				if ($sMaxAge > 0 && $midnight === true) {
					/* Check for midnight: postpone the cache expiration at midnight and 5 seconds */
					$midnight = mktime(0, 0, 5, date('m'), date('d') + 1, date('Y'));
					$sMaxAge = max(5, min($sMaxAge, $midnight - $_SERVER['REQUEST_TIME']));
				}
				$maxAge = (int) $browser;
				if ($this->config->debug === true && $sMaxAge * $maxAge === 0 && $annotations->get('CacheControl')->getNamedArgument('surrogate') === '0') {
					$this->response->setHeader('X-Ignore-Cache', '1');
				}
			}
			$this->response->setHeader('Cache-Control', Server::getCacheHeaders($sMaxAge, $maxAge));

			/* Check GET parameters */
			if ($this->request->isGet() === true && count($this->request->getQuery()) > 1 && $dispatcher->getDI()->module === 'frontend' && ($controllerName !== 'Error' && $controllerName !== 'Resource')) {
				$gets = $this->request->getQuery();
				$getParameters = $annotations->has('GetParameters') ? $annotations->get('GetParameters')->getArgument(0) : [];
				if ($getParameters !== 'any') {
					if (!is_array($getParameters)) {
						$getParameters = [$getParameters];
					}
					$getParameters[] = '_url';
					/* HTML validator */
					if ($this->config->debug === true && $controllerName === 'Resource' && $actionName === 'view') {
						$getParameters[] = 'key';
					}
					foreach ($getParameters as $get) {
						unset($gets[$get]);
					}
					if (count($gets) > 0) {
						trigger_error('Forbidden GET parameters: ' . implode(', ', array_keys($gets)) . ' - Missing @GetParameters({' . implode(',', array_keys($gets)) . '}) annotation on action ' . $controllerName . 'Controller::' . $actionName . 'Action?');
						$newParameters = $this->request->getQuery();
						$url = $this->request->getQuery('_url');
						unset($newParameters['_url']);
						foreach ($gets as $get => $v) {
							unset($newParameters[$get]);
						}
						if (count($newParameters) > 0) {
							$url .= '?' . http_build_query($newParameters);
						}
						$this->view->disable();
						$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
						if ($this->config->debug === true) {
							throw new Exception('Too many parameters, else redirect (302) to: ' . $url);
						}
						$this->response->redirect($url, true, 302);
						return false;
					}
				}
			}

			return true;
		} catch (Exception $exception) {
			$dispatcher->forward(['controller' => 'error', 'action' => 'exception', 'params' => [$exception]]);
			return false;
		}
	}

	public function afterDispatch(Event $event, Dispatcher $dispatcher) {
		try {
			if ($dispatcher->getReturnedValue() !== true) {
				/* Error occurred: remove cache headers */
				$this->response->setHeader('Cache-Control', Server::getCacheHeaders(0, 0));
			} else {
				/* Add last-modified */
				$this->response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME']) . ' GMT');
			}
			return true;
		} catch (Exception $exception) {
			$dispatcher->forward(['controller' => 'error', 'action' => 'exception', 'params' => [$exception]]);
			return false;
		}
	}

}
