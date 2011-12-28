<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class sly_Controller_Frontend_Base extends sly_Controller_Base {
	protected function getViewFolder() {
		return SLY_DEVELOPFOLDER.'/views/';
	}

	public function checkPermission() {
		return true;
	}
}
