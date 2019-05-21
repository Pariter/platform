<?php

namespace Pariter\Frontend\Controllers;

use Dugwood\Core\Configuration;
use Phalcon\Exception;
use Phalcon\Forms\Element\Check;
use Phalcon\Forms\Element\Radio;
use Phalcon\Forms\Element\Select;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\Textarea;
use Phalcon\Forms\Form;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Validation\Exception as PVException;
use stdClass;
use Pariter\Forms\Element\Suggest;
use Pariter\Library\Auth;
use Pariter\Library\Url;
use Pariter\Models\User;

class EditableController extends Controller {

	private function getAssociatedModel($model = null) {
		if (!$model) {
			$model = get_called_class();
		}
		return Auth::getAssociatedModel($model);
	}

	private function checkUserRights($entity) {
		$sessionUser = $this->session->exists() ? $this->session->getUser() : false;
		$entityUser = get_class($entity) === User::class ? $entity->id : $entity->user->id;
		if (!$sessionUser || $entityUser !== $sessionUser->id) {
			throw new Exception('Invalid user', 1000);
		}
	}

	/**
	 * @CacheControl(surrogate=0)
	 */
	public function editAction() {

		$class = $this->getAssociatedModel();
		$entity = $class::findFirst((int) $this->dispatcher->getParam('id'));
		if (!$entity) {
			throw new Exception('Entity not found', Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}
		$this->checkUserRights($entity);

		$this->edit(false, false);

		return null;
	}

	/**
	 * @CacheControl(surrogate=0)
	 */
	public function editAjaxAction() {

		$class = $this->getAssociatedModel();
		$entity = $class::findFirst((int) $this->dispatcher->getParam('id'));
		if (!$entity) {
			return $this->sendJson(400, ['message' => 'Entity not found']);
		}
		$this->checkUserRights($entity);

		return $this->edit(false, false);
	}

	private function edit($readOnly, $clone) {

		$entityModel = $this->getAssociatedModel();
		$id = (int) $this->dispatcher->getParam('id', 'int');
		if ($clone && $this->request->isPost()) {
			$id = 0;
		}
		if (!$id) {
			$entity = new $entityModel();
			$reload = true;
		} else {
			$entity = $entityModel::findFirst((int) $this->dispatcher->getParam('id', 'int'));
			$reload = false;
		}
		$suggests = [];

		$classAnnotations = $this->annotations->get($entity)->getClassAnnotations();
		$this->view->setVar('entityTitle', $entity->getSuggestedColumn());
		$controller = strtolower(substr($entityModel, strrpos($entityModel, '\\') + 1));

		$formGroups = array_flip($classAnnotations->has('FormGroups') ? $classAnnotations->get('FormGroups')->getArgument(0) : ['default']);

		$form = [
			'url' => Url::get('editAjax', ['controller' => $controller, 'action' => $clone ? 'clone' : 'edit', 'id' => $entity->id ? $entity->id : 0]),
			'readonly' => $readOnly,
			'formGroups' => $formGroups,
			'elements' => []
		];

		$orderedProperties = [];
		$postSaveRelations = [];
		$properties = [];
		$nullable = [];
		$relatedProperties = [];
		$position = 100;
		/* @Form's annotations */
		foreach ($this->annotations->get($entity)->getPropertiesAnnotations() as $property => $annotations) {
			foreach ($annotations as $annotation) {
				if ($annotation->getName() === 'Form' && Auth::hasRole($annotation->getArgument(0))) {
					$orderedProperties[sprintf('%02d%03d', $formGroups[$annotation->getNamedArgument('group') ?: 'default'], $annotation->getNamedArgument('position') ?: $position++)] = $property;
					$properties[$property] = $annotation;
				}
			}
			if ($annotations->has('Column')) {
				if ($annotations->get('Column')->hasArgument('nullable') && $annotations->get('Column')->getArgument('nullable') === true) {
					$nullable[$property] = true;
				}
			}
		}
		/* @HasMany's annotations */
		$nextForm = false;
		foreach ($this->annotations->get($entity)->getClassAnnotations() as $annotation) {
			if ($nextForm !== false) {
				if ($annotation->getName() === 'HasManyForm' && Auth::hasRole($annotation->getArgument(0))) {
					$property = $nextForm->getArgument(3)['alias'];
					$orderedProperties[sprintf('%02d%03d', $formGroups[$annotation->getNamedArgument('group') ?: 'default'], $annotation->getNamedArgument('position') ?: $position++)] = $property;
					$properties[$property] = $annotation;
					$relatedProperties[$property] = $nextForm;
				}
				$nextForm = false;
			}
			if ($annotation->getName() === 'HasMany') {
				$nextForm = $annotation;
			}
		}

		ksort($orderedProperties);
		foreach ($orderedProperties as $property) {
			$annotation = $properties[$property];
			$options = [];
			$options['escape'] = false;
			/* Associated table */
			$model = false;
			foreach ($this->modelsManager->getBelongsTo($entity) as $relation) {
				if ($relation->getFields() === $property) {
					$model = ['property' => $property, 'type' => 'belongsto', 'model' => $relation->getReferencedModel(), 'alias' => $relation->getOptions()['alias'], 'controller' => strtolower(substr($relation->getReferencedModel(), strrpos($relation->getReferencedModel(), '\\') + 1)), 'computation' => $relation->getOptions()['computation'] ?? ''];
					if (isset($nullable[$property])) {
						$nullable[$model['alias']] = $nullable[$property];
					}
					$property = $model['alias'];
					break;
				}
			}
			foreach ($this->modelsManager->getHasMany($entity) as $relation) {
				if ($relation->getOptions()['alias'] === $property) {
					if (isset($relatedProperties[$property])) {
						/* Indirect association */
						$key = $relatedProperties[$property]->getArgument(2);

						$m = $relation->getReferencedModel();
						$belongsTo = $this->modelsManager->getBelongsTo(new $m());
						if (count($belongsTo) === 3) {
							foreach ($belongsTo as $idx => $belong) {
								if (isset($belong->getOptions()['ignoreforsuggests'])) {
									unset($belongsTo[$idx]);
								}
							}
						}
						if (count($belongsTo) === 2) {
							$alreadyRead = false;
							foreach ($belongsTo as $r) {
								if ($r->getReferencedModel() !== $entityModel || $alreadyRead === true) {
									$model = ['property' => $property, 'type' => 'hasmany', 'model' => $r->getReferencedModel(), 'relatedModel' => $relation->getReferencedModel(), 'alias' => $relation->getOptions()['alias'], 'subalias' => $r->getOptions()['alias'], 'controller' => strtolower(substr($r->getReferencedModel(), strrpos($r->getReferencedModel(), '\\') + 1)), 'computation' => $relation->getOptions()['computation'] ?? ''];
									break;
								}
								if ($r->getReferencedModel() === $entityModel) {
									$alreadyRead = true;
								}
							}
						}
					}
					if ($model === false) {
						$model = ['property' => $property, 'type' => 'hasmany', 'model' => $relation->getReferencedModel(), 'alias' => $relation->getOptions()['alias'], 'controller' => strtolower(substr($relation->getReferencedModel(), strrpos($relation->getReferencedModel(), '\\') + 1)), 'computation' => $relation->getOptions()['computation'] ?? ''];
					}
					if ($model === false) {
						throw new Exception('Error looking for model: ' . $property);
					}
					$property = $model['alias'];
					break;
				}
			}

			if (Auth::hasRole($annotation->getArgument(0), true) === false || $clone === false && $annotation->getNamedArgument('readonlyifnotempty') === true && $entity->$property) {
				$options['readonly'] = 'readonly';
			} elseif ($clone === false && $annotation->getNamedArgument('hiddenifnotempty') === true && $entity->$property) {
				continue;
			} elseif (!$readOnly && $this->request->isPost() && $annotation->getNamedArgument('hiddenforpost') !== true) {
				if (!isset($_POST[$property])) {
					$return['message'] = 'Missing field ' . $property;
					return $this->sendJson(400, $return);
				}
				if ($model !== false) {
					$newValue = null;
					if ($model['type'] === 'belongsto') {
						foreach ($annotation->getArgument(1) === 'radio' ? [$_POST[$property]] : $_POST[$property] as $v) {
							if (!empty($v)) {
								$newValue = $model['model']::findFirst((int) $v);
								break;
							}
						}
					} else {
						$newValue = [];
						foreach ($_POST[$property] as $key => $p) {
							if ($p) {
								$newValue[$key] = $model['model']::findFirst((int) $p);
							}
						}
					}
				} else {
					$newValue = $_POST[$property];
				}
				if (($annotation->getNamedArgument('hiddenifnotempty') || $annotation->getNamedArgument('readonlyifnotempty')) && !$entity->$property && $newValue) {
					$reload = true;
				}
				if (($annotation->getArgument(1) === 'radio' || $annotation->getArgument(1) === 'text') && empty($newValue) && isset($nullable[$property])) {
					$newValue = null;
				}
				if (($annotation->getArgument(1) === 'images' || $annotation->getArgument(1) === 'suggest' || $annotation->getArgument(1) === 'checkboxes') && is_array($newValue)) {
					$function = 'update' . ucfirst($property);
					if (!method_exists($entity, $function)) {
						$postSaveRelations[] = [$newValue, $model, $annotation];
					} else {
						try {
							$entity->$function($newValue, $model, $annotation);
						} catch (\Exception $e) {
							if ($this->config->debug !== true) {
								trigger_error($e->getMessage(), E_USER_WARNING);
							}
							if ($e instanceof PVException) {
								return $this->sendJson(400, ['message' => $e->getMessage()]);
							}
							return $this->sendJson(400, ['message' => substr($e->getMessage(), 0, Configuration::dev === true ? 500 : 30)]);
						}
					}
				} else {
					if ($model !== false && is_null($newValue)) {
						$entity->{$model['property']} = null;
					} else {
						$entity->$property = $newValue;
					}
				}
			}

			if (!$this->request->isPost()) {
				if (!$id) {
					/* Looking in GET for default values */
					if ($model === false && ($getParameter = $this->request->getQuery($property, 'string'))) {
						$entity->$property = $getParameter;
					}
				}
				if ($readOnly) {
					$options['readonly'] = 'readonly';
				}
				$options['class'] = 'form-control';
				$options['data-form-group'] = $annotation->getNamedArgument('group') ?: 'default';
				$this->addDataColumns($options, $annotation->getNamedArgument('columns'));
				switch ($annotation->getArgument(1)) {
					case 'textarea':
						$options['cols'] = 50;
						$options['rows'] = 1;
						$form['elements'][] = [
							'type' => 'textarea',
							'name' => $property,
							'value' => $entity->$property,
							'options' => $options
						];
						break;

					case 'datetime':
					case 'datehour':
					case 'date':
					case 'time':
					case 'hour':
						$options['class'] .= ' ' . $annotation->getArgument(1) . 'Picker';
					case 'text':
						if (($format = $annotation->getNamedArgument('format'))) {
							if ($format === 'float') {
								$options['onkeyup'] = 'if (this.value.indexOf(\',\') !== -1) this.value = this.value.replace(/,/g, \'.\');';
							}
						}
						$form['elements'][] = [
							'type' => 'text',
							'component' => 'TextInput',
							'label' => $this->translate->query($property),
							'name' => $property,
							'value' => $entity->$property,
							'options' => $options
						];
						break;

//					case 'select':
//						$values = $annotation->getNamedArgument('values');
//						if (!is_array($values)) {
//							throw new Exception('Missing parameter «values»');
//						}
//						foreach ($values as $value) {
//							$_values[$value] = $this->translate->query($property . '_label_for_' . $value);
//						}
//						$options['name'] = $property;
//						$options['value'] = $entity->$property;
//						$element = new Select($property, $_values, $options);
//						$form->add($element);
//						break;
//					case 'radio':
//						if ($model) {
//							$values = [];
//							foreach ($model['model']::find(['limit' => 50]) as $m) {
//								$values[$m->id] = $m->getName();
//							}
//						} else {
//							$values = $annotation->getNamedArgument('values');
//							$values = array_combine($values, array_fill(0, count($values), ''));
//						}
//						if (!is_array($values)) {
//							throw new Exception('Missing parameter «values»');
//						}
//						/* Undefined value => NULLable */
//						if (isset($nullable[$property])) {
//							$values['NULL'] = '';
//						}
//						$i = 1;
//						if (isset($options['readonly'])) {
//							unset($options['readonly']);
//							$options['disabled'] = 'disabled';
//						}
//						$options['class'] = null;
//						$options['name'] = $property;
//						$options['data-count'] = count($values);
//						if ($model) {
//							if (empty($entity->$property)) {
//								$defaultValue = 'NULL';
//							} else {
//								$defaultValue = $entity->$property->id;
//							}
//						} elseif (isset($nullable[$property]) && is_null($entity->$property)) {
//							$defaultValue = 'NULL';
//						} else {
//							$defaultValue = $entity->$property;
//						}
//						foreach ($values as $key => $value) {
//							$options['value'] = $key;
//							$options['data-label'] = $value;
//							$radio = new Radio($property . $i, $options);
//							$radio->setDefault($defaultValue);
//							$i++;
//							$form->add($radio);
//						}
//						break;
//					case 'checkbox':
//						if (isset($options['readonly'])) {
//							unset($options['readonly']);
//							$options['disabled'] = 'disabled';
//						}
//						$options['class'] = null;
//						$options['name'] = $property;
//						if (($value = $annotation->getNamedArgument('unchecked')) !== null) {
//							$options['data-default'] = $value;
//						}
//						if (($value = $annotation->getNamedArgument('checked')) !== null) {
//							$options['value'] = $value;
//						} else {
//							$options['value'] = '1';
//						}
//						$check = new Check($property, $options);
//						$form->add($check);
//						break;
//
//					case 'suggest':
//						/* Multiple association */
//						if (($models = $annotation->getNamedArgument('models'))) {
//							$values = [];
//							foreach ($models as $m) {
//								$titleField = $this->annotations->get('Pariter\\Models\\' . ucfirst($m))->getClassAnnotations()->get('Suggest')->getArgument(0);
//								foreach ($entity->{$m . 's'} as $value) {
//									$values[$value->order] = [$m . '-' . $value->$m->id => '(' . $this->translate->query($m) . ') ' . $value->$m->$titleField];
//								}
//							}
//							ksort($values);
//							$options['values'] = [];
//							foreach ($values as $v) {
//								list($k, $v) = each($v);
//								$options['values'][$k] = $v;
//							}
//							$options['data-id'] = $property;
//							$options['onlyone'] = $annotation->getNamedArgument('onlyone') ?: false;
//							$options['ordered'] = $annotation->getNamedArgument('ordered') ?: false;
//							$options['controller'] = $controller;
//							$options['suggestAction'] = $annotation->getNamedArgument('suggestAction');
//							$options['models'] = $annotation->getNamedArgument('models');
//							$suggest = new Suggest($property, $options);
//							$suggests[] = $suggest->getJavascriptInfo($this->getDI());
//							$form->add($suggest);
//							break;
//						}
//
//						if ($model === false) {
//							throw new Exception('pas de modèle référencé : ' . $property);
//						}
//						$options['model'] = $model['model'];
//						$options['values'] = [];
//						$options['ordered'] = $annotation->getNamedArgument('ordered') ?: false;
//						if (!$entity->{$model['alias']}) {
//							$values = [];
//							/* Reading in GET values */
//							if (($getId = $this->request->getQuery($model['alias'], 'int'))) {
//								if (($value = $model['model']::findFirst($getId))) {
//									$values[] = $value;
//								}
//							}
//						} elseif (is_object($entity->{$model['alias']}) && $entity->{$model['alias']} instanceof Model) {
//							$values = [$entity->{$model['alias']}];
//						} else {
//							$values = $entity->{$model['alias']};
//						}
//						$index = 0;
//						$newValues = [];
//						foreach ($values as $value) {
//							if ($options['ordered']) {
//								$index = $value->order;
//							} else {
//								$index++;
//							}
//							if (isset($model['subalias'])) {
//								$alias = $model['subalias'];
//								$newValues[$index] = [$value->$alias->id, $value->$alias->getSuggestedColumn()];
//							} else {
//								$newValues[$index] = [$value->id, $value->getSuggestedColumn()];
//							}
//						}
//						ksort($newValues);
//						foreach ($newValues as $value) {
//							$options['values'][$value[0]] = $value[1];
//						}
//						$options['data-id'] = $property;
//						$options['onlyone'] = $annotation->getNamedArgument('onlyone') ?: false;
//						if (($suggestFilter = $annotation->getNamedArgument('suggestFilter'))) {
//							$options['suggestFilter'] = [];
//
//							foreach ($suggestFilter as $p => $v) {
//								if (is_numeric($v)) {
//									$options['suggestFilter'][$p] = $v;
//								} elseif (method_exists($entity, $v)) {
//									$options['suggestFilter'][$p] = $entity->$v();
//								} else {
//									$options['suggestFilter'][$p] = $entity->$v;
//								}
//							}
//						}
//						$options['popoveradd'] = $annotation->getNamedArgument('popoveradd') ?: false;
//						$options['controller'] = $model['controller'];
//						if (!$options['onlyone']) {
//							$options['remove'] = true;
//						}
//						$suggest = new Suggest($property, $options);
//						$suggests[] = $suggest->getJavascriptInfo($this->getDI());
//						$form->add($suggest);
//						break;
//
//					case 'checkboxes':
//						$options['class'] = null;
//						$list = [];
//						if ($model === false) {
//							/* Most probably a binary sum */
//							$binaryType = '';
//							if ($annotation->hasArgument('binary')) {
//								$binaryType = 'binary';
//							} elseif ($annotation->hasArgument('set')) {
//								$binaryType = 'set';
//							}
//							if (!$binaryType) {
//								throw new Exception('No reference model, nor «binary» nor «set»: ' . $property);
//							}
//							$orderColumn = 'label';
//							$options['name'] = $property . '[]';
//							$values = [];
//							if ($binaryType === 'binary') {
//								for ($i = 1; $i <= 1048576 /* 2^20 */; $i *= 2) {
//									if (($entity->$property & $i) === $i) {
//										$values[$i] = true;
//									}
//								}
//							} elseif ($binaryType === 'set') {
//								foreach (explode(',', $entity->$property) as $v) {
//									if ($v) {
//										$values[$v] = true;
//									}
//								}
//							}
//							foreach ($annotation->getArgument($binaryType) as $v) {
//								$list[] = (object) ['label' => $this->translate->query($property . '_label_for_' . $v), 'id' => $v];
//							}
//						} else {
//							$options['name'] = $model['alias'] . '[]';
//							$values = [];
//							if ($entity->{$model['alias']}) {
//								foreach ($entity->{$model['alias']} as $m) {
//									$values[(int) $m->{$model['subalias']}->id] = true;
//								}
//							}
//							/* Current entries */
//							$conditions = '1 = 0';
//							$sql = $binds = [];
//							foreach ($values as $k => $v) {
//								$sql[] = ':id' . $k . ':';
//								$binds['id' . $k] = $k;
//							}
//							if (count($sql) > 0) {
//								$conditions .= ' OR id in (' . implode(', ', $sql) . ')';
//							}
//							$orderColumn = $this->annotations->get($model['model'])->getClassAnnotations()->get('Suggest')->getArgument(0);
//							$list = $model['model']::find(['conditions' => $conditions, 'bind' => $binds, 'order' => $orderColumn]);
//						}
//						if (isset($options['readonly'])) {
//							unset($options['readonly']);
//							$options['disabled'] = 'disabled';
//						}
//						if (count($list) === 0) {
//							$m = new stdClass();
//							$m->$orderColumn = '-';
//							$m->id = 0;
//							$list = [$m];
//						}
//						$options['data-default'] = '[]';
//						$options['data-count'] = count($list);
//						foreach ($list as $m) {
//							$options['data-label'] = $m->$orderColumn;
//							$options['value'] = $m->id;
//							$check = new Check($property . $m->id, $options);
//							if (isset($values[$m->id])) {
//								$check->setDefault($m->id);
//							}
//							$form->add($check);
//						}
//						break;
//					case 'image':
//					case 'images':
//						$options['defaultImage'] = Image::defaultImage;
//						$options['values'] = $entity->$property;
//						$options['model'] = $entityModel;
//						$options['types'] = $annotation->getNamedArgument('types');
//						$image = new Images($property, $options);
//						$form->add($image);
//						break;

					default:
						throw new Exception('Unknown format: ' . $annotation->getArgument(1));
				}
			}
		}
		//$this->view->setVar('suggests', $suggests);

		if (!$readOnly && $this->request->isPost()) {
			$this->view->disable();
			$return = ['reload' => $reload];
			try {
				if ($entity->save()) {
					foreach ($postSaveRelations as $arguments) {
						call_user_func_array([$entity, 'updateRelations'], $arguments);
					}

					$return['message'] = $this->translate->query('save_was_successful');
					$return['id'] = (int) $entity->id;
					/* New content, redirect on the new url */
					if ($return['reload'] === true) {
						$return['reload'] = Url::get('editAjax', ['controller' => $this->dispatcher->getControllerName(), 'action' => 'edit', 'id' => $entity->id]);
					}
				} else {
					$return = ['message' => ''];
					foreach ($entity->getMessages() as $message) {
						$return['message'] .= $message->getMessage() . ' ';
					}
				}
			} catch (\Exception $e) {
				$return = [];
				/* Duplicate key */
				if ($e->getCode() === '23000') {
					$return['message'] = 'Duplicate key';
				} elseif ($e->getCode() === 100001) {
					$return['message'] = $e->getMessage();
				}
				if (count($return) === 0) {
					trigger_error($e->getMessage());
					$return['message'] = 'Unknown error';
				}
			}
			if ($this->config->debug === true && ob_get_length()) {
				var_dump('response: ' . json_encode($return)); /* VAR_DUMP_ALLOWED */
				exit;
			}

			return $this->sendJson(isset($return['id']) ? 200 : 400, $return);
		}

		$this->view->setVar('form', json_encode($form));
	}

	private function addDataColumns(&$options, $columns) {
		$columns = (int) $columns;
		if ($columns === 2) {
			$options['columns'] = ['block' => 6, 'label' => 4, 'input' => 8];
		} elseif ($columns === 3) {
			$options['columns'] = ['block' => 4, 'label' => 6, 'input' => 6];
		} elseif ($columns === 32) { // 3 columns, only 2 are used
			$options['columns'] = ['block' => 8, 'label' => 3, 'input' => 9];
		} elseif ($columns === 4) { // 4 columns
			$options['columns'] = ['block' => 3, 'label' => 8, 'input' => 4];
		} else {
			$options['columns'] = ['block' => 12, 'label' => 2, 'input' => 10];
		}
	}

	protected function returnJson(int $code, array $result) {
		$this->view->disable();
		if ($this->config->environment !== 'dev' || ob_get_length() === 0) {
			$this->response->setContentType('application/json');
			$this->response->setHeader('X-Content-Type-Options', 'nosniff');
			$this->response->setHeader('Access-Control-Allow-Origin', '*');
		} else {
			$this->response->setContentType('text/html');
		}
		$this->response->setStatusCode($code);
		$this->response->setContent(json_encode($result));
		if (!$this->response->isSent()) {
			$this->response->send();
		}
		return true;
	}

}
