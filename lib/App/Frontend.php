<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Frontend {
	const CONTROLLER_PARAM = 'slycontroller';

	public function initialize() {
		$config  = sly_Core::config();
		$isSetup = $config->get('SETUP');

		// Setup?
		if (!isset($_GET['sly_asset']) && $isSetup) {
			header('Location: backend/index.php');
			exit('Bitte führe das <a href="backend/index.php">Setup</a> aus, um SallyCMS zu nutzen.');
		}

		// init i18n (TODO: This makes no sense... but addOns require the i18n object to be present)
		sly_Core::setI18N(new sly_I18N(sly_Core::getDefaultLocale(), SLY_SALLYFOLDER.'/backend/lang'));

		// instantiate asset service before addOns are loaded to make sure
		// the Scaffold CSS processing is first in the line for CSS files
		sly_Service_Factory::getAssetService();

		// include AddOns
		sly_Core::loadAddons();

		// register listeners
		sly_Core::registerListeners();

		// refresh cache
		if (sly_Core::isDeveloperMode()) $this->syncDevelopFiles($isSetup);
	}

	public function run() {
		$config  = sly_Core::config();
		$isSetup = $config->get('SETUP');

		// get the most probably already prepared response object
		// (addOns had a shot at modifying it)
		$response = sly_Core::getResponse();

		// get page and action from the current request
		$page   = $this->controller === null ? $this->findPage() : $this->controller;
		$action = $this->getAction('index');

		// let the core know where we are
		self::$currentPage = $page;

		// notify the addOns
		sly_Core::dispatcher()->notify('PAGE_CHECKED', $page, compact('action'));

		// do it, baby
		$content  = $this->dispatch($page, $action);
		$response = sly_Core::getResponse(); // re-fetch the current global response

		// if we got a string, wrap it in the layout and then in the response object
		if (is_string($content)) {
			$layout->setContent($content);
			$payload = $layout->render();
			$response->setContent($payload);
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

	public function dispatch($page, $action) {
		$pageResponse = $this->runPage($page, $action);

		// register the new response, if the controller returned one
		if ($pageResponse instanceof sly_Response) {
			sly_Core::setResponse($pageResponse);
		}

		// if the controller returned another action, execute it
		if ($pageResponse instanceof sly_Response_Action) {
			$pageResponse = $pageResponse->execute($this);
		}

		return $pageResponse;
	}

	public function runPage($controller, $action) {
		$response = sly_Core::getResponse();

		try {
			// prepare controller
			$method = $action; // TODO: make this $action.'Action'

			if (!($controller instanceof sly_Controller_Backend)) {
				$className  = $this->getControllerClass($controller);
				$controller = new $className();
			}

			if (!($controller instanceof sly_Controller_Backend)) {
				throw new sly_Controller_Exception(t('does_not_implement', $className, 'sly_Controller_Backend'));
			}

			if (!method_exists($controller, $method)) {
				throw new sly_Controller_Exception(t('unknown_action', $method, $className), 404);
			}

			ob_start();

			// init the controller
			$r = $controller->init($method, $response);
			if ($r instanceof sly_Response) { ob_end_clean(); return $r; }

			// run the action method
			$r = $controller->$method($response);
			if ($r instanceof sly_Response) { ob_end_clean(); return $r; }

			// and tear it down
			$r = $controller->teardown($method, $response);
			if ($r instanceof sly_Response) { ob_end_clean(); return $r; }

			// collect output
			return ob_get_clean();
		}
		catch (Exception $e) {
			// throw away all content (including notices and warnings)
			sly_Core::getLayout()->closeAllBuffers();

			// manually create the error controller to pass the exception
			$controller = new sly_Controller_Error($e);

			// forward to the error page
			return new sly_Response_Forward($controller, 'index');
		}
	}

	protected function syncDevelopFiles($isSetup) {
		if (!$isSetup) {
			sly_Service_Factory::getTemplateService()->refresh();
			sly_Service_Factory::getModuleService()->refresh();
		}

		sly_Service_Factory::getAssetService()->validateCache();
	}

	/**
	 * Get the page param
	 *
	 * Reads the page param from the $_REQUEST array and returns it.
	 *
	 * @param  string $default  default value if param is not present
	 * @return string           the page param
	 */
	public function getPage($default = '') {
		return strtolower(sly_request(self::PAGEPARAM, 'string', $default));
	}

	/**
	 * Get the action param
	 *
	 * Reads the action param from the $_REQUEST array and returns it.
	 *
	 * @param  string $default  default value if param is not present
	 * @return string           the action param
	 */
	public function getAction($default = '') {
		return strtolower(sly_request(self::ACTIONPARAM, 'string', $default));
	}

	/**
	 * Get the currently active page
	 *
	 * The page determines the controller that will be used for dispatching. It
	 * will be put into $_REQUEST (so that third party code can access the
	 * correct value).
	 *
	 * When setup is true, requests to the setup controller will be redirected to
	 * the profile page (always accessible). Otherwise, this method will also
	 * check whether the current user has access to the found controller. If a
	 * forbidden controller is requested, the profile page is used.
	 *
	 * @return string  the currently active page
	 */
	protected function findPage() {
		$config = sly_Core::config();
		$page   = $this->getPage();

		// Erst normale Startseite, dann User-Startseite, dann System-Startseite und
		// zuletzt auf die Profilseite zurückfallen.

		if (strlen($page) === 0 || !$this->isControllerAvailable($page)) {
			$user = sly_Util_User::getCurrentUser();
			$page = $user ? $user->getStartpage() : null;

			if ($page === null || !$this->isControllerAvailable($page)) {
				$page = strtolower($config->get('START_PAGE'));

				if (!$this->isControllerAvailable($page)) {
					$page = 'profile';
				}
			}
		}

		$_REQUEST[self::PAGEPARAM] = $page;

		return $page;
	}

	public function isControllerAvailable($page) {
		return class_exists($this->getControllerClass($page));
	}

	/**
	 * return classname for &page=whatever
	 *
	 * It will return sly_Controller_System for &page=system
	 * and sly_Controller_System_Languages for &page=system_languages
	 *
	 * @param  string $page
	 * @return string
	 */
	public function getControllerClass($page) {
		$className = 'sly_Controller';
		$parts     = explode('_', $page);

		foreach ($parts as $part) {
			$className .= '_'.ucfirst($part);
		}

		return $className;
	}
}
