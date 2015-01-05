<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Frontend extends sly_App_Base {
	const CONTROLLER_PARAM = 'slycontroller';  ///< string  the request param that contains the page
	const ACTION_PARAM     = 'slyaction';      ///< string  the request param that contains the action

	protected $dispatcher;

	public function isBackend() {
		return false;
	}

	public function initialize() {
		$container = $this->getContainer();
		$request   = $container->getRequest();
		$config    = $container->getConfig();

		// init basic error handling
		$container->getErrorHandler()->init();

		if ($config->get('setup', true) === true) {
			$target = $request->getBaseUrl(true).'/setup/';
			$text   = 'Bitte führe das <a href="'.sly_html($target).'">Setup</a> aus, um SallyCMS zu nutzen.';

			sly_Util_HTTP::tempRedirect($target, array(), $text);
		}

		// set timezone
		$this->setDefaultTimezone();

		// Load the base i18n database. This database contains translations for
		// the *backend* locales, but since it only contains error messages that
		// are used before any frontend language detection is done (-> article
		// controller), this is OK.

		if ($container->has('sly-i18n')) {
			$i18n = $container->getI18N();
		}
		else {
			$i18n = new sly_I18N($config->get('default_locale'), null, false);
			$container->setI18N($i18n);
		}

		$i18n->appendFile(SLY_SALLYFOLDER.'/frontend/lang');

		parent::initialize();
	}

	public function run() {
		try {
			// resolve URL and find controller
			$container = $this->getContainer();
			$this->performRouting($container->getRequest());

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

	public function getCurrentControllerName() {
		return $this->controller;
	}

	public function getCurrentAction() {
		return $this->action;
	}

	protected function getControllerFromRequest(sly_Request $request) {
		return $request->request(self::CONTROLLER_PARAM, 'string', 'article');
	}

	protected function getActionFromRequest(sly_Request $request) {
		return $request->request(self::ACTION_PARAM, 'string', 'index');
	}

	protected function prepareRouter(sly_Container $container) {
		// use the basic router
		$router = new sly_Router_Base();

		// let addOns extend our router rule set
		return $container->getDispatcher()->filter('SLY_FRONTEND_ROUTER', $router, array('app' => $this));
	}

	/**
	 * get request dispatcher
	 *
	 * @return sly_Dispatcher
	 */
	protected function getDispatcher() {
		if ($this->dispatcher === null) {
			$this->dispatcher = new sly_Dispatcher_Frontend($this->getContainer());
		}

		return $this->dispatcher;
	}
}
