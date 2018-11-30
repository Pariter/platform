<?php

namespace Dugwood\Core;

class Configuration {

	const dev = true;
	const mainId = 99;

	public static $database = ['servers' => ['db' => 99]];

	public static function getEnvironment() {
		return 'dev';
	}

}
