<?php

namespace Pariter\Common;

use Dugwood\Core\Cache;
use Dugwood\Core\Configuration;
use Dugwood\Core\Debug\Database;
use Dugwood\Core\Security\Crawler;
use Dugwood\Core\Server;
use Exception;
use Pariter\Plugins\DispatchEvents;
use Pariter\Plugins\ModelAnnotations;
use Pariter\Plugins\ModelAnnotationsMetaData;
use Pariter\Plugins\Session;
use Phalcon\Annotations\Adapter\Apcu as AnnotationsAdapter;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\DI;
use Phalcon\DI\FactoryDefault;
use Phalcon\DI\FactoryDefault\CLI;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Loader;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\Model\MetaData\Apcu as MetaDataAdapter;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;

class Kernel {

	private $di = [];

	public function getDI($module) {
		if (isset($this->di[$module])) {
			return $this->di[$module];
		}

		$namespaces = [
			'Pariter\Common\Controllers' => __DIR__ . '/controllers/',
			'Pariter\Models' => __DIR__ . '/../models/',
			'Pariter\Library' => __DIR__ . '/../library/',
			'Pariter\Plugins' => __DIR__ . '/../plugins/'
		];
		switch ($module) {
			case 'frontend':
				$namespaces['Pariter\Frontend\Controllers'] = __DIR__ . '/../' . $module . '/controllers/';
				break;
			case 'task':
				$namespaces['Pariter\Tasks'] = __DIR__ . '/../tasks/';
				break;
		}
		$loader = new Loader();
		$loader->setFileCheckingCallback('stream_resolve_include_path');
		$loader->registerNamespaces($namespaces);
		$loader->register();

		if ($module === 'task') {
			$di = new CLI();
		} else {
			/* Anti-crawlers */
			Crawler::check();
			$di = new FactoryDefault();
		}
		$this->di[$module] = &$di;

		$di->module = $module;

		$di['config'] = require __DIR__ . '/../config/config.' . Configuration::getEnvironment() . '.php';

		if ($module !== 'task') {
			$di['router'] = include __DIR__ . '/../' . $module . '/router.php';
		}

		$di['db'] = function () {
			$hosts = array_keys(Configuration::$database['servers']);
			/* Prepend with local server first (reduce network latency) */
			if (in_array(Server::getAddr(), $hosts)) {
				array_unshift($hosts, Server::getAddr());
			}
			$config = $this->getConfig()->database;
			foreach ($hosts as $host) {
				try {
					/* Node is marked down (see common/ErrorController.php) */
					if (apcu_fetch('pxc-node-down-' . $host) === 'down') {
						continue;
					}
					$hasException = false;
					$connection = new DbAdapter(array(
						"host" => $host,
						"username" => $config->username,
						"password" => $config->password,
						"dbname" => $config->dbname
					));
					if ($connection) {
						$connection->_host = $host;
						break;
					}
				} catch (Exception $e) {
					$hasException = true;
				}
			}
			if (empty($connection)) {
				throw new Exception('No database available');
			}
			if ($hasException === true) {
				throw new Exception($e);
			}
			/* SQL checks */
			if ($this->getConfig()->debug === true && $this->getConfig()->environment !== 'prod' && !defined('NO_SQL_DEBUG')) {
				$eventsManager = new EventsManager();

				$eventsManager->attach('db', function($event, $connection) {
					Database::debug($this, $event, $connection);
				});

				$connection->setEventsManager($eventsManager);
			}
			return $connection;
		};

		$di['cache'] = function () {
			return Cache::getInstance();
		};

		$di['session'] = function () {
			if ($this->getConfig()->debug === true) {
				$this->getConfig()->needAcl = true;
			}
			return new Session();
		};

		$di['modelsManager'] = function() {

			$eventsManager = new EventsManager();

			$modelsManager = new ModelsManager();

			$modelsManager->setEventsManager($eventsManager);

			$eventsManager->attach('modelsManager', new ModelAnnotations());

			return $modelsManager;
		};

		$di['modelsMetadata'] = function () {
			$metaData = new MetaDataAdapter(['prefix' => $this->getCache()->getPrefix() . 'metadata-', 'lifetime' => 3600]);

			$metaData->setStrategy(new ModelAnnotationsMetaData());

			return $metaData;
		};

		$di['annotations'] = function() {
			return new AnnotationsAdapter(['prefix' => $this->getCache()->getPrefix() . 'annotations-', 'lifetime' => 3600]);
		};

		if ($module !== 'task') {
			$di['dispatcher'] = function() {
				$eventsManager = new EventsManager();
				$eventsManager->attach('dispatch', new DispatchEvents());
				$dispatcher = new Dispatcher();
				$dispatcher->setEventsManager($eventsManager);

				return $dispatcher;
			};
		}

		$di['translate'] = function() use ($module) {
			$controller = $this->getDispatcher()->getControllerName() ?? 'index';
			$config = $this->getConfig();
			$compiledKey = $this->getCache()->getPrefix() . 'messages-' . $module . '-' . $controller . '-' . $config->language;
			if (!($messages = apcu_fetch($compiledKey))) {
				$messages = [];
				foreach (['default', $controller] as $template) {
					foreach (Translation::getAll($module, $template) as $translation) {
						if (!isset($messages[$translation->keyword])) {
							$messages[$translation->keyword] = '';
						}
						/* Load French, English, then the desired language, which will return exactly the opposite */
						foreach (['fr', 'en', $config->language] as $language) {
							if (($text = $translation->{$language . 'Text'})) {
								$messages[$translation->keyword] = $text;
							}
						}
					}
				}
				if (count($messages) > 0) {
					apcu_store($compiledKey, $messages, $this->getConfig()->debug === true ? 180 : 86400);
				}
			}

			return new NativeArray(['content' => $messages]);
		};

		$di['view'] = function () use ($module) {
			$view = new View();
			$view->setViewsDir(__DIR__ . '/../' . $module . '/views/');

			$view->registerEngines([
				'.volt' => function ($view, $di) {
					$volt = new Volt($view, $di);
					$volt->setOptions(array(
						'compiledPath' => $this->getCache()->getDirectory('volt', true),
						'compiledSeparator' => '_',
						'compileAlways' => $di->getConfig()->environment !== 'prod'
					));
					$volt->getCompiler()->addFilter('trans', function ($resolvedArgs, $exprArgs) {
								return sprintf('$this->translate->query(%s)', $resolvedArgs);
							});
					$volt->getCompiler()->addFilter('resource', function ($resolvedArgs, $exprArgs) {
								return sprintf('Pariter\Library\Url::get(\'resource\', [\'file\' => %s])', $resolvedArgs);
							});
					$volt->getCompiler()->addFilter('image', function ($resolvedArgs, $exprArgs) {
								return sprintf('Pariter\Models\Image::getTag(%s)', $resolvedArgs);
							});
					$volt->getCompiler()->addFilter('url', function ($resolvedArgs, $exprArgs) {
								return sprintf('Pariter\Library\Url::get(%s)', $resolvedArgs);
							});
					$volt->getCompiler()->addFilter('wiki', function ($resolvedArgs, $exprArgs) {
								return sprintf('Dugwood\Core\Formatter\Wiki::format(%s)', $resolvedArgs);
							});
					$volt->getCompiler()->addFilter('date', function ($resolvedArgs, $exprArgs) {
								return sprintf('Dugwood\Core\Formatter\Date::format(%s)', $resolvedArgs);
							});
					$volt->getCompiler()->addFilter('js', function ($resolvedArgs, $exprArgs) {
								return sprintf('Dugwood\Core\Formatter\Javascript::format(%s)', $resolvedArgs);
							});
					return $volt;
				}
			]);

			return $view;
		};

		return $di;
	}

	public static function getDIForTask($module = 'frontend') {
		$kernel = new self();
		$di = $kernel->getDI($module);
		DI::setDefault($di);
		self::setLanguage($di, 'en');
		return $di;
	}

	public static function setLanguage($di, $language = false) {
		static $savedLanguage = false;
		/* Keep first used language */
		if ($savedLanguage === false) {
			$savedLanguage = $language;
		}
		/* Restore first used language */
		if ($language === false) {
			$language = $savedLanguage;
		}
		$locale = $di->getConfig()->languages[$language] . '.UTF8';
		$di->getConfig()->language = $language;
		/* No LC_ALL, as French will fail with «echo (float) 1.23» */
		setlocale(LC_TIME, $locale);
		if ($language === 'en') {
			setlocale(LC_MONETARY, 'en_IE.UTF8');
		} else {
			setlocale(LC_MONETARY, 'fr_FR.UTF8');
		}
	}

}
