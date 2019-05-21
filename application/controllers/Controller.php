<?php

namespace Pariter\Application\Controllers;

use Phalcon\Mvc\Controller as MainController;

class Controller extends MainController {

	public function initialize() {
		$this->view->setVar('_config', $this->config);
	}

	protected function sendJson($code, $json) {
		$this->view->disable();
		if (ob_get_length() === 0) {
			$this->response->setContentType('application/json');
			$this->response->setHeader('Access-Control-Allow-Origin', '*');
		}
		$this->response->setStatusCode($code);
		$this->response->setContent(json_encode($json));
		if (!$this->response->isSent()) {
			$this->response->send();
		}
		return null;
	}

}
