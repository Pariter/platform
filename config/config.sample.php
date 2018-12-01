<?php

use Phalcon\Config;

$_config = [
	'debug' => true,
	'environment' => 'dev',
	'host' => 'localhost:50080',
	'session' => 'PHPSESSID',
	'cookiePrefix' => 'pariter',
	'database' => [
		'username' => 'root',
		'password' => 'password',
		'dbname' => 'PARITER'
	],
	'download' => 'https://platform.pariter.io/'
];

$config = require __DIR__ . '/config.php';
unset($_config);

return new Config($config);
