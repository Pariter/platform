<?php

namespace Pariter\Library;

use Dugwood\Core\Security\Crawler;
use Dugwood\Core\Server;
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

				case 'view':
					$url = $di->getUrl()->get(['for' => $type, 'language' => $parameters['language'], 'controller' => $parameters['controller'], 'action' => $type, 'id' => $parameters['id']]);
					break;

				case 'list':
					$url = $di->getUrl()->get(['for' => $type, 'language' => $parameters['language'], 'controller' => $parameters['controller'], 'action' => $type]);
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

	static public function redirect($url, $status, $checkDestination = true) {
		if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
			header('Status: 400');
			echo 'An error occurred / Une erreur est survenue...';
			Crawler::check(true);
			//	trigger_error('Redirect with method different from GET/HEAD : '.$_SERVER['REQUEST_METHOD'], E_USER_WARNING);
			exit;
		}

		$di = DI::getDefault();
		$config = $di->getConfig();

		if ($status === 301 || $status === 302 || $status === 303) {
			$checkRedirect = false;
			$loopUrl = $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
			if ($checkDestination === true) {
				/* Check if final url is our website */
				if (strpos($url, 'http') !== 0) {
					$url = self::getHost('http') . $url;
					$checkRedirect = true;
				} else {
					$host = parse_url($url, PHP_URL_HOST);
					if (strpos(self::getHost('front'), $host) !== false) {
						$checkRedirect = true;
					}
				}
			}
			if ($checkRedirect === true) {
				$host = parse_url($url, PHP_URL_HOST);
				$scheme = parse_url($url, PHP_URL_SCHEME);
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_TIMEOUT, 2);
				curl_setopt($curl, CURLOPT_RESOLVE, [$host . ':' . ($scheme === 'https' ? '443' : '80') . ':' . Server::getAddr()]);
				curl_setopt($curl, CURLOPT_HEADER, true);
				curl_setopt($curl, CURLOPT_NOBODY, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.86 Safari/537.36']);
				curl_setopt($curl, CURLOPT_REFERER, $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
				$result = curl_exec($curl);
				$curlStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				$curlError = curl_error($curl);
				curl_close($curl);

				if ($result) {
					$redirectUrl = false;
					if (preg_match('~Location: (.+?)\r\n~', $result, $location) === 1) {
						$redirectUrl = $location[1];
					}
					if ($redirectUrl) {
						if (strpos($redirectUrl, 'http') !== 0) {
							$redirectUrl = self::getHost('http') . $redirectUrl;
						} else {
							$host = parse_url($redirectUrl, PHP_URL_HOST);
							if (strpos(self::getHost('front'), $host) === false) {
								$redirectUrl = false;
							}
						}
					}
					/* One hop is enough: A => B => C is dealt with as A => B, which in turn will do B => C */
					if ($redirectUrl && ($curlStatus === 301 || $curlStatus === 302 || $curlStatus === 303)) {
						$status = $curlStatus;
						$url = $redirectUrl;
					} elseif ($curlStatus === 404) {
						$status = $curlStatus;
						$url = false;
					}
				} else {
					trigger_error('cURL error: ' . $curlError, E_USER_WARNING);
				}
			}

			if ($url) {
				if ($url === $loopUrl) {
					header('Status: 421');
					echo 'An error occurred / Une erreur est survenue...';
					trigger_error('redirect-loop: ' . $url, E_USER_WARNING);
					exit;
				}

				if ($config->environment === 'dev' && !$di->getRequest()->getHeader('X-PHPUNIT') && strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome/50') === false) {
					echo 'Redirect ' . $status . ' to <a href="' . $url . '">' . $url . '</a>';
					exit;
				}

				header('Status: ' . $status);
				if ($status === 301) {
					Server::sendCacheHeaders(86400, 2592000);
				}
				header('Location: ' . $url);
				header('Content-Type:', true);
				ob_end_clean();
				header('Content-Encoding:', true);
				header('Vary:', true);
			}
		}
		if ($status !== 404) {
			exit;
		}
		Crawler::check(true);
		return true;
	}

}
