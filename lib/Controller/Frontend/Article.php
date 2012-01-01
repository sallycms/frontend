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
		$article = $this->findArticle();

		if ($article) {
			// last chance to tamper with the page building process before the actual article processing starts
			$article = sly_Core::dispatcher()->filter('SLY_PRE_PROCESS_ARTICLE', $article);

			// set the article data in sly_Core
			sly_Core::setCurrentArticleId($article->getId());

			// now that we know the frontend language, init the global i18n object
			$i18n = sly_Core::getI18N();
			$i18n->setLocale(strtolower(sly_Util_Language::getLocale()));
			$i18n->appendFile(SLY_DEVELOPFOLDER.'/lang');

			// finally run the template and generate the output
			print $article->getArticleTemplate();
			$this->article = $article;
		}
		else {
			print t('no_startarticle', 'backend/index.php');
		}
	}

	protected function findArticle() {
		$articleID = sly_request('article_id', 'int');
		$clangID   = sly_request('clang', 'int');

		if ($clangID <= 0 || !sly_Util_Language::exists($clangID)) {
			$clangID = sly_Core::getDefaultClangId();
		}

		// the following article API calls require to know a language
		sly_Core::setCurrentClang($clangID);

		if ($articleID <= 0 || !sly_Util_Article::exists($articleID)) {
			$articleID = sly_Core::getSiteStartArticleId();
		}

		// ask the system if anyone can make a better guess
		$retval = sly_Core::dispatcher()->filter('SLY_RESOLVE_ARTICLE', array(
			'article' => $articleID,
			'clang'   => $clangID
		));

		if (!is_array($retval) || !isset($retval['article']) || !isset($retval['clang'])) {
			throw new sly_Exception('Invalid result got from executing SLY_RESOLVE_ARTICLE.');
		}

		extract($retval);

		if (!sly_Util_Article::exists($article)) {
			$article = sly_Core::getNotFoundArticleId();
		}

		return sly_Util_Article::findById($article, $clang);
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
