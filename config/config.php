<?php

$_config['debug'] = isset($_config['debug']) && (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['HTTP_X_STAGING']) || !isset($_SERVER['HTTP_HOST'])) ? $_config['debug'] : false;

$root = realpath(__DIR__ . '/..') . '/';
$mediaRoot = realpath(__DIR__ . '/../..') . '/media/platform/';

return [
	'root' => $root,
	'composer' => $root . 'composer/vendor/',
	'host' => $_config['host'],
	'environment' => $_config['environment'],
	'debug' => $_config['debug'],
	'download' => $_config['debug'] === true ? $_config['download'] : '',
	'session' => $_config['session'],
	'cookiePrefix' => $_config['cookiePrefix'],
	'database' => [
		'adapter' => 'Mysql',
		'username' => $_config['database']['username'],
		'password' => $_config['database']['password'],
		'dbname' => $_config['database']['dbname'],
		'debug' => false
	],
	'application' => [
		'baseUri' => '/',
	],
	'storage' => $root . 'cache/pariter-' . $_config['environment'] . '/',
	'languages' => ['fr' => 'fr_FR', 'en' => 'en_US'],
	'language' => '',
	'media' => $mediaRoot,
	'resources' => $mediaRoot . 'resources/',
	'images' => $mediaRoot . 'images/'
];
