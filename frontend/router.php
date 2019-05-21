<?php

use Phalcon\Mvc\Router;

/**
 * Registering a router
 */
return function () {
	$router = new Router(false);

	$router->setDefaultNamespace("Pariter\Frontend\Controllers");

	/* Redirect to language */
	$router->addGet('/', [
		'controller' => 'index',
		'action' => 'redirect']);
	/* Login page */
	$router->addGet('/{language:[a-z]{2}}/', [
				'controller' => 'index',
				'action' => 'login'])
			->setName('login');
	/* Auth */
	$router->addGet('/{language:[a-z]{2}}/{controller:auth}/{action:(hybrid|endpoint|profile|thanks|token)}')->setName('auth');
	$router->addPost('/{language:[a-z]{2}}/{controller:auth}/{action:(profile)}');

	/* API */
	$router->add('/{language:[a-z]{2}}/api/{type:(users)}/{id:[0-9]*}', [
				'controller' => 'api',
				'action' => 'handle'])
			->setName('api');

	/* Progressive Web Application */
	$router->addGet('/{language:[a-z]{2}}/application/', [
				'controller' => 'application',
				'action' => 'resource',
				'directory' => 'root',
				'name' => 'index',
				'type' => 'html'])
			->setName('application');
	$router->addGet('/application/{directory:[a-z0-9\-/]+}/{name:[a-zA-Z0-9\-_\.]+}.{checksum:[0-9]+}.{type:[a-z0-9]+}', [
				'controller' => 'application',
				'action' => 'resource'])
			->setName('application-resource');

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
