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
		$file     = sly_get('sly_asset', 'string');
		$timezone = sly_Core::config()->get('SETUP') ? @date_default_timezone_get() : sly_Core::getTimezone();

		// fix badly configured servers where the get function doesn't even return a guessed default timezone
		if (empty($timezone)) {
			$timezone = sly_Core::getTimezone();
		}

		// set the determined timezone
		date_default_timezone_set($timezone);

		if (mb_strlen($file) === 0) {
			return new sly_Response('', 400);
		}

		$service = sly_Service_Factory::getAssetService();

		// "clear" any errors that might came up when detecting the timezone
		if (error_get_last()) @trigger_error('', E_USER_NOTICE);

		try {
			$errorLevel   = 0; // error_reporting(0);
			$encoding     = $this->getCacheEncoding();
			$type         = sly_Util_Mime::getType($file);
			$plainFile    = $service->process($file, $encoding);
			$cacheControl = sly_Core::config()->get('ASSETS_CACHE_CONTROL', 'max-age=29030401');

			if ($plainFile === null) {
				return new sly_Response('', 404);
			}
			elseif ($plainFile instanceof sly_Response) {
				return $plainFile;
			}

			$response = new sly_Response_Stream($plainFile, 200);
			$response->setContentType($type, 'UTF-8');
			$response->setHeader('Cache-Control', $cacheControl);
			$response->setHeader('Last-Modified', date('r', time()));

			$lastError = error_get_last();
			error_reporting($errorLevel);

			if (!empty($lastError) && mb_strlen($lastError['message']) > 0) {
				throw new sly_Exception($lastError['message'].' in '.$lastError['file'].' on line '.$lastError['line'].'.');
			}
		}
		catch (Exception $e) {
			$response = new sly_Response();

			if ($e instanceof sly_Authorisation_Exception) {
				$response->setStatusCode(403);
			}
			else {
				$response->setStatusCode(500);
			}

			if (sly_Core::isDeveloperMode()) {
				$response->setContent($e->getMessage());
			}
			else {
				$response->setContent('Error while processing asset.');
			}

			$response->setExpires(time()-24*3600);
			$response->setContentType('text/plain', 'UTF-8');
		}

		return $response;
	}

	/**
	 * get the encoding to use for caching
	 *
	 * The encoding (gzip, deflate or plain) can differ from the client's
	 * Accept-Encoding header and is determined by the asset cache's .htaccess
	 * file. If no mod_headers is available, the encoding is set to plain to make
	 * the service put the file at the correct location (so the rewrite rules can
	 * find it for following requests). However, the client can and probably will
	 * receive a gzip'ed response, since the contents we send him is only
	 * determined by the Accept-Encoding header.
	 *
	 * So this method returns the encoding that should be used for caching, *not*
	 * for sending the content to the client. The client encoding is set in
	 * sly_Response_Stream via output buffers.
	 *
	 * @return string  either 'plain', 'gzip' or 'deflate'
	 */
	private function getCacheEncoding() {
		$enc = isset($_SERVER['HTTP_ENCODING_CACHEDIR']) ? $_SERVER['HTTP_ENCODING_CACHEDIR'] : (isset($_SERVER['REDIRECT_HTTP_ENCODING_CACHEDIR']) ? $_SERVER['REDIRECT_HTTP_ENCODING_CACHEDIR'] : 'plain');
		$enc = strtolower(trim($enc, '/'));

		return in_array($enc, array('gzip', 'deflate', 'plain')) ? $enc : 'plain';
	}
}
