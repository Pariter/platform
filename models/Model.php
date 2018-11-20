<?php

namespace Pariter\Models;

use Dugwood\Core\Cache;
use Dugwood\Core\Debug\Database;
use Phalcon\DI;
use Phalcon\Exception;
use Phalcon\Mvc\Model as MainModel;
use Phalcon\Mvc\Model\MetaData;

class Model extends MainModel {

	/** @var boolean cache Cache type */
	const cache = Cache::none;

	/** @var boolean _fromCache Object comes from the cache, forbids saving */
	private $_fromCache = false;
	private $_hasChanged = false;

	public static function findFirst($parameters = null) {
		/* Foreign keys are NULL but request is made anyway (https://github.com/phalcon/cphalcon/issues/2953) */
		if (isset($parameters['bind']) && array_key_exists('APR0', $parameters['bind']) && !array_key_exists('APR1', $parameters['bind']) && is_null($parameters['bind']['APR0'])) {
			return false;
		}

		if ($parameters === null) {
			throw new Exception('Requête de l\'intégralité de la table interdite');
		} elseif (is_numeric($parameters)) {
			$parameters = ['[id] = :APR0:', 'bind' => ['APR0' => $parameters]];
		}

		$di = DI::getDefault();

		$queryMax = false;
		if (isset($parameters['query-max'])) {
			$queryMax = $parameters['query-max'];
			unset($parameters['query-max']);
		}
		$key = false;
		if (isset($parameters['cache'])) {
			unset($parameters['cache']);
			$key = self::_getKey($parameters, true);
		}

		if ($key === false || is_null($result = $di->getCache()->get($key))) {
			if ($di->getConfig()->debug === true && $queryMax) {
				Database::setQueryMax($queryMax);
			}
			$result = parent::findFirst($parameters);

			if ($key !== false && $result) {
				$result->_fromCache = true;
				$di->getCache()->set($key, $result, static::cache);
			}
		}

		return $result;
	}

	public static function find($parameters = null) {
		if ($parameters === null) {
			throw new Exception('Requête de l\'intégralité de la table interdite');
		}

		$di = DI::getDefault();

		$queryMax = false;
		if (isset($parameters['query-max'])) {
			$queryMax = $parameters['query-max'];
			unset($parameters['query-max']);
		}
		$key = false;
		if (isset($parameters['cache'])) {
			unset($parameters['cache']);
			$key = self::_getKey($parameters, false);
		}

		if ($key === false || is_null($result = $di->getCache()->get($key))) {
			if (DI::getDefault()->getConfig()->debug === true && $queryMax) {
				Database::setQueryMax($queryMax);
			}
			$result = parent::find($parameters);

			if ($key !== false && $result) {
				$result->_fromCache = true;
				$di->getCache()->set($key, $result, static::cache);
			}
		}

		return $result;
	}

	public static function deleteCache($parameters, $first) {
		if (($key = self::_getKey($parameters, $first)) === false) {
			return false;
		}
		return DI::getDefault()->getCache()->delete($key);
	}

	static private function _getKey($parameters, $first) {
		if (!is_array($parameters)) {
			trigger_error('Parameters must be sent as array: ' . json_encode($parameters), E_USER_WARNING);
			return false;
		}
		if (static::cache === Cache::none || isset($parameters['conditions']) || isset($parameters['order']) || isset($parameters['limit']) || isset($parameters['columns'])) {
			//	trigger_error('no-cache: ' . get_called_class() . ' ' . json_encode($parameters));
			return false;
		}
		unset($parameters['di']);
		if (array_keys($parameters) !== [0, 'bind']) {
			trigger_error('Parameter #0 must be an SQL query, or you should use «conditions» to bypass this caching mechanism: ' . json_encode($parameters), E_USER_WARNING);
			return false;
		}
		$class = get_called_class();
		$allowedQueries = self::getAllowedQueries($class, $first);
		if (!isset($allowedQueries['sql'][$parameters[0]])) {
			trigger_error('Missing cache: ' . $class . ' (' . $parameters[0] . ') - Missing «conditions» or missing «@Unique»?', E_USER_WARNING);
			return false;
		}
		return vsprintf($allowedQueries['sql'][$parameters[0]][1], $parameters['bind']);
	}

	static public function getAllowedQueries($class, $first) {
		static $queries = [];
		$q = &$queries[$first][$class];
		if (!isset($q)) {
			$entity = new $class();
			$q = ['keys' => [], 'sql' => []];
			$di = DI::getDefault();
			$prefix = str_replace('\\', '_', $class) . '-';
			if ($first === true) {
				$prefix .= 'findFirst-by';
				// Via findFirst
				$properties = $di->getAnnotations()->get($entity)->getPropertiesAnnotations();
				if (!$properties) {
					throw new Exception("There are no properties defined on the class");
				}

				$uniqueKeys = [];
				foreach ($properties as $name => $collection) {
					if ($collection->has('Column')) {
						if ($collection->has('Primary')) {
							$uniqueKeys['__PRIMARY'][] = $name;
						}

						if ($collection->has('Unique')) {
							if ($collection->get('Unique')->numberArguments() === 0) {
								$uniqueKeys['__' . $name] = [$name];
							} else {
								foreach ($collection->get('Unique')->getArguments() as $argument) {
									$uniqueKeys[$argument][] = $name;
								}
							}
						}
					}
				}
				foreach ($uniqueKeys as $keys) {
					sort($keys);
					$bind = 0;
					$sql = [];
					$cacheKey = $prefix;
					foreach ($keys as $key) {
						$q['keys'][$key] = true;
						$sql[] = '[' . $key . '] = :APR' . $bind++ . ':';
						$cacheKey .= '-' . $key . '-%s';
					}
					$q['sql'][implode(' AND ', $sql)] = [$keys, $cacheKey];
				}
			} else {
				$prefix .= 'find-by';
				/* Via find => no need to list primary keys, else findFirst() should have been used */
				foreach ($di->getModelsManager()->getBelongsTo($entity) as $belong) {
					$q['sql']['[' . $belong->getFields() . '] = :APR0:'] = [$belong->getFields(), $prefix . '-' . $belong->getFields() . '-%s'];
				}
			}
		}
		return $q;
	}

	public function getAssociatedName() {
		return strtolower(str_replace('Pariter\\Models\\', '', get_called_class()));
	}

	public function getSuggestedColumn() {
		$suggest = $this->getDI()->getAnnotations()->get($this)->getClassAnnotations()->get('Suggest');
		if ($suggest) {
			return $this->{$suggest->getArgument(0)};
		}
		return $this->id;
	}

	public function getTranslatedField($function, $optional = false) {
		$fields = [];
		foreach ($this->getDI()->getConfig()->languages as $language => $locale) {
			$fields[$language] = $this->$function($language, $optional);
		}
		return $fields;
	}

	public function initialize() {
		$this->useDynamicUpdate(true);
		$this->keepSnapshots(true);
	}

	public function beforeSave() {
		if ($this->_fromCache === true) {
			trigger_error('Save forbidden, object read from cache', E_USER_WARNING);
			return false;
		}
		if ($this->hasSnapshotData() === true && count($this->getChangedFields()) > 0) {
			$this->_hasChanged = true;
		}
		$di = $this->getDI();
		$annotations = $di->getAnnotations()->get($this)->getPropertiesAnnotations();
		/* Encode HTML characters in database */
		foreach ($di->getModelsMetadata()->readColumnMapIndex($this, MetaData::MODELS_COLUMN_MAP) as $property) {
			if ($property[0] !== '_' && property_exists($this, $property) && !is_object($this->$property) && !is_array($this->$property) && !is_numeric($this->$property) && $annotations[$property]->has('HtmlSafe') === false) {
				$value = $this->$property;
				if ($value && !($this instanceof Session)) {
					$value = htmlspecialchars(htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5), ENT_COMPAT | ENT_HTML5);
				}
				$this->$property = $value;
			}
		}
	}

	public function afterSave() {
		if ($this->_hasChanged === true) {
			$di = $this->getDI();
			$class = get_class($this);
			foreach (self::getAllowedQueries($class, true)['sql'] as $sql => $cacheKey) {
				$binds = [];
				foreach ($cacheKey[0] as $property) {
					$binds[] = $this->$property;
				}
				self::deleteCache([$sql, 'bind' => $binds], true);
			}
			foreach ($di->getModelsManager()->getHasMany($this) as $relation) {
				$model = $relation->getReferencedModel();
				/* Fake request */
				$model::deleteCache(['[' . $relation->getReferencedFields() . '] = :APR0:', 'bind' => ['APR0' => $this->{$relation->getFields()}]], false);
			}
			$cache = $di->getCache();
			$annotations = $di->getAnnotations()->get($class)->getClassAnnotations();
			if ($annotations->has('Varnish')) {
				foreach ($annotations->get('Varnish')->getArguments() as $rule) {
					$cache->ban(str_replace('ID', $this->id, $rule));
				}
			}
			if (defined($class . '::keys')) {
				$cache->deleteTimedKey($class::keys);
			}
		}
	}

}
