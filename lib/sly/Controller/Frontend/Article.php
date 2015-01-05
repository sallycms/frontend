<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_Article extends sly_Controller_Frontend_Base {
	private $notFound = false;

	public function __construct() {
		sly_Core::dispatcher()->addListener('SLY_RESOLVE_ARTICLE', array($this, 'oldSchoolResolver'));
	}

	public function indexAction() {
		$article = $this->findArticle();

		// preselect the HTTP response code
		$response = $this->prepareResponse($article);

		if ($article) {
			$container = $this->getContainer();

			// set the article data in sly_Core
			$container->setCurrentArticleId($article->getId());
			$container->setCurrentLanguageId($article->getClang());
			$container->setCurrentArticleRevision($article->getRevision());

			// now that we know the frontend language, init the global i18n object
			$i18n = $container['sly-i18n'];
			$i18n->setLocale(strtolower(sly_Util_Language::getLocale()));
			$i18n->appendFile(SLY_DEVELOPFOLDER.'/lang');

			// notify listeners about the article to be rendered
			$container['sly-dispatcher']->notify('SLY_CURRENT_ARTICLE', $article);

			// finally run the template and generate the output
			if (!$article->getTemplateName()) {
				$output = t('no_template_set');
			}
			else {
				$output = $article->getArticleTemplate();

				// article postprocessing is a special task, so here's a special event
				$output = $container['sly-dispatcher']->filter('SLY_ARTICLE_OUTPUT', $output, compact('article'));
			}
		}
		else {
			// If we got here, not even the 404 article could be found. Ouch.
			$output = t('no_startarticle', 'backend/index.php');
		}

		$response->setContent($output);
		return $response;
	}

	protected function prepareResponse(sly_Model_Article $article = null) {
		$lastMod  = sly_Core::config()->get('use_last_modified');
		$response = sly_Core::getResponse();

		// handle 404
		if ($this->notFound) {
			$response->setStatusCode(404);
		}

		// optionally send Last-Modified header
		if ($article && ($lastMod === true || $lastMod === 'frontend')) {
			$response->setLastModified($article->getUpdateDate());
		}

		return $response;
	}

	protected function findArticle() {
		$article = sly_Core::dispatcher()->filter('SLY_RESOLVE_ARTICLE', null);

		// Did all listeners behave?
		if ($article !== null && !($article instanceof sly_Model_Article)) {
			throw new LogicException('Listeners to SLY_RESOLVE_ARTICLE are required to return a sly_Model_Article instance.');
		}

		// If no article could be found, display the not-found article.
		// Try to use the current language, maybe a resolver did not detect the article, but
		// is pretty sure about the requested language.
		if ($article === null) {
			$this->notFound = true;

			$clang   = sly_Core::getCurrentClang();
			$article = sly_Util_Article::findById(sly_Core::getNotFoundArticleId(), $clang === null ? sly_Core::getDefaultClangId() : $clang);
		}

		return $article;
	}

	public function isNotFound() {
		return $this->notFound;
	}

	public function setIsNotFound($isNotFound = true) {
		$this->notFound = !!$isNotFound;
	}

	public function oldSchoolResolver($article) {
		// if someone has already found an article, do nothing
		if ($article) return $article;

		$best = sly_Service_Article::FIND_REVISION_BEST;

		// we need to know if the params are missing
		$request   = $this->getRequest();
		$articleID = $request->request('article_id', 'int');
		$clangID   = $request->request('clang', 'int', $this->getContainer()->getConfig()->get('default_clang_id'));
		$revision  = $request->request('revision', 'int', $best);
		$isStart   = rtrim(dirname($_SERVER['PHP_SELF']), '/').'/' === $_SERVER['REQUEST_URI'];

		// it might be the startpage http://example.com/ which has no params
		if ($articleID === null && $isStart) {
			$articleID = sly_Core::getSiteStartArticleId();
		}

		// A wrong language counts as not found!
		// But since we're nice people, we won't just give up and try to use the
		// site's default language, possibly at least showing the requested article.
		if (!sly_Util_Language::exists($clangID)) {
			if (!$isStart && sly_Util_Language::isMultilingual()) {
				$this->notFound = true;
			}
			$clangID = sly_Core::getDefaultClangId();
		}

		// setting a specific revision is only allowed to authenticated users with the appropriate permissions
		if ($revision !== $best) {
			// if there's a cookie, start the session
			sly_Util_Session::start(true);

			$user    = $this->getContainer()->get('sly-service-user')->getCurrentUser();
			$canRead = false;

			if ($user) {
				if ($user->isAdmin()) {
					$canRead = true;
				}
				elseif (class_exists('sly_Backend_Authorisation_Util')) { // do not create a hard dependency on the backend app
					$canRead = sly_Backend_Authorisation_Util::canReadArticle($user, $articleID);
				}
			}

			if (!$canRead) {
				$revision = $best;
			}
		}

		// find the requested article (or give up by returning null)
		return sly_Util_Article::findById($articleID, $clangID, $revision);
	}
}
