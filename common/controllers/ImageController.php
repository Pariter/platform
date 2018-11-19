<?php

namespace Pariter\Common\Controllers;

use Phalcon\Exception;
use Phalcon\Mvc\Dispatcher;
use Pariter\Models\Image;

trait ImageController {

	public function initView() {
		$format = $this->dispatcher->getParam('format', 'string');
		$id = (int) $this->dispatcher->getParam('id', 'int');
		$image = Image::getResizedImage($id, $format);
		if (!$image) {
			throw new Exception('Image not found', Dispatcher::EXCEPTION_HANDLER_NOT_FOUND);
		}
		if ($this->config->environment === 'dev' && ob_get_length() !== 0) {
			throw new Exception('Error shown before content');
		}

		$this->view->disable();
		$this->response->setContent($image['content']);
		$this->response->setContentType('image/jpeg');
		$this->response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $image['timestamp']) . ' GMT');
		$this->response->send();

		return true;
	}

}
