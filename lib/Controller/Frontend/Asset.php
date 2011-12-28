<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_Asset extends sly_Controller_Frontend_Base {
	public function indexAction() {
		$file = sly_get('sly_asset', 'string');

		if (mb_strlen($file) === 0) {
			header('HTTP/1.0 400 Bad Request');
			die;
		}

		// process the file
		sly_Service_Factory::getAssetService()->process($file);
	}
}
