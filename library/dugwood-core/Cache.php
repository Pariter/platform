<?php

namespace Dugwood\Core;

class Cache {

	/**
	 * Cache types :
	 * ultraShort : 5 min
	 * short : 1h
	 * long : 1d
	 * midnight : at midnight
	 */
	const none = 0;
	const ultraShort = 1;
	const ultraShortMidnight = 2;
	const short = 3;
	const shortMidnight = 4;
	const long = 5;
	const longMidnight = 6;

	private static $_instance = false;
	private $_releaseKey = '';
	private $_release = 0;
	private $_prefix = '';
	private $_directory = '';
	private $_cache = [];

	/**
	 * @return self
	 */
	public static function getInstance() {
		if (self::$_instance === false) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		$this->_releaseKey = 'dev-release';
		$this->_directory = realpath(__DIR__ . '/../..') . '/cache/';
		$release = apcu_fetch($this->_releaseKey);
		if (!$release) {
			$this->updateRelease();
		} else {
			$this->_release = $release;
		}
		$this->_prefix = 'cache-server-' . $this->_release . '-';
	}

	public function getPrefix() {
		return $this->_prefix;
	}

	public function getRelease() {
		return $this->_release;
	}

	public function get($key) {
		$key = $this->_prefix . $key;

		if (isset($this->_cache[$key])) {
			return $this->_cache[$key];
		}

		$result = apcu_fetch($key, $success);
		if ($success === true) {
			return ($this->_cache[$key] = $result);
		}

		return null; // no cache
	}

	public function set($key, $result, $type) {
		if ($type === self::ultraShort || $type === self::ultraShortMidnight) {
			$duration = 300;
		} elseif ($type === self::short || $type === self::shortMidnight) {
			$duration = 3600;
		} elseif ($type === self::long || $type === self::longMidnight) {
			$duration = 86400;
		} elseif ($type === self::debugTime) {
			$duration = 5;
		} else {
			trigger_error('Unknown cache: ' . $type);
			return false;
		}
		$this->_cache[$key] = $result;

		return apcu_store($key, $result, $duration);
	}

	public function delete($key) {
		$key = $this->_prefix . $key;
		unset($this->_cache[$key]);

		return apcu_delete($key);
	}

	public function ban($rule) {
		return true;
	}

	public function addBanHeader($rule) {
		return true;
	}

	public function sendBanHeader() {

	}

	public function getTimedKey($key, $subKey) {
		static $keys = [];
		if (!isset($keys[$key])) {
			$keys[$key] = apcu_fetch($this->_prefix . 'timed-key-' . $key);
			if (!$keys[$key]) {
				$keys[$key] = $_SERVER['REQUEST_TIME'];
				apcu_store($this->_prefix . 'timed-key-' . $key, $keys[$key]);
			}
		}
		if ($subKey === '_timestamp') {
			return $keys[$key];
		}
		return 'timed-key-' . $key . '-' . $keys[$key] . '-' . $subKey;
	}

	public function deleteTimedKey($key) {
		$this->delete('timed-key-' . $key);
	}

	public function updateRelease($time = false, $deleteOthers = false) {
		if ($time === false) {
			$time = $_SERVER['REQUEST_TIME'];
		} elseif ($time === true) {
			$time = $this->_release;
		}
		$this->_release = $time - $time % 10;

		$this->checkDirectory($this->getDirectory('volt', true));

		apcu_store($this->_releaseKey, $this->_release, 86400 * 365);
	}

	public function checkDirectory($directory) {
		if (strpos($directory, $this->_directory) !== 0) {
			$directory = $this->_directory . $directory;
		}
		if (strpos($directory, '.') !== false) {
			throw new Exception('Wrong directory: ' . $directory);
		}
		if (!is_dir($directory)) {
			mkdir($directory, 0755, true);
		}
		return $directory;
	}

	public function getDirectory($subPath = '', $withRelease = false) {
		$path = $this->_directory;
		if ($subPath) {
			$path .= $subPath;
			if ($withRelease === true) {
				$path .= '-' . $this->_release;
			}
			$path .= '/';
		}
		return $path;
	}

	public function __destruct() {
		$this->sendBanHeader();
	}

}
