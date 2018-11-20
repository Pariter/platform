<?php

namespace Pariter\Models;

use Dugwood\Core\Cache;
use Phalcon\DI;

/**
 * @Source("TRANSLATIONS")
 */
class Translation extends Model {

	const cache = Cache::long;

	/**
	 * @var integer $id
	 *
	 * @Primary
	 * @Identity
	 * @Column(column="TSL_ID", type="integer")
	 */
	public $id;

	/**
	 * @var string $module
	 *
	 * @Column(column="TSL_MODULE", type="string", nullable=false)
	 * @Unique(module_template_keyword)
	 */
	public $module;

	/**
	 * @var string $template
	 *
	 * @Column(column="TSL_TEMPLATE", type="string", nullable=false)
	 * @Unique(module_template_keyword)
	 */
	public $template;

	/**
	 * @var string $keyword
	 *
	 * @Column(column="TSL_KEYWORD", type="string", nullable=false)
	 * @Unique(module_template_keyword)
	 */
	public $keyword;

	/**
	 * @var string $frText
	 *
	 * @Column(column="TSL_FR_TEXT", type="string")
	 */
	public $frText = '';

	/**
	 * @var string $enText
	 *
	 * @Column(column="TSL_EN_TEXT", type="string")
	 */
	public $enText = '';

	public function afterSave() {
		$cache = $this->getDI()->getCache();
		$cache->delete('pariter-messages-MATCHALL');
		return parent::afterSave();
	}

	static public function getAll($module, $template = '') {
		if ($template) {
			return self::find(['conditions' => '[module] = :APR0: and [template] = :APR1:', 'bind' => ['APR0' => $module, 'APR1' => $template]]);
		}
		return self::find(['conditions' => '[module] = :APR0:', 'bind' => ['APR0' => $module]]);
	}

	static public function get($module, $template, $keyword, $language) {
		static $stored = [];
		$key = $module . '-' . $template . '-' . $keyword . '-';
		if (!isset($stored[$key . $language])) {
			$translation = self::findFirst(['[keyword] = :APR0: AND [module] = :APR1: AND [template] = :APR2:', 'bind' => ['APR0' => $keyword, 'APR1' => $module, 'APR2' => $template]]);
			foreach (DI::getDefault()->getConfig()->languages as $l => $locale) {
				$stored[$key . $l] = $translation->{$l . 'Text'} ?? $keyword;
			}
		}
		return $stored[$key . $language];
	}

}
