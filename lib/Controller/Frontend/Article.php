<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_Article extends sly_Controller_Frontend_Base {
	public function indexAction() {
		// find current article
		$article = sly_Util_Article::findById(sly_Core::getCurrentArticleId(), sly_Core::getCurrentClang());

		// last chance to tamper with the page building process before the actual article processing starts
		$article = sly_Core::dispatcher()->filter('SLY_PRE_PROCESS_ARTICLE', $article);

		if ($article) {
			print $article->getArticleTemplate();
		}
		else {
			print t('no_startarticle', 'backend/index.php');
		}
	}
}
