<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Frontend extends sly_App_Base implements sly_App_Interface {
	const CONTROLLER_PARAM = 'slycontroller';  ///< string  the request param that contains the page
	const ACTION_PARAM     = 'slyaction';      ///< string  the request param that contains the action

	public function initialize() {
		$config  = sly_Core::config();
		$isSetup = $config->get('SETUP');

		// Setup?
		if (!isset($_GET['sly_asset']) && $isSetup) {
			$target = sly_Util_HTTP::getBaseUrl(true).'/backend/';
			header('Location: '.$target);
			exit('Bitte führe das <a href="'.sly_html($target).'">Setup</a> aus, um SallyCMS zu nutzen.');
		}

		// Load the base i18n database. This database contains translations for
		// the *backend* locales, but since it only contains error messages that
		// are used before any frontend language detection is done (-> article
		// controller), this is OK.

		$i18n = new sly_I18N(sly_Core::getDefaultLocale(), SLY_SALLYFOLDER.'/frontend/lang', false);
		sly_Core::setI18N($i18n);

		parent::initialize();
	}

	public function run() {
		$config  = sly_Core::config();
		$isSetup = $config->get('SETUP');

		// get the most probably already prepared response object
		// (addOns had a shot at modifying it)
		$response = sly_Core::getResponse();

		// find controller
		$router = new sly_Router_Base(array(
			'/sally/:controller/:action',
			'/sally/:controller'
		));

		// if no special controller was found, we use the article controller
		if (!$router->hasMatch()) {
			$controller = sly_request(self::CONTROLLER_PARAM, 'string', 'article');
			$action     = sly_request(self::ACTION_PARAM, 'string', 'index');
		}
		else {
			$controller = $router->get('controller');
			$action     = $router->get('action', 'index');
		}

		// let the core know where we are
		$this->controller = $controller;
		$this->action     = $action;

		// notify the addOns
		$this->notifySystemOfController();

		// do it, baby
		$content  = $this->dispatch($controller, $action);
		$response = sly_Core::getResponse(); // re-fetch the current global response

		// if we got a string, wrap it in the layout and then in the response object
		if (is_string($content)) {
			$this->handleStringResponse($response, $content, 'frontend');
		}

		// if we got a response, use that one
		elseif ($content instanceof sly_Response) {
			$response = $content;
		}

		// everything else is a bug
		else {
			throw new LogicException('Controllers must return either content as a string or a Response, got '.gettype($content).'.');
		}

		// send the response :)
		$response->send();
	}

	public function getControllerClassPrefix() {
		return 'sly_Controller_Frontend';
	}

	public function getCurrentControllerName() {
		return $this->controller;
	}

	public function getCurrentAction() {
		return $this->action;
	}

	protected function handleControllerError(Exception $e, $controller, $action) {
		// throw away all content (including notices and warnings)
		while (ob_get_level()) ob_end_clean();

		// call the system error handler
		$handler = sly_Core::getErrorHandler();
		$handler->handleException($e); // dies away (does not use sly_Response)
	}
}
