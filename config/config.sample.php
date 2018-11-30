<?php

use Phalcon\Config;

$_config = [
	'debug' => true,
	'environment' => 'dev',
	'host' => 'dev.platform.pariter.io',
	'session' => 'PHPSESSID',
	'cookiePrefix' => 'pariter',
	'database' => [
		'username' => 'root',
		'password' => 'password',
		'dbname' => 'PARITER'
	]
];

$config = require __DIR__ . '/config.php';
unset($_config);

return new Config($config);
