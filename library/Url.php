<?php

namespace Pariter\Library;

use Pariter\Common\Kernel;
use Phalcon\DI;
use Phalcon\DI\Injectable;

class Url extends Injectable {

	private static $_urls = [];

	public static function get($model, $parameters = [], $di = false) {
		if ($di === false) {
			$di = DI::getDefault();
		}
		$config = $di->getConfig();

		if (isset($parameters['absolute']) || $model === 'image') {
			$absolute = true;
			unset($parameters['absolute']);
		} else {
			$absolute = false;
		}
		/* Missing model */
		if (is_bool($model)) {
			return '';
		}
		if (is_object($model)) {
			$type = strtolower(str_replace('Pariter\\Models\\', '', get_class($model)));
			$key = $type . $model->id;
		} else {
			$type = $model;
			$key = $type;
		}
		if (!isset($parameters['language'])) {
			$setLanguage = false;
			$parameters['language'] = $config->language;
		} else {
			$setLanguage = true;
		}
		ksort($parameters);
		$key .= '-' . serialize($parameters);

		if (!isset(self::$_urls[$key])) {
			if ($setLanguage === true) {
				Kernel::setLanguage($di, $parameters['language']);
			}
			$url = '';
			$allowedQueries = [];
			switch ($type) {
				case 'login':
					$url = $di->getUrl()->get(['for' => $type, 'language' => $parameters['language']]);
					break;

				case 'auth':
					$url = $di->getUrl()->get(['for' => $type, 'language' => $parameters['language'], 'controller' => $type, 'action' => $parameters['action']]);
					break;

				case 'resource':
					$resource = preg_replace('~[^a-z0-9\.\-]+~', '', $parameters['file']);
					$filename = pathinfo($resource, PATHINFO_FILENAME);
					$extension = pathinfo($resource, PATHINFO_EXTENSION);
					if (($file = File::findFile($filename, $extension)) !== false) {
						$url = $di->getUrl()->get(['for' => 'resource', 'name' => $filename, 'type' => $extension, 'checksum' => $file['filemtime']]);
					}
					break;

				default:
					trigger_error('Unknown url: ' . $type . ': ' . json_encode($parameters));
			}
			if ($url && count($allowedQueries) > 0) {
				$urlQueries = [];
				foreach ($allowedQueries as $query) {
					if (!empty($parameters[$query])) {
						$urlQueries[$query] = urlencode($parameters[$query]);
					}
				}
				if (count($urlQueries) > 0) {
					$url .= '?' . http_build_query($urlQueries, null, '&amp;');
				}
			}
			if ($url && !empty($parameters['hash'])) {
				$url .= '#' . $parameters['hash'];
			}

			if ($setLanguage === true) {
				Kernel::setLanguage($di);
			}
			self::$_urls[$key] = $url;
		} else {
			$url = self::$_urls[$key];
		}

		if ($absolute === true && $url) {
			return self::getHost('http') . '/' . ltrim($url, '/');
		}
		return $url;
	}

	static public function getHost($type = 'host') {
		$config = DI::getDefault()->getConfig();
		$host = $config->host;
		if ($type === 'host') {
			return $host;
		}
		if ($type === 'http' || $type === 'front' || $type === 'back') {
			return 'https://' . $host;
		}
		/* For cookies: only main domain */
		return substr($host, strpos($host, '.') + 1);
	}

}
