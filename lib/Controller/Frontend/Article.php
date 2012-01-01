<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_Article extends sly_Controller_Frontend_Base {
	protected $article;

	public function indexAction() {
		// find current article
		$article = sly_Util_Article::findById(sly_Core::getCurrentArticleId(), sly_Core::getCurrentClang());

		// last chance to tamper with the page building process before the actual article processing starts
		$article = sly_Core::dispatcher()->filter('SLY_PRE_PROCESS_ARTICLE', $article);

		if ($article) {
			// now that we know the frontend language, init the global i18n object
			$i18n = sly_Core::getI18N();
			$i18n->setLocale(sly_Util_Language::getLocale());
			$i18n->appendFile(SLY_DEVELOPFOLDER.'/lang');

			// finally run the template and generate the output
			print $article->getArticleTemplate();
			$this->article = $article;
		}
		else {
			print t('no_startarticle', 'backend/index.php');
		}
	}

	public function teardown() {
		// check if this is a 404 article and set HTTP status accordingly
		// (This works only for projects not using a realurl implementation.)

		if ($this->article) {
			$requestedID = sly_request('article_id', 'int');
			$displayedID = $this->article->getId();
			$config      = sly_Core::config();
			$notFoundID  = sly_Core::getNotFoundArticleId();
			$startID     = sly_Core::getSiteStartArticleId();
			$lastMod     = $config->get('USE_LAST_MODIFIED');
			$response    = sly_Core::getResponse();

			if ($requestedID !== $notFoundID && $displayedID === $notFoundID && $displayedID !== $startID) {
				$response->setStatusCode(404);
			}

			if ($lastMod === true || $lastMod === 'frontend') {
				$response->setLastModified($article->getUpdateDate());
			}
		}
	}
}
