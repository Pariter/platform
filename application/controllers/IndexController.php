<?php

namespace Pariter\Application\Controllers;

class IndexController extends Controller {

	/**
	 * Parameters from Ionic (fonts)
	 * @GetParameters({v})
	 *
	 * Cache only in Varnish
	 * @CacheControl(surrogate=86400, last=true)
	 */
	public function resourceAction() {
		$directory = str_replace('.', '', $this->dispatcher->getParam('directory'));
		$this->dispatcher->setParam('directory', $directory === 'root' ? $this->config->application->htdocs : $this->config->application->htdocs . 'assets/' . $directory . '/');
		$this->dispatcher->forward(['controller' => 'resource', 'action' => 'view']);
	}

}
