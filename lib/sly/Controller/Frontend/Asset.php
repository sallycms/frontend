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
	private static function setResponseHeaders(sly_Response $response, $etag = null, $type = null) {
		$cacheControl = sly_Core::config()->get('asset_cache/control/default', null);

		if ($cacheControl !== null) {
			foreach ($cacheControl as $key => $value) {
				$response->addCacheControlDirective($key, $value);
			}
		}
		else {
			// fallback to old config parameter
			$cacheControl = sly_Core::config()->get('ASSETS_CACHE_CONTROL', 'max-age=29030401');
			$response->setHeader('Cache-Control', $cacheControl);
		}

		if ($type) {
			$type         = explode('/', $type, 2);
			$cacheControl = sly_Core::config()->get('asset_cache/control/'.$type[0], null);
			
			if ($cacheControl !== null) {
				foreach ($cacheControl as $key => $value) {
					$response->addCacheControlDirective($key, $value);
				}
			}

			if (count($type) == 2) {
				$cacheControl = sly_Core::config()->get('asset_cache/control/'.$type[0].'_'.$type[1], null);

				if ($cacheControl !== null) {
					foreach ($cacheControl as $key => $value) {
						$response->addCacheControlDirective($key, $value);
					}
				}
			}
		}

		$now     = time();
		$expires = sly_Core::config()->get('asset_cache/expires', null);

		$response->setLastModified($now);

		if (is_int($expires)) {
			$response->setExpires($now + $expires);
		}

		if ($etag) {
			$response->setEtag($etag);
		}
	}

	public function indexAction() {
		$file = $this->getRequest()->get('sly_asset', 'string', '');

		if (mb_strlen($file) === 0) {
			return new sly_Response('', 400);
		}

		$service = sly_Service_Factory::getAssetService();

		// "clear" any errors that might came up when detecting the timezone
		if (error_get_last()) @trigger_error('', E_USER_NOTICE);

		try {
			$errorLevel   = error_reporting(0);
			$encoding     = $this->getCacheEncoding();
			$type         = sly_Util_Mime::getType($file);
			$etag         = sly_Core::config()->get('asset_cache/etag', false) && file_exists($file) ? md5_file($file) : null;

			if ($etag) {
				$ifNoneMatch = array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER) ? $_SERVER['HTTP_IF_NONE_MATCH'] : null;

				if ($ifNoneMatch && strpos($ifNoneMatch, '"'.$etag.'"') !== false) {
					$responseMatch = new sly_Response('Not modified', 304);
					self::setResponseHeaders($responseMatch, $etag, $type);
					return $responseMatch;
				}
			}

			$plainFile = $service->process($file, $encoding);

			$lastError = error_get_last();
			error_reporting($errorLevel);

			if ($plainFile === null) {
				return new sly_Response('', 404);
			}
			elseif ($plainFile instanceof sly_Response) {
				return $plainFile;
			}

			$response = new sly_Response_Stream($plainFile, 200);
			$response->setContentType($type, 'UTF-8');
			self::setResponseHeaders($response, $etag, $type);

			// if the file is protected, run the project specific checkpermission.php
			if ($service->isProtected($file)) {
				$allowAccess = false;
				$checkScript = SLY_DEVELOPFOLDER.'/checkpermission.php';

				if (file_exists($checkScript)) {
					include $checkScript;
				}

				if (!$allowAccess) {
					throw new sly_Authorisation_Exception('access forbidden');
				}

				if ($response->hasCacheControlDirective('public')) {
					$response->removeCacheControlDirective('public');
				}
				$response->addCacheControlDirective('private');
			}

			if (!empty($lastError) && mb_strlen($lastError['message']) > 0) {
				throw new sly_Exception($lastError['message'].' in '.$lastError['file'].' on line '.$lastError['line'].'.');
			}
		}
		catch (Exception $e) {
			$response = new sly_Response();

			if ($e instanceof sly_Authorisation_Exception) {
				$response->setStatusCode(403);
				$response->setHeader('Cache-Control', 'private');
			}
			else {
				$response->setStatusCode(500);
			}

			if (sly_Core::isDeveloperMode() || $e instanceof sly_Authorisation_Exception) {
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
		// first and second one are normal possibilities, the third one is for special cases like 1&1...
		$keys = array('HTTP_ENCODING_CACHEDIR', 'REDIRECT_HTTP_ENCODING_CACHEDIR', 'REDIRECT_REDIRECT_HTTP_ENCODING_CACHEDIR');
		$enc  = 'plain';

		foreach ($keys as $key) {
			if (isset($_SERVER[$key])) {
				$enc = $_SERVER[$key];
				break;
			}
		}

		$enc = strtolower(trim($enc, '/'));

		return in_array($enc, array('gzip', 'deflate', 'plain')) ? $enc : 'plain';
	}
}
