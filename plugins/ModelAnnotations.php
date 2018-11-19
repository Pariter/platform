<?php

namespace Pariter\Plugins;

use Phalcon\Events\Event;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\User\Plugin;

class ModelAnnotations extends Plugin {

	/**
	 * This is called after initialize the model
	 *
	 * @param Phalcon\Events\Event $event
	 */
	public function afterInitialize(Event $event, ModelsManager $manager, $model) {
		$class = get_class($model);

		/**
		 * Read the annotations in the class' docblock
		 */
		$annotations = $this->annotations->get($class)->getClassAnnotations();
		if ($annotations) {

			/**
			 * Traverse the annotations
			 */
			foreach ($annotations as $annotation) {
				switch ($annotation->getName()) {

					/**
					 * Initializes the model's source
					 */
					case 'Source':
						$arguments = $annotation->getArguments();
						$manager->setModelSource($model, $arguments[0]);
						break;

					/**
					 * Initializes Has-Many relations
					 */
					case 'HasOne':
						$arguments = $annotation->getArguments();
						if (isset($arguments[3])) {
							$manager->addHasone($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2], $arguments[3]);
						} else {
							$manager->addHasOne($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2]);
						}
						break;

					case 'HasMany':
						$arguments = $annotation->getArguments();
						if (isset($arguments[3])) {
							$manager->addHasMany($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2], $arguments[3]);
						} else {
							$manager->addHasMany($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2]);
						}
						break;

					/**
					 * Initializes Has-Many-To-Many relations
					 */
					case 'HasManyToMany':
						$arguments = $annotation->getArguments();
						if (isset($arguments[6])) {
							$manager->addHasManyToMany($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2], $arguments[3], 'Locator\\Models\\' . $arguments[4], $arguments[5], $arguments[6]);
						} else {
							$manager->addHasMany($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2], $arguments[3], 'Locator\\Models\\' . $arguments[4], $arguments[5]);
						}
						break;

					/**
					 * Initializes Has-Many relations
					 */
					case 'BelongsTo':
						$arguments = $annotation->getArguments();
						if (isset($arguments[3])) {
							$manager->addBelongsTo($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2], $arguments[3]);
						} else {
							$manager->addBelongsTo($model, $arguments[0], 'Locator\\Models\\' . $arguments[1], $arguments[2]);
						}
						break;
				}
			}
		}
	}

}
