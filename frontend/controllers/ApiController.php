<?php

namespace Pariter\Frontend\Controllers;

use Dugwood\Core\Cache;
use Dugwood\Core\Configuration;
use Exception;
use Pariter\Models\Image;
use Pariter\Models\Model;
use Pariter\Models\User;
use Phalcon\Db\Column;

/**
 * @Auth(anonymous, {handle})
 */
class ApiController extends Controller {

	const version = '1.0';

	/**
	 * @GetParameters({token, sort, page, include, dump, filter, meta})
	 * @CacheControl(surrogate=3600)
	 */
	public function handleAction() {
		try {
			/* Check user */
			if (!($user = User::getByToken($this->request->getQuery('token')))) {
				return $this->returnError(401, 'Missing or wrong token');
			}
			$meta = ['version' => self::version];

			$config = $this->di->getConfig();
			$cache = $this->di->getCache();

			$id = (int) $this->dispatcher->getParam('id', 'int') ?: 0;
			$type = preg_replace('~[^a-z0-9]+~', '', $this->dispatcher->getParam('type'));
			$types = $this->getAllowedTypes();
			if (!isset($types[$type])) {
				return $this->returnError(400, 'Unknown type: ' . $type);
			}
			$class = $types[$type];
			$definition = $this->getDefinition($class);
			$roles = (int) $user->roles;
			switch ($this->request->getMethod()) {
				case 'GET':
					if (!$definition['listing']) {
						return $this->returnError(404, 'No listing allowed');
					}
					/* Need read at least */
					if (($definition['read'] & $roles) === 0) {
						return $this->returnError(401, 'Missing roles: read');
					}
					$page = $this->request->getQuery('page');
					if (!is_array($page)) {
						$page = ['offset' => 0, 'limit' => 10];
					}
					if (!isset($page['offset'])) {
						$page['offset'] = 0;
					}
					if ($page['offset'] > 100000) {
						return $this->returnError(400, 'Offset cannot exceed 100000');
					}
					if (!isset($page['limit'])) {
						$page['limit'] = 10;
					}
					if ($page['limit'] > 100) {
						return $this->returnError(400, 'Limit cannot exceed 100');
					}
					$page['offset'] = max(0, (int) $page['offset']);
					$page['limit'] = max(0, (int) $page['limit']);
					$include = array_flip(explode(',', $this->request->getQuery('include')));
					$sort = $this->request->getQuery('sort') ?: '';
					$filters = $this->request->getQuery('filter') ?: [];
					if (!is_array($filters)) {
						$filters = [];
					}
					$metadata = $this->request->getQuery('meta') ?: '';
					if (empty($metadata)) {
						$metadata = [];
					} else {
						$metadata = explode(',', $metadata);
					}
					if ($sort) {
						$direction = '';
						if (isset($sort[0]) && $sort[0] === '-') {
							$direction = 'DESC';
							$sort = substr($sort, 1);
						} elseif (isset($sort[0]) && $sort[0] === '+') {
							$direction = 'ASC';
							$sort = substr($sort, 1);
						}
						if (!isset($definition['sorts'][$sort])) {
							return $this->returnError(400, 'Unknown sort field: ' . $sort);
						}
						if (!$direction) {
							$direction = $definition['sorts'][$sort][1];
						}
					}

					if ($id) {
						if (count($definition['primary']) !== 1) {
							return $this->returnError(400, 'Can\'t request objects with composite keys');
						}
						$entry = $class::findFirst($id);
						if (!$entry) {
							return $this->returnError(404, 'Object not found');
						}
						$entries = [$entry];

						$annotations = $this->di->getAnnotations()->get($class)->getClassAnnotations();
						if ($annotations->has('Varnish')) {
							foreach ($annotations->get('Varnish')->getArguments() as $rule) {
								if (strpos($rule, 'ID') !== false) {
									$cache->addBanHeader(str_replace('ID', $entry->id, $rule));
								}
							}
						}
					} else {
						$parameters = ['limit' => $page['limit'], 'offset' => $page['offset']];
						if ($sort) {
							$parameters['order'] = $definition['sorts'][$sort][0];
							$parameters['direction'] = $direction;
						}
						if ($filters) {
							foreach ($filters as $filter => $query) {
								if (!isset($definition['filters'][$filter])) {
									return $this->returnError(400, 'Unknown filter field: ' . $filter);
								}
								$f = $definition['filters'][$filter];
								switch ($f['type']) {
									case 'integer':
										if ($f['multiple']) {
											$query = explode(',', $query);
											foreach ($query as &$q) {
												$q = (int) $q;
												unset($q);
											}
										} else {
											$query = (int) $query;
										}
										break;

									case 'string':
										$query = urldecode($query);
										break;

									case 'boolean':
										$query = (bool) $query;
										break;

									default:
										return $this->returnError(400, 'Unknown filter type: ' . $f['type']);
								}
								if ($f['search'] === true) {
									/* Mixing search and sort won't work */
									if ($sort) {
										return $this->returnError(400, 'Suggest search and sorting are mutually exclusive. Use either one or the other');
									}
									/* Search through Sphinx */
									$parameters[$f['name'] . 'Search'] = $query;
								} else {
									$parameters[$f['name']] = $query;
								}
							}
						}
						$entries = $class::getAllByParameters($parameters);

						$cache->addBanHeader($type);
					}
					$results = [];
					foreach ($entries as $entry) {
						$results[] = $this->getJson((int) $user->id, $roles, $entry, $include);
					}
					if ($id) {
						$results = $results[0];
					}
					$json = ['data' => $results, 'meta' => $meta, 'jsonapi' => ['version' => '1.0']];
					break;

				case 'PATCH':
					/* Write read at least */
					if (($definition['write'] & $roles) === 0) {
						return $this->returnError(401, 'Missing roles: write');
					}
					$data = json_decode($this->request->getRawBody());
					if (!$data || !isset($data->data->type) || !isset($data->data->attributes) && !isset($data->data->relationships)) {
						return $this->returnError(403, 'Missing parameters: data or data->type or data->attributes');
					}
					if (!isset($data->data->id) && $id === 0) {
						$entry = $this->findFirst($class, $data->data, true);
						if (!$entry || !$entry->id) {
							if (($definition['upsert'] & $roles) === 0) {
								return $this->returnError(404, 'Not found (by special filter)');
							} else {
								$data->data->id = $id = -1;
							}
						} else {
							$data->data->id = $id = (int) $entry->id;
						}
					}

					if ($data->data->type !== $type || $data->data->id !== $id) {
						return $this->returnError(409, 'URI doesn\'t match data parameters: data->type or data->id');
					}

					if ($id === -1 && ($definition['upsert'] & $roles) !== 0) {
						$id = $class::upsertForApi($data->data, $roles);
						if (!$id) {
							return $this->returnError(409, 'Can\'t create new entry');
						}
					}

					$saveEntry = $class::findFirstForUpdate($id);
					if (!$saveEntry) {
						if ($id <= 0 || ($definition['upsert'] & $roles) === 0) {
							return $this->returnError(404, 'Not found (by id)');
						}
						$saveEntry = new $class();
						$saveEntry->setForUpdate();
						$saveEntry->id = $id;
					}
					$needSave = false;
					foreach ($data->data->attributes ?? [] as $attribute => $value) {
						$propertyInformation = $this->getPropertyValue($roles, $definition, $attribute, $value, $saveEntry);
						if (isset($propertyInformation['error'])) {
							return $this->returnError(403, $propertyInformation['error']);
						}
						$property = $propertyInformation['property'];
						$newValue = $propertyInformation['value'];
						$def = $definition['properties'][$property];
						if ($newValue != $saveEntry->$property) {
							if ($def['notEmpty'] === true &&
									$saveEntry->$property && ($def['format'] !== 'date' || $def['format'] !== 'datetime' || $saveEntry->$property > 0) && // Ancienne valeur non nulle
									(!$newValue || ($def['format'] === 'date' || $def['format'] === 'datetime') && $newValue <= 0)) {  // Nouvelle valeur nulle
								return $this->returnError(403, 'Current value for ' . $attribute . ' isn\'t empty, trying to set an empty value');
							}
							$needSave = true;
							$saveEntry->$property = $newValue;
						}
					}
					foreach ($data->data->relationships ?? [] as $relationship => $value) {
						if (!isset($definition['relationships'][$relationship])) {
							return $this->returnError(403, 'Unknown relationship: ' . $relationship);
						}
						$def = $definition['relationships'][$relationship];
						if ($def['write'] === 0 || ($def['write'] & $roles) === 0) {
							return $this->returnError(403, 'Read-only or invalid relationship: ' . $relationship);
						}

						if ($def['oneOrMany'] === 'many') {
							if (!isset($value->data) || !is_array($value->data)) {
								return $this->returnError(403, 'Sub-property data not found for relationship: ' . $relationship);
							}
							$relations = [];
							$relationDefinition = $this->getDefinition($def['intermediate']);
							foreach ($value->data as $v) {
								if (empty($v->type) || $v->type !== $def['type']) {
									return $this->returnError(403, 'Wrong type for relationship: ' . $relationship . ' (' . $v->type . ', should be ' . $def['type'] . ')');
								}
								if (empty($v->id)) {
									$entry = $this->findFirst($def['class'], $v);
									if (!$entry || !$entry->id) {
										return $this->returnError(404, 'Model not found by filters: ' . $v->type);
									}
									$v->id = (int) $entry->id;
								}
								$relation = ['id' => (int) $v->id];
								foreach ($v->relationAttributes ?? [] as $attribute => $value) {
									$propertyInformation = $this->getPropertyValue($roles, $relationDefinition, $attribute, $value);
									if (isset($propertyInformation['error'])) {
										return $this->returnError(403, $propertyInformation['error']);
									}
									$relation[$propertyInformation['property']] = $propertyInformation['value'];
								}
								$relations[] = $relation;
							}
							$model = ['property' => $def['relation'], 'type' => 'hasmany', 'model' => $def['class'], 'relatedModel' => $def['intermediate'], 'alias' => $def['relation'], 'subalias' => $def['finalProperty'], 'computation' => false];
							if ($saveEntry->updateRelations($relations, $model) === true) {
								$needSave = true;
							}
						} elseif ($def['oneOrMany'] === 'one') {
							$related = null;
							if (!is_null($value->data)) {
								if (empty($value->data->type) || $value->data->type !== $def['type']) {
									return $this->returnError(403, 'Wrong type for relationship: ' . $relationship . ' (' . $value->data->type . ' should be ' . $def['type'] . ')');
								}
								$related = $this->findFirst($def['class'], $value->data);
							}
							if ($def['notEmpty'] === true && !$related && $saveEntry->{$def['relation']}) {
								return $this->returnError(403, 'Current value for ' . $relationship . ' isn\'t empty, trying to set an empty value');
							}
							$saveEntry->{$def['relation']} = $related;
							$needSave = true;
						} else {
							return $this->returnError(500, 'Unknown one or many relationship: ' . $relationship);
						}
					}
					if ($needSave === true && !$saveEntry->save()) {
						foreach ($saveEntry->getMessages() as $message) {
							trigger_error($message->getMessage(), E_USER_WARNING);
						}
						return $this->returnError(500, 'Can\'t update object');
					}
					if (Configuration::dev === true && ob_get_length() !== 0) {
						exit;
					}
					$this->response->setStatusCode(204, 'No Content');
					$json = false;
					break;

				default:
					return $this->returnError(400, 'Wrong method');
			}
		} catch (Exception $exception) {
			trigger_error($exception->getMessage(), E_USER_WARNING);
			if ($exception->getCode() === 100001) {
				return $this->returnError(404, 'Object used in relationship not found');
			}
			if ($exception->getCode() === 100002) {
				return $this->returnError(404, $exception->getMessage());
			}
			return $this->returnError(503, 'Unknown error');
		}

		$this->view->disable();
		if (ob_get_length() === 0) {
			$this->response->setContentType('application/vnd.api+json');
			$this->response->setHeader('Access-Control-Allow-Origin', '*');
			$this->response->setHeader('X-Content-Type-Options', 'nosniff');
		}
		if ($this->request->getQuery('dump')) {
			$this->response->setContentType('text/html');
			ini_set('serialize_precision', 14); // Else float are endless
			$var = highlight_string('<?php $json = ' . str_replace(['array (', '),'], ['{', '},'], preg_replace('~\d+\s*=>\s*~s', '', preg_replace('~\'(.+?)\'\s*=>\s*~', '\\1: ', preg_replace('~\'([^\']+)\'\s*=>\s*array \(~s', '\\1: {', preg_replace('~\d+\s*=>\s*array \(~s', '{', var_export($json, true)))))) . ' ?>', true);
			$var = '<code>' . substr($var, strpos($var, '<br />') + 6);
			$var = substr($var, 0, strrpos($var, '<br />)')) . '<br/>}';
			$this->response->setContent($var);
		} elseif ($json) {
			$this->response->setContent(json_encode($json));
		}
		$this->response->send();
		return true;
	}

	private function getPropertyValue($roles, $definition, $attribute, $value, $entry = null) {
		if (!isset($definition['reverse'][$attribute])) {
			return $this->returnError(403, 'Unknown attribute: ' . $attribute);
		}
		$property = $definition['reverse'][$attribute];
		$def = $definition['properties'][$property];
		if ($def['write'] === 0 || ($def['write'] & $roles) === 0) {
			return $this->returnError(403, 'Read-only attribute: ' . $attribute);
		}
		$newValue = $value;
		switch ($def['type']) {
			case 'integer':
				if ($def['format'] === 'boolean') {
					$newValue = $value ? 1 : 0;
				}
				break;

			case 'string':
				if ($def['format'] === 'date' && !preg_match('~^\d{4}-\d{2}-\d{2}$~D', $value)) {
					return ['error' => 'Wrong date format, should be YYYY-MM-DD'];
				}
				if ($def['format'] === 'datetime' && !preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$~D', $value)) {
					return ['error' => 'Wrong date format, should be YYYY-MM-DD HH:MM:SS'];
				}
				break;

			default:
				return ['error' => 'Unknown definition\'s type'];
		}
		if ($def['writeMethod']) {
			$newValue = $definition['class']::{$def['writeMethod']}($entry->$property, $newValue);
		}
		return ['property' => $property, 'value' => $newValue];
	}

	private function findFirst($class, $data, $allowNull = false) {
		$entry = null;
		$definition = $this->getDefinition($class);
		$columns = ['id' => 'id'];
		foreach ($definition['filters'] as $attribute => $filter) {
			if ($filter['unique'] === true) {
				$columns[$attribute] = $filter['name'];
			}
		}
		foreach ($columns as $attribute => $column) {
			if (!empty($data->$attribute) && ($entry = $class::findFirst(['[' . $column . '] = :APR0:', 'bind' => ['APR0' => $data->$attribute]]))) {
				break;
			}
		}
		if (!$entry && $allowNull === false) {
			throw new Exception('Entry not found for ' . $definition['self'] . ' (using filters on: ' . implode(', ', array_keys($columns)) . ')', 100002);
		}
		return $entry;
	}

	private function getAllowedTypes() {
		static $types = null;
		if (!isset($types)) {
			$key = 'api-get-allowed-types';
			$types = $this->di->getCache()->get($key);
			if (is_null($types)) {
				preg_match('~\{type:\((.+?)\)\}~', $this->router->getRouteByName('api')->getPattern(), $match);
				$types = [];
				foreach (explode('|', $match[1]) as $type) {
					$class = 'Pariter\\Models\\' . ucfirst(substr(str_replace(['tries'], ['trys'], $type), 0, -1));
					if (constant($class . '::keys') === $type) {
						$types[$type] = $class;
					}
				}
				$this->di->getCache()->set($key, $types, Cache::short);
			}
		}
		return $types;
	}

	private function getDefinition($class) {
		static $definitions = [];
		$class = ltrim($class, '\\');
		$key = 'api-get-definition-' . $class;
		if (isset($definitions[$key])) {
			return $definitions[$key];
		}
		$definition = $this->di->getCache()->get($key);
		if (is_null($definition)) {
			$entry = new $class();
			$metadata = $entry->getModelsMetaData();
			$dataTypes = $metadata->getDataTypes($entry);
			$columnsMap = $metadata->getReverseColumnMap($entry);
			$definition = ['class' => $class, 'self' => $class::keys, 'read' => 0, 'write' => 0, 'upsert' => 0, 'properties' => [], 'relationships' => [], 'reverse' => [], 'sorts' => [], 'filters' => [], 'primary' => [], 'availableFilters' => method_exists($class, 'getAllowedTypes'), 'listing' => method_exists($class, 'getAllByParameters')];
			$belongsTo = [];
			foreach ($this->modelsManager->getBelongsTo($entry) as $relation) {
				$belongsTo[$relation->getOptions()['alias']] = [$relation->getReferencedModel(), $relation->getFields()];
			}
			$hasMany = [];
			foreach ($this->modelsManager->getHasMany($entry) as $relation) {
				$intermediateModel = $relation->getReferencedModel();
				$intermediate = new $intermediateModel();
				$intermediateBelongsTo = $this->modelsManager->getBelongsTo($intermediate);
				if (count($intermediateBelongsTo) === 3) {
					foreach ($intermediateBelongsTo as $idx => $belong) {
						if (isset($belong->getOptions()['ignoreforsuggests'])) {
							unset($intermediateBelongsTo[$idx]);
						}
					}
				}
				if (count($intermediateBelongsTo) !== 2) {
					continue;
				}
				$index = 0;
				if ($intermediateBelongsTo[0]->getReferencedModel() === $class) {
					$index = 1;
				}
				$hasMany[$relation->getOptions()['alias']] = [$intermediateBelongsTo[$index]->getReferencedModel(), $intermediateBelongsTo[$index]->getOptions()['alias'], $intermediateModel];
			}
			$imageTypes = [];
			$apiReverseTypes = array_flip($this->getAllowedTypes());
			$propertiesAnnotations = $this->annotations->get($entry)->getPropertiesAnnotations();
			foreach ($this->annotations->get($entry)->getClassAnnotations() as $annotation) {
				if ($annotation->getName() === 'HasManyForm' && $annotation->getArgument(1) === 'images') {
					foreach ($annotation->getNamedArgument('types') as $type => $max) {
						if (!is_numeric($max)) {
							$imageTypes[] = $max;
						} else {
							$imageTypes[] = $type;
						}
					}
				} elseif ($annotation->getName() === 'ApiDefinition') {
					$definition['one'] = $class::key;
					$definition['many'] = $class::keys;
					$definition['description'] = $annotation->getArgument(0);
					foreach (explode(',', $annotation->getNamedArgument('upsert') ?: '') as $role) {
						if ($role && ($value = constant(User::class . '::role' . $role))) {
							$definition['upsert'] |= $value;
						}
					}
				} elseif ($annotation->getName() === 'Api') {
					$property = $annotation->getArgument(0);
					$roles = [$this->getRoles('read', $annotation), $this->getRoles('write', $annotation)];
					if (isset($belongsTo[$property])) {
						$definition['relationships'][$annotation->getNamedArgument('alias') ?: $property] = ['class' => $belongsTo[$property][0], 'relation' => $property, 'property' => $belongsTo[$property][1], 'type' => $apiReverseTypes[$belongsTo[$property][0]], 'oneOrMany' => 'one', 'description' => $annotation->getNamedArgument('description'), 'notEmpty' => $annotation->getNamedArgument('notEmpty') ? true : false, 'read' => $roles[0], 'write' => $roles[1]];
					} elseif (isset($hasMany[$property])) {
						if ($property === 'images') {
							foreach ($imageTypes as $imageType) {
								$oneOrMany = Image::$imageTypes[$imageType] === 'multiple' ? 'many' : 'one';
								$definition['relationships']['images:' . ($oneOrMany === 'many' ? 'images' : 'image') . '-' . $imageType] = ['class' => $hasMany[$property][0], 'relation' => $property, 'type' => $apiReverseTypes[$hasMany[$property][0]], 'oneOrMany' => $oneOrMany, 'finalProperty' => $hasMany[$property][1], 'notEmpty' => $annotation->getNamedArgument('notEmpty') ? true : false, 'read' => $roles[0], 'write' => $roles[1]];
							}
						} else {
							$definition['relationships'][$annotation->getNamedArgument('alias') ?: $property] = ['class' => $hasMany[$property][0], 'relation' => $property, 'type' => $apiReverseTypes[$hasMany[$property][0]], 'oneOrMany' => 'many', 'intermediate' => $hasMany[$property][2], 'finalProperty' => $hasMany[$property][1], 'notEmpty' => $annotation->getNamedArgument('notEmpty') ? true : false, 'description' => $annotation->getNamedArgument('description'), 'meta' => $annotation->getNamedArgument('meta'), 'read' => $roles[0], 'write' => $roles[1]];
						}
					} else {
						trigger_error('Not found: ' . $property, E_USER_WARNING);
					}
					/* Property-less model */
					foreach (explode(',', $annotation->getNamedArgument('read') ?: 'Anonymous') as $role) {
						if ($role && ($value = constant(User::class . '::role' . $role))) {
							$definition['read'] |= $value;
						}
					}
				} elseif ($annotation->getName() === 'ApiSort') {
					$definition['sorts'][$annotation->getArgument(0)] = [$annotation->getArgument(1), $annotation->getNamedArgument('direction') === 'DESC' ? 'DESC' : 'ASC'];
				} elseif ($annotation->getName() === 'ApiFilter') {
					$unique = isset($propertiesAnnotations[$annotation->getArgument(1)]) && $propertiesAnnotations[$annotation->getArgument(1)]->has('Unique') ? $propertiesAnnotations[$annotation->getArgument(1)]->get('Unique') : false;
					if ($unique && $unique->getArgument(0)) { // multiple unique key
						$unique = false;
					}
					$filter = ['name' => $annotation->getArgument(1), 'default' => !is_null($annotation->getNamedArgument('default')) ? $annotation->getNamedArgument('default') : '', 'search' => (bool) $annotation->getNamedArgument('search'), 'type' => $annotation->getNamedArgument('type'), 'values' => $annotation->getNamedArgument('values') ?: '', 'multiple' => (bool) $annotation->getNamedArgument('multiple'), 'unique' => $unique ? true : false, 'description' => $annotation->getNamedArgument('description')];
					$definition['filters'][$annotation->getArgument(0)] = $filter;
				}
			}
			foreach ($propertiesAnnotations as $property => $annotations) {
				if ($annotations->has('Primary')) {
					$definition['primary'][] = $property;
				}
				foreach (['Api', 'ApiAgain'] as $argument) {
					if ($annotations->has($argument)) {
						$def = [];
						$annotation = $annotations->get($argument);
						$def['name'] = $annotation->getNamedArgument('name') ?: $property;
						$def['function'] = $annotation->getNamedArgument('function');
						$def['firstParameter'] = $annotation->getNamedArgument('firstParameter') ?: false;
						if (is_string($def['firstParameter']) && strpos($def['firstParameter'], '::') !== false) {
							if (strpos($def['firstParameter'], 'self::') === 0) {
								$def['firstParameter'] = constant($class . '::' . substr($def['firstParameter'], 6));
							} else {
								$def['firstParameter'] = constant('Pariter\\Models\\' . $def['firstParameter']);
							}
						}
						$def['secondParameter'] = $annotation->getNamedArgument('secondParameter') ?: false;
						$def['description'] = $annotation->getNamedArgument('description') ?: '';
						$def['emptyToNull'] = $annotation->getNamedArgument('emptyToNull') ? true : false;
						$def['notEmpty'] = $annotation->getNamedArgument('notEmpty') ? true : false;
						$def['format'] = $annotation->getNamedArgument('format') ?: 'string';
						if ($argument !== 'ApiAgain' && $def['format'] === 'string' && $annotations->has('Column')) {
							$def['format'] = $annotations->get('Column')->getNamedArgument('type') ?: 'string';
						}
						$def['read'] = $this->getRoles('read', $annotation);
						$definition['read'] |= $def['read'];
						$def['write'] = $this->getRoles('write', $annotation);
						$definition['write'] |= $def['write'];
						$def['writeMethod'] = $annotation->getNamedArgument('writeMethod') ?: false;
						switch ($dataTypes[$columnsMap[$property]]) {
							case Column::TYPE_INTEGER:
								$def['type'] = 'integer';
								break;

							case Column::TYPE_VARCHAR:
								$def['type'] = 'string';
								break;

							default:
								trigger_error('Undefined type for ' . $class . '->' . $property . ' : ' . $dataTypes[$columnsMap[$property]]);
						}
						if ($argument !== 'Api') {
							$definition['properties'][$property . $argument] = $def;
							$definition['reverse'][$def['name']] = $property . $argument;
						} else {
							$definition['properties'][$property] = $def;
							$definition['reverse'][$def['name']] = $property;
						}
					}
				}
			}
			if (!isset($definition['one'])) {
				trigger_error('Missing ApiDefinition for ' . $class, E_USER_WARNING);
			}
			$this->di->getCache()->set($key, $definition, Cache::short);
		}
		$definitions[$key] = $definition;
		return $definition;
	}

	private function getRoles($mode, $annotation) {
		$roles = 0;
		foreach (explode(',', $annotation->getNamedArgument($mode) ?: ($mode === 'read' ? 'Anonymous' : '')) as $role) {
			if ($role && ($value = constant(User::class . '::role' . $role))) {
				$roles |= $value;
			}
		}
		if ($mode === 'write' && ($roles & User::roleAnonymous) !== 0) {
			$roles = 0;
			trigger_error('Write role for everybody!', E_USER_WARNING);
		}
		return $roles;
	}

	private function getJson($userId, $readRight, Model $entry, $include = [], $meta = false) {
		static $included = [], $level = 0;
		$definition = $this->getDefinition(get_class($entry));
		if ($level > 0 && !isset($included[$definition['self']])) {
			/* Images or languages are included in every level */
			$included[$definition['self']] = $definition['self'] === 'images' || $definition['self'] === 'language' ? 100 : $level;
		}

		/* Add «self» read right */
		if ($definition['class'] === User::class && $userId === (int) $entry->id) {
			$readRight |= User::roleSelf;
		}

		$result = [];
		foreach ($definition['primary'] as $k) {
			if (isset($result['id'])) {
				$result['id'] .= '-' . ((int) $entry->$k);
			} else {
				$result['id'] = (int) $entry->$k;
			}
		}
		$result['type'] = $definition['many'];
		$result['attributes'] = [];
		if ($meta !== false) {
			$result['meta'] = $meta;
		}

		$subInclude = [];
		foreach ($include as $inc => $v) {
			if (!isset($included[$inc]) || $included[$inc] >= $level) {
				$subInclude[$inc] = true;
			}
		}

		foreach ($definition['properties'] as $property => $data) {
			if (($data['read'] & $readRight) === 0) {
				continue;
			}
			if ($data['function']) {
				if ($data['firstParameter'] !== false) {
					if ($data['secondParameter'] !== false) {
						$value = $entry->{$data['function']}($data['firstParameter'], $data['secondParameter']);
					} else {
						$value = $entry->{$data['function']}($data['firstParameter']);
					}
				} else {
					$value = $entry->{$data['function']}();
				}
			} else {
				$value = $entry->$property;
			}
			if ($data['emptyToNull'] === true && !$value) {
				$value = null;
			}
			if (!is_null($value)) {
				if ($data['format'] === 'integer') {
					$value = (int) $value;
				} elseif ($data['format'] === 'float') {
					$value = (float) $value;
				} elseif ($data['format'] === 'boolean') {
					$value = ((is_numeric($value) && $value || is_bool($value) && $value || $value === 'Y'));
				} elseif ($data['format'] === 'arrayOfStrings') {
					foreach ($value as &$v) {
						$v = htmlspecialchars_decode($v);
						unset($v);
					}
				} else {
					if ($value === '0000-00-00') {
						$value = null;
					} else {
						$value = htmlspecialchars_decode($value);
					}
				}
			}
			$result['attributes'][$data['name']] = $value;
		}
		$result['relationships'] = [];
		$images = false;
		foreach ($definition['relationships'] as $property => $parameters) {
			$subDefinition = $this->getDefinition($parameters['class']);
			$relationField = $property;
			$value = null;
			$objectProperty = $parameters['relation'];
			if (!$objectProperty) {
				throw new Exception('Alias is missing for ' . $property);
			}
			/* At least one read role */
			if (($parameters['read'] & $readRight) === 0 || ($subDefinition['read'] & $readRight) === 0) {
				continue;
			}

			if (strpos($relationField, 'images:') === 0) {
				if ($images === false) {
					foreach ($entry->images as $image) {
						if (is_numeric($image->order)) {
							$order = ['ordered', (int) $image->order];
						} else {
							$order = explode('-', $image->order);
						}
						if (!isset($images[$order[0]])) {
							$images[$order[0]] = [];
						}
						if (count($order) === 2) {
							$images[$order[0]][$order[1]] = $image;
						} else {
							$images[$order[0]][] = $image;
						}
					}
				}
				$relationField = substr($relationField, 7);
				$imageType = explode('-', $relationField);
				$switchType = Image::$imageTypes[$imageType[1]];
				switch ($switchType) {
					case 'translated':
						foreach ([$this->config->language, 'en', 'fr'] as $language) {
							if (isset($images[$imageType[1]][$language])) {
								$value = $images[$imageType[1]][$language]->image;
								break;
							}
						}
						break;

					case 'unique':
						if (isset($images[$imageType[1]][0])) {
							$value = $images[$imageType[1]][0]->image;
						}
						break;

					case 'multiple':
						$value = [];
						if (isset($images[$imageType[1]])) {
							ksort($images[$imageType[1]]);
							foreach ($images[$imageType[1]] as $order => $image) {
								$value[] = $image;
							}
						}
						break;

					default:
						trigger_error('Unknown type: ' . $switchType, E_USER_WARNING);
				}
			} else {
				$value = $entry->$objectProperty;
			}

			if ($parameters['oneOrMany'] === 'one') {
				if (!$value) {
					$result['relationships'][$relationField] = null;
				} else {
					if (isset($include[$subDefinition['many']])) {
						/* Full model */
						$level++;
						$result['relationships'][$relationField] = ['data' => $this->getJson($userId, $readRight, $value, $subInclude)];
						$level--;
					} else {
						/* Only what's necessary */
						$result['relationships'][$relationField] = ['data' => ['id' => (int) $value->id, 'type' => $subDefinition['many']]];
					}
				}
			} else {
				$finalRelationField = $parameters['finalProperty'];
				$relations = [];
				if (isset($parameters['intermediate'])) {
					$intermediate = $this->getDefinition($parameters['intermediate']);
				}
				foreach ($value as $relation) {
					if (isset($include[$subDefinition['many']])) {
						/* Full model */
						$level++;
						$r = $this->getJson($userId, $readRight, $relation->$finalRelationField, $subInclude);
						$level--;
					} else {
						$r = ['id' => (int) $relation->$finalRelationField->id, 'type' => $subDefinition['many']];
					}
					/* Intermediate information */
					if (isset($parameters['intermediate'])) {
						foreach ($intermediate['properties'] as $p => $data) {
							if (($data['read'] & $readRight) === 0) {
								continue;
							}
							if ($data['format'] === 'integer') {
								$r['relationAttributes'][$data['name']] = (int) $relation->$p;
							} else {
								$r['relationAttributes'][$data['name']] = $relation->$p;
							}
						}
					}
					$relations[] = $r;
				}
				$result['relationships'][$relationField] = ['data' => $relations];
			}
		}
		return $result;
	}

	private function returnError($code, $error) {
		$this->view->disable();
		if (ob_get_length() === 0) {
			$this->response->setContentType('application/vnd.api+json');
			$this->response->setHeader('Access-Control-Allow-Origin', '*');
			if ($code >= 500) {
				trigger_error('Wrong call: ' . $code . ' - ' . $error, E_USER_WARNING);
			}
		}
		$this->response->setStatusCode($code);
		$this->response->setContent(json_encode(['errors' => ['error' => ['status' => $code, 'title' => $error]], 'meta' => ['version' => self::version], 'jsonapi' => ['version' => '1.0']]));
		if (!$this->response->isSent()) {
			$this->response->send();
		}
		return null;
	}

}
