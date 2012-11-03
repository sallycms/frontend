<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Frontend extends sly_App_Base {
	const CONTROLLER_PARAM = 'slycontroller';  ///< string  the request param that contains the page
	const ACTION_PARAM     = 'slyaction';      ///< string  the request param that contains the action

	public function isBackend() {
		return false;
	}

	public function initialize() {
		$container = $this->getContainer();
		$request   = $container->getRequest();
		$isSetup   = sly_Core::isSetup();

		// Setup?
		if (!$request->get->has('sly_asset') && $isSetup) {
			$target = $request->getBaseUrl(true).'/backend/';
			$text   = 'Bitte führe das <a href="'.sly_html($target).'">Setup</a> aus, um SallyCMS zu nutzen.';

			sly_Util_HTTP::tempRedirect($target, array(), $text);
		}

		// set timezone
		$this->setDefaultTimezone($isSetup);

		// Load the base i18n database. This database contains translations for
		// the *backend* locales, but since it only contains error messages that
		// are used before any frontend language detection is done (-> article
		// controller), this is OK.

		$i18n = new sly_I18N(sly_Core::getDefaultLocale(), SLY_SALLYFOLDER.'/frontend/lang', false);
		$container->setI18N($i18n);

		parent::initialize();
	}

	public function run() {
		try {
			// resolve URL and find controller
			$this->performRouting();

			// notify the addOns
			$this->notifySystemOfController();

			// do it, baby
			$dispatcher = $this->getDispatcher();
			$response   = $dispatcher->dispatch($this->controller, $this->action);
		}
		catch (sly_Controller_Exception $e) {
			$response = new sly_Response('', 404);
		}
		catch (Exception $e) {
			$response = new sly_Response('Internal Error', 500);
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

	protected function performRouting() {
		// create new router and hand it to all addOns
		$container = $this->getContainer();
		$router    = $this->prepareRouter($container);
		$request   = $container->getRequest();

		// use the router to prepare the request and setup proper query string values
		$router->match($request);

		$controller = $request->request(self::CONTROLLER_PARAM, 'string', 'article');
		$action     = $request->request(self::ACTION_PARAM, 'string', 'index');

		// test the controller name
		$dispatcher = $this->getDispatcher();
		$className  = $dispatcher->getControllerClass($controller);

		// boom
		$dispatcher->getController($className);

		// let the core know where we are
		$this->controller = $controller;
		$this->action     = $action;
	}

	protected function prepareRouter(sly_Container $container) {
		// find controller
		$router = new sly_Router_Base();

		// let addOns extend our router rule set
		$router = $container->getDispatcher()->filter('SLY_FRONTEND_ROUTER', $router, array('app' => $this));

		if (!($router instanceof sly_Router_Interface)) {
			throw new LogicException('Expected a sly_Router_Interface as the result from SLY_FRONTEND_ROUTER.');
		}

		return $router;
	}
}
