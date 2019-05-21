<?php

namespace Pariter\Models;

use Dugwood\Core\Cache;
use Dugwood\Core\Command;
use Dugwood\Core\Image as CoreImage;
use Pariter\Library\File as FileLib;
use Pariter\Library\Url;
use Phalcon\DI;
use Phalcon\Exception;

/**
 * @Source('IMAGES')
 * @ApiDefinition("Images")
 * @Suggest(text)
 * @Varnish("image-ID")
 */
class Image extends Model {

	const cache = Cache::long;
	const key = 'image';
	const keys = 'images';

	/** @var $imageTypes */
	public static $imageTypes = [
		'ordered' => 'multiple',
	];

	/**
	 * @var integer $id
	 *
	 * @Primary
	 * @Identity
	 * @Column(column="IMG_ID", type="integer", nullable = false)
	 */
	public $id;

	/**
	 * @var integer $width
	 *
	 * @Column(column="IMG_WIDTH", type="integer", nullable=false)
	 * @List(2)
	 */
	public $width = 0;

	/**
	 * @var integer $height
	 *
	 * @Column(column="IMG_HEIGHT", type="integer", nullable=false)
	 * @List(3)
	 */
	public $height = 0;

	/**
	 * @var integer $length
	 *
	 * @Column(column="IMG_LENGTH", type="integer", nullable=false)
	 */
	public $length = 0;

	/**
	 * @var string $checksum
	 *
	 * @Column(column="IMG_CHECKSUM", type="string", nullable=false)
	 */
	public $checksum = '';

	/**
	 * @var string $url
	 *
	 * @Column(column="IMG_URL", type="string")
	 */
	public $url = '';

	/**
	 * @var integer $soft
	 *
	 * @Column(column="IMG_SOFT", type="integer")
	 */
	public $soft = 0;

	/**
	 * @var string $text
	 *
	 * @Column(column="IMG_TEXT", type="string")
	 */
	public $text = '';

	static public function saveToDisk($file, $url = '', $synchronize = true) {
		if (!is_file($file)) {
			throw new Exception('File not found: ' . $file);
		}
		$sizes = getimagesize($file);
		$length = filesize($file);
		$checksum = self::getChecksum($file);
		if (!$sizes || !$length || !$checksum || $sizes['mime'] !== 'image/jpeg') {
			throw new Exception('Size, weight, signature or format invalid: ' . $file, 555);
		}
		$width = $sizes[0];
		$height = $sizes[1];
		/* Same settings, same image */
		$image = self::findFirst(array(
					'conditions' => 'width = :APR0: AND height = :APR1: AND length = :APR2: AND checksum = :APR3:',
					'bind' => array('APR0' => $width, 'APR1' => $height, 'APR2' => $length, 'APR3' => $checksum)));
		if ($image) {
			if (!$image->url && $url) {
				$image->url = $url;
				$image->save();
			}
			return $image;
		}
		$image = new self();
		$image->width = $width;
		$image->height = $height;
		$image->length = $length;
		$image->checksum = $checksum;
		$image->url = $url;
		if (!$image->save()) {
			foreach ($image->getMessages() as $m) {
				trigger_error($m->getMessage(), E_USER_WARNING);
			}
			return null;
		}
		$path = self::getPath($image->id);
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0755, true);
		}
		if (copy($file, $path) !== true) {
			$image->delete();
			return null;
		}
		if ($synchronize === true) {
			Command::synchronize('static', 'pariter/images');
		}
		return $image;
	}

	static public function getChecksum($file) {
		$contents = file_get_contents($file);
		return md5($contents) . '-' . crc32($contents) . '-' . sha1($contents) . '-' . strlen($contents);
	}

	static public function getPath($id) {
		return DI::getDefault()->getConfig()->images . substr('0' . $id, -2, 1) . '/' . substr($id, -1, 1) . '/' . $id . '.jpg';
	}

	static public function getTag($imageId, $parameters = array()) {
		if (!($image = self::findFirst((int) $imageId))) {
			return '';
		}
		$alt = $image->text;
		$format = isset($parameters['format']) ? $parameters['format'] : '';
		$class = isset($parameters['class']) ? $parameters['class'] : '';
		$absolute = !empty($parameters['absolute']);
		$anchor = !empty($parameters['anchor']);

		if (!($imageFormat = ImageFormat::findFirstByKeyword($format))) {
			trigger_error('Format inconnu : ' . $format, E_USER_NOTICE);
			return '';
		}
		if (!empty($parameters['enclosure'])) {
			if (empty($imageId)) {
				return '';
			}
			$filesize = filesize(self::getPath($imageId));
			return '<enclosure type="image/jpeg" url="' . Url::get('image', ['id' => $imageId, 'format' => $format, 'absolute' => $absolute]) . '" length="' . $filesize . '"/>';
		}
		$src = Url::get('image', ['id' => $imageId, 'format' => $format]);
		$return = '';
		if ($anchor === true) {
			$return .= '<a href="' . $src . '"';
		} else {
			$return .= '<img src="' . $src . '"';
			$return .= ' width="' . $imageFormat->width . '"';
			$return .= ' height="' . $imageFormat->height . '"';
			$return .= ' alt="' . $alt . '"';
		}
		if ($class) {
			$return .= ' class="' . trim($class) . '"';
		}
		if ($anchor === true) {
			$return .= '>';
		} else {
			$return .= '/>';
		}
		return $return;
	}

	static public function getResizedImage($id, $format) {
		$config = DI::getDefault()->getConfig();
		$imageFormat = ImageFormat::findFirstByKeyword($format);
		if (!$imageFormat) {
			return false;
		}
		$file = self::getPath($id);
		if (!file_exists($file) && ($config->environment === 'prod' || FileLib::downloadFile($file) !== true)) {
			return false;
		}

		$parameters = [
			'quality' => (int) $imageFormat->quality
		];

		$coreImage = CoreImage::getInstance();
		return $coreImage->getResized($file, $imageFormat->type, $imageFormat->width, $imageFormat->height, $parameters);
	}

}
