<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_Asset extends sly_Controller_Frontend_Base {
	public function indexAction() {
		$file = sly_get('sly_asset', 'string');

		$response = new sly_Response();

		if (mb_strlen($file) === 0) {
			$response->setStatusCode(400);
		}else {
			$service = sly_Service_Factory::getAssetService();
			try {
				$errorLevel   = error_reporting();
				$enc          = $this->getPreferredClientEncoding();
				$type         = sly_Util_Mime::getType($file);
				$content      = $service->process($file, $enc);

				$cacheControl = sly_Core::config()->get('ASSETS_CACHE_CONTROL', 'max-age=29030401');

				if($content === false) {
					$response->setStatusCode(404);
					return $response;
				}

				switch ($enc) {
					case 'plain':
						break;
					case 'deflate':
					case 'gzip':
						$response->setHeader('Content-Encoding', $enc);
						break;
				}

				$response->setStatusCode(200);
				$response->setContent($content);
				$response->setContentType($type, 'UTF-8');
				$response->setHeader('Cache-Control', $cacheControl);
				$response->setHeader('Last-Modified', date('r', time()));
				$lastError = error_get_last();
				error_reporting($errorLevel);
				if(!empty($lastError)) throw new sly_Exception($lastError['message'].' In File: '.$lastError['file'].' on Line: '.$lastError['line']);
			}catch(Exception $e) {
				if($e instanceof sly_Authorisation_Exception) {
					$response->setStatusCode(403);
				}else {
					$response->setStatusCode(500);
				}
				$response->setContent($e->getMessage());
				$response->setContentType('text/html', 'UTF-8');
			}
		}
		// process the file
		return $response;
	}

	/**
	 * @return string
	 */
	private function getPreferredClientEncoding() {
		static $enc;

		if (!isset($enc)) {
			$enc = false;
			$e   = trim(sly_get('encoding', 'string'), '/');

			if (in_array($e, array('plain', 'gzip', 'deflate'))) {
				$enc = $e;
			}
			elseif (!empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
				if (stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) $enc = 'gzip';
				elseif (stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false) $enc = 'deflate';
			}
		}

		return $enc;
	}
}
