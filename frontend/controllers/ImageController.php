<?php

namespace Pariter\Frontend\Controllers;

use Pariter\Common\Controllers\ImageController as CommonIC;

class ImageController extends Controller {

	use CommonIC;

	/**
	 * @CacheControl(surrogate=86400, browser=2592000)
	 */
	public function viewAction() {
		return $this->initView();
	}

}
