<?php

namespace Pariter\Frontend\Controllers;

use Phalcon\Mvc\Controller as MainController;

class Controller extends MainController {

	public function initialize() {
		$this->view->setVar('_config', $this->config);
	}

}
