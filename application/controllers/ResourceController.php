<?php

namespace Pariter\Application\Controllers;

use Dugwood\Core\Command;
use Dugwood\Core\Server;
use Pariter\Library\File;
use Pariter\Library\Url;
use Phalcon\Exception;
use Phalcon\Mvc\Dispatcher;

class ResourceController extends Controller {

	/**
	 * @CacheControl(surrogate=3600, browser=86400)
	 */
	public function rootAction($name) {
		$this->dispatcher->setParam('name', pathinfo($name, PATHINFO_FILENAME));
		$this->dispatcher->setParam('type', pathinfo($name, PATHINFO_EXTENSION));
		$this->dispatcher->forward(array('action' => 'view'));
	}

	/**
	 * @CacheControl(surrogate=86400, browser=2592000)
	 */
	public function viewAction() {
		$name = preg_replace('~[^a-z\-_0-9\.]+~i', '', $this->dispatcher->getParam('name'));
		$extension = preg_replace('~[^a-z0-9]+~', '', $this->dispatcher->getParam('type'));
		$directory = $this->dispatcher->getParam('directory') ?: false;

		if (($file = File::findFile($name, $extension, $directory)) === false) {
			throw new Exception('File not found: ' . $name . '.' . $extension, Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}

		$content = file_get_contents($file['file']);
		if ($content === false) {
			throw new Exception('Invalid file: ' . $name . '.' . $extension);
		}

		$this->cache->addBanHeader('pariter-static-file-' . str_replace('.', '-', $name) . '-' . $extension);
		$return = true;

		switch ($extension) {
			case 'json':
				if ($name === 'manifest') {
					$max = preg_match_all('~__(.+?\..+?)__~', $content, $resources);
					for ($i = 0; $i < $max; $i++) {
						$content = str_replace($resources[0][$i], str_replace('/', '\\/', Url::get('resource', ['file' => $resources[1][$i]])), $content);
					}
				}
				$this->response->setContentType('application/json');
				break;

			case 'js':
				$content = str_replace('__DEBUG__', $this->config->debug === true ? 'true' : 'false', $content);
				$productionFiles = [];

				if ($name === 'cordova' && strpos($content, 'cordova_plugins.js') !== false) {
					$content = str_replace('cordova_plugins.js', ltrim(Url::get('application-resource', ['file' => 'cordova_plugins.js']), '/'), $content);
					$content = preg_replace('~function findCordovaPath.+?return path;~s', 'function findCordovaPath() { return \'/\';', $content);
				} elseif ($name === 'cordova_plugins') {
					$max = preg_match_all('~"(plugins/.+?)"~', $content, $resources);
					for ($i = 0; $i < $max; $i++) {
						$content = str_replace($resources[0][$i], '"' . ltrim(Url::get('application-resource', ['file' => $resources[1][$i]]), '/') . '"', $content);
					}
				}

				if (strpos($content, '/assets/i18n/') !== false && strpos($name, 'main.') !== false) {
					$timestamp = 0;
					foreach (glob($this->config->application->htdocs . 'assets/i18n/*') as $f) {
						$timestamp = max($timestamp, filemtime($f));
					}
					$content = str_replace('".json"', '".' . $timestamp . '.json"', $content);
				}

				/* Production files are already minified */
				if (count($productionFiles) > 0) {
					$max = preg_match_all('~,includeMinified\((\d+)\);~', $content, $includes);
					for ($i = 0; $i < $max; $i++) {
						$include = $productionFiles[$includes[1][$i]];
						if (!file_exists($include) || !($include = file_get_contents($include))) {
							throw new Exception('Missing file');
						}
						$content = str_replace($includes[0][$i], ';' . trim($include, ';') . ';', $content);
					}
				}

				$this->response->setContentType('text/javascript');
				break;

			case 'css':
				$this->response->setContentType('text/css');
				break;

			case 'png':
				$this->response->setContentType('image/png');
				if ($this->config->environment === 'prod') {
					$thumb = tempnam($this->cache->getDirectory(), 'resourceController');
					file_put_contents($thumb, $content);
					$cmd = 'optipng -silent -o5 ' . escapeshellarg($thumb);
					$result = Command::controlledExec($cmd, 60);
					if ($result !== '') {
						trigger_error($result);
					} else {
						$content = file_get_contents($thumb);
					}
					unlink($thumb);
				}
				break;

			case 'gif':
				$this->response->setContentType('image/gif');
				break;

			case 'jpg':
				$this->response->setContentType('image/jpeg');
				break;

			case 'ico':
				$this->response->setContentType('image/x-icon');
				break;

			case 'eot':
				$this->response->setContentType('application/vnd.ms-fontobject');
				break;

			case 'woff':
				$this->response->setContentType('application/font-woff');
				break;

			case 'woff2':
				$this->response->setContentType('application/font-woff2');
				break;

			case 'ttf':
				$this->response->setContentType('application/x-font-truetype');
				break;

			case 'txt':
				$this->response->setContentType('text/plain');
				break;

			case 'xml':
				$this->response->setContentType('text/xml');
				break;

			case 'html':
				/* Force headers for freshness */
				$this->response->setHeader('Cache-Control', Server::getCacheHeaders(3600, 0));
				$this->response->setContentType('text/html');
				break;

			default:
				throw new Exception('Unknown extension: ' . $extension, Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}

		if ($this->config->environment === 'dev' && ob_get_length() !== 0) {
			$this->response->setContentType('text/html');
			throw new Exception('Error shown before content');
		}

		$this->view->disable();
		$this->response->setContent($content);
		$this->response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $file['filemtime']) . ' GMT');

		$this->response->send();

		return $return;
	}

}
