<?php

namespace Pariter\Library;

use Dugwood\Core\Cache;
use Dugwood\Core\Command;
use Dugwood\Core\Configuration;
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
			return false;
		}
		$save = ['file' => realpath($file), 'filemtime' => $filemtime, 'override' => ($directory === 'images' && $file === $files[0])];
		$cache->set($key, $save, Cache::short);
		return $save;
	}

	static public function downloadFile($path) {
		$config = DI::getDefault()->getConfig();
		if (file_exists($path) || $config->environment !== 'dev' && $config->environment !== 'staging' || $config->debug !== true || $path[0] !== '/' || strpos($path, '..') !== false) {
			return false;
		}
		$dir = dirname($path);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$productionPath = str_replace('media/staging/static', 'media/static', $path);
		if ($config->environment === 'staging') {
			$cmd = 'cp ' . $productionPath . ' ' . $path . ' 2>&1';
		} else {
			$masterIp = Configuration::$fullList[Configuration::mainId];
			$cmd = 'scp ' . $masterIp . ':/' . $productionPath . ' ' . $path . ' 2>&1';
		}
		$result = Command::controlledExec($cmd, 50);
		if ($result !== '') {
			trigger_error('Downloading ' . $path . ' : ' . json_encode($result));
			unlink($path);
			return false;
		}
		clearstatcache();
		return true;
	}

}
