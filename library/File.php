<?php

namespace Pariter\Library;

use Dugwood\Core\Cache;
use Phalcon\DI;
use Phalcon\DI\Injectable;

class File extends Injectable {

	static public function findFile($name, $extension, $directory = false) {
		$di = DI::getDefault();
		$config = $di->getConfig();
		$cache = $di->getCache();
		$key = 'file-' . $name . '-' . $extension . '-' . ($directory ?: 'directory');

		if (($save = $cache->get($key)) !== null) {
			return $save;
		}
		$files = [];
		if (!$directory) {
			$directory = 'images';
			if ($extension === 'js' || $extension === 'json' || $extension === 'css' || $extension === 'txt' || $extension === 'xml' || $extension === 'csv') {
				$directory = $extension;
			}
			if ($extension === 'eot' || $extension === 'woff' || $extension === 'ttf') {
				$directory = 'fonts';
			}
			if ($directory === 'images' || $directory === 'xml' || $directory === 'csv') {
				$files[] = $config->resources . $name . '.' . $extension;
			}
			if ($directory !== 'images') {
				$files[] = __DIR__ . '/../resources/' . $directory . '/' . $name . '.' . $extension;
			}
		} else {
			if (strpos($directory, '..') !== false) {
				return false;
			}
			$files[] = rtrim($directory, '/') . '/' . $name . '.' . $extension;
		}
		foreach ($files as $file) {
			if (($filemtime = @filemtime($file))) {
				break;
			}
		}
		if (!$filemtime) {
			trigger_error('Can\'t find file: ' . $name . '.' . $extension . ' from: ' . implode(', ', $files));
			/* Try downloading the file */
			self::downloadFile($name, $extension);
			return false;
		}
		$save = ['file' => realpath($file), 'filemtime' => $filemtime, 'override' => ($directory === 'images' && $file === $files[0])];
		$cache->set($key, $save, Cache::short);
		return $save;
	}

	static public function downloadFile($name, $extension) {
		$config = DI::getDefault()->getConfig();
		if ($config->environment !== 'dev' && $config->environment !== 'staging' || $config->debug !== true || !isset($config->download)) {
			return false;
		}

		$curl = curl_init($config->download . 'resources/' . $name . '/1.' . $extension);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$contents = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($status === 200) {
			$path = $config->resources . $name . '.' . $extension;
			if (!file_put_contents($path, $contents)) {
				mkdir(dirname($path), 0755, true);
				file_put_contents($path, $contents);
			}

			clearstatcache();
		}
		return true;
	}

}
