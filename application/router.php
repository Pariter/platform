<?php

use Phalcon\Mvc\Router;

/**
 * Registering a router
 */
return function () {
	$router = new Router(false);

	$router->setDefaultNamespace("Pariter\Application\Controllers");

	/* Progressive Web Application */
	$router->addGet('/', [
				'controller' => 'index',
				'action' => 'resource',
				'directory' => 'root',
				'name' => 'index',
				'type' => 'html'])
			->setName('application');
	$router->addGet('/{name:[a-zA-Z0-9\-_]+.[0-9a-f]+}.{type:[a-z0-9]+}', [
				'controller' => 'index',
				'action' => 'resource',
				'directory' => 'root'])
			->setName('application-resource');
	$router->addGet('/assets/{directory:[a-z0-9]+}/{name:[a-z]+}.{checksum:[0-9]+}.{type:[a-z0-9]+}', [
		'controller' => 'index',
		'action' => 'resource']);

	/* Common ressources */
	$router->addGet('/resources/{name:[a-z0-9\-]+}/{checksum:[0-9]+}.{type:[a-z0-9]+}', [
				'controller' => 'resource',
				'action' => 'view'])
			->setName('resource');
	$router->addGet('/images/{format:[a-z0-9]+}/{id:[0-9]+}.jpg', [
				'controller' => 'image',
				'action' => 'view'])
			->setName('image');
	$router->addGet('/{name:[a-z\-0-9]+\.[a-z0-9]+}', [
		'controller' => 'resource',
		'action' => 'root']);
	$router->addGet('/_', [
		'controller' => 'error',
		'action' => 'javascript']);

	$router->notFound(['controller' => 'error', 'action' => 'notFound']);

	return $router;
};
