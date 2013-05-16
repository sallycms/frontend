<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use Gaufrette\Util\Path;

class sly_Controller_Frontend_Asset extends sly_Controller_Frontend_Base {
	public function indexAction() {
		// retrieve filename

		$request   = $this->getRequest();
		$container = $this->getContainer();
		$config    = $container->getConfig();
		$file      = $request->get('sly_asset', 'string', '');

		if (mb_strlen($file) === 0) {
			return new sly_Response('', 400);
		}

		try {
			// check if the filetype may be accessed at all
			// and check if the file is inside an allowed path

			$service = $container->getAssetService();
			$configs = $config->get('frontend/assets', array(
				'etag'          => false,
				'cache-control' => array(),
				'expires'       => null,
				'directories'   => array()
			));

			$this->checkForBlockedExtensions($config, $file);
			$this->checkForAllowedPath($configs['directories'], $file);

			$isProtected = $this->checkFilePermission($service, $file);

			// "clear" any errors that might came up when detecting the timezone

			if (error_get_last()) @trigger_error('', E_USER_NOTICE);
			$errorLevel = error_reporting(0);

			// check the ETag

			$type = sly_Util_File::getMimetype($file);
			$etag = $configs['etag'] && file_exists($file) ? md5_file($file) : null;

			if ($etag) {
				$etags = $request->getETags();

				if (in_array('"'.$etag.'"', $etags)) {
					$response = new sly_Response('Not modified', 304);
					$this->setResponseHeaders($response, $etag, $type);

					return $response;
				}
			}

			// process the file

			$resultFile = $service->process($file);
			$lastError  = error_get_last();

			error_reporting($errorLevel);

			if (!empty($lastError) && mb_strlen($lastError['message']) > 0) {
				throw new sly_Exception($lastError['message'].' in '.$lastError['file'].' on line '.$lastError['line'].'.');
			}

			// prepare the response

			$response = new sly_Response_Stream($resultFile, 200);
			$response->setContentType($type, 'UTF-8');

			$this->setResponseHeaders($response, $etag, $type);

			// if the file is protected, mark the response as private

			if ($isProtected) {
				if ($response->hasCacheControlDirective('public')) {
					$response->removeCacheControlDirective('public');
				}

				$response->addCacheControlDirective('private');
			}
		}
		catch (Exception $e) {
			$response = new sly_Response();

			if ($e instanceof sly_Authorisation_Exception) {
				$response->setStatusCode(403);
				$response->addCacheControlDirective('private');
			}
			else {
				$response->setStatusCode(500);
			}

			if ($container->get('sly-environment') !== 'dev' || $e instanceof sly_Authorisation_Exception) {
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

	protected function checkForBlockedExtensions(sly_Configuration $config, $file) {
		$blocked = $config->get('blocked_extensions');

		foreach ($blocked as $ext) {
			if (sly_Util_String::endsWith($file, $ext)) {
				throw new sly_Authorisation_Exception('Forbidden');
			}
		}
	}

	protected function checkForAllowedPath(array $allowed, $file) {
		$normalized = Path::normalize($file);
		$ok         = strpos($normalized, '/') === false; // allow files in root directory (favicon)

		if (!$ok) {
			foreach ($allowed as $path) {
				if (sly_Util_String::startsWith($normalized, Path::normalize($path))) {
					$ok = true;
					break;
				}
			}

			if (!$ok) {
				throw new sly_Authorisation_Exception('Forbidden');
			}
		}
	}

	protected function checkFilePermission(sly_Asset_Service $service, $file) {
		$isProtected = $service->isProtected($file);

		if ($isProtected && !$service->checkPermission($file)) {
			throw new sly_Authorisation_Exception('access forbidden');
		}

		return $isProtected;
	}

	protected function setResponseHeaders(array $config, sly_Response $response, $etag = null, $type = null) {
		$cacheControl = $config['cache-control'];

		$this->addCacheControlDirective($cacheControl, 'default', $response);

		// add type-specific cache-control headers

		if ($type) {
			$type = explode('/', $type, 2);

			$this->addCacheControlDirective($cacheControl, $type[0], $response);

			if (count($type) == 2) {
				$this->addCacheControlDirective($cacheControl, $type[0].'_'.$type[1], $response);
			}
		}

		$now     = time();
		$expires = $config['expires'];

		$response->setLastModified($now);

		if (is_int($expires)) {
			$response->setExpires($now + $expires);
		}

		if ($etag) {
			$response->setEtag($etag);
		}
	}

	protected function addCacheControlDirective(array $cacheControl, $key, sly_Response $response) {
		if (isset($cacheControl[$key])) {
			foreach ($cacheControl[$key] as $k => $value) {
				$response->addCacheControlDirective($k, $value);
			}
		}
	}
}
