<?php

namespace Dugwood\Core;

class Server {

	static public function getCacheHeaders($sMaxAge, $maxAge) {
		$cacheControl = array('must-revalidate');
		if ($sMaxAge === 0) {
			$cacheControl[] = 'private';
		} else {
			$cacheControl[] = 'public';
		}
		if ($maxAge === 0) {
			$cacheControl[] = 'no-cache';
		}

		$cacheControl[] = 'max-age=' . $maxAge;
		$cacheControl[] = 's-maxage=' . $sMaxAge;
		return implode(', ', $cacheControl);
	}

	static public function sendCacheHeaders($sMaxAge, $maxAge) {
		header('Cache-Control: ' . self::getCacheHeaders($sMaxAge, $maxAge));
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME']) . ' GMT');
	}

	static public function getRemoteAddr() {
		return '192.168.0.1';
	}

	static public function getAddr() {
		return '192.168.0.254';
	}

	static public function getId() {
		return 99;
	}

	static public function isMaster() {
		return true;
	}

}
