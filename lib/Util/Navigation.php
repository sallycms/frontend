<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * This class generates a navigation and is highly configurable
 *
 * To change the default list-navigation, create a
 * subclass of this class. Overwrite or overload the desired methods.
 *
 * Overwrite the generateNavigation and the getHTML methods to change the html
 * output. generateNavigation is called once to generate the full navigation
 * tree. getHTML is called recursively to handle each single navigation entry.
 * The $childrenHTMLString contains the result of the deeper navigation entries.
 *
 * Use the constructor to get a new instance of the navigation object.
 *
 * Use getNavigationHTMLString to get the generated HTML-Output for the
 * navigation.
 *
 * Use getPathString to get the path to the current active article.
 *
 * @ingroup util
 */
class sly_Util_Navigation {
	protected $activePathIds;        ///< array
	protected $activePathCategories; ///< array
	protected $naviString;           ///< string
	protected $maxDepth;             ///< int
	protected $useHTMLSpecialchars;  ///< boolean
	protected $activeArticleId;      ///< int
	protected $startArticleId;       ///< int
	protected $isStartClang;         ///< boolean

	/**
	 * @param int     $depth
	 * @param boolean $fullNavigation
	 * @param boolean $useHTMLSpecialchars
	 * @param array   $baseCategories
	 * @param int     $activeArticleId
	 */
	public function __construct($depth = 1, $fullNavigation = false, $useHTMLSpecialchars = true, $baseCategories = null, $activeArticleId = null) {
		$this->maxDepth            = $depth;
		$this->useHTMLSpecialchars = $useHTMLSpecialchars;
		$this->activeArticleId     = is_null($activeArticleId) ? sly_Core::getCurrentArticleId() : $activeArticleId;
		$this->startArticleId      = sly_Core::getSiteStartArticleId();
		$this->isStartClang        = sly_Core::getDefaultClangId() == sly_Core::getCurrentClang();

		$baseCategories = is_null($baseCategories) ? sly_Util_Category::getRootCategories(true) : $baseCategories;
		$this->generateNavigation($baseCategories, $fullNavigation);
	}

	/**
	 * returns an array which contains current active category is and its parents ids
	 *
	 * @return array
	 */
	protected function getActivePathArray() {
		if (!isset($this->activePathIds)) {
			$parts = explode('|', sly_Util_Article::findById($this->activeArticleId)->getPath().$this->activeArticleId);
			$this->activePathIds = array_filter($parts);
		}
		return $this->activePathIds;
	}

	/**
	 * generates the navigationstring
	 *
	 * @param array   $baseCategories
	 * @param boolean $fullNavigation
	 */
	protected function generateNavigation($baseCategories, $fullNavigation) {
		$this->naviString = $this->walkCategories($baseCategories, $fullNavigation, $this->maxDepth);
	}

	/**
	 * returns a md5 hash of the current navigation html output
	 *
	 * @return string
	 */
	protected function getNavigationHash() {
		return md5($this->getNavigationHTMLString());
	}

	/**
	 * gets the navigationstring for a level
	 *
	 * @param  array   $categories
	 * @param  boolean $all
	 * @param  int     $maxDepth
	 * @param  int     $currentLevel
	 * @return string
	 */
	protected function walkCategories($categories, $all, $maxDepth, $currentLevel = 1) {
		// skip possibly expensive category filtering if possible
		if ($currentLevel > $maxDepth) return '';

		// custom filtering in subclasses
		$categories = $this->filterCategories($categories);
		if (empty($categories)) return '';

		$lastnum      = count($categories) -1;
		$resultString = '';

		foreach ($categories as $num => $category) {
			$isActive = $this->isLevelActive($category);

			if ($isActive) {
				$this->activePathCategories[] = $category;
			}

			if (($isActive || $all) && $currentLevel+1 <= $maxDepth) {
				$children           = $category->getChildren(true);
				$childrenHTMLString = $this->walkCategories($children, $all, $maxDepth, $currentLevel+1);
			}
			else {
				$childrenHTMLString = '';
			}

			$isLast = $num == $lastnum;
			$resultString .= $this->getHTMLForCategory($category, $childrenHTMLString, $currentLevel, $isActive, $isLast, $num);
		}

		if (!empty($resultString)) {
			$resultString = '<ul class="nav'.$currentLevel.'">'.$resultString.'</ul>';
		}

		return $resultString;
	}

	/**
	 * Return the list item html for one category
	 *
	 * @param  sly_Model_Category $category
	 * @param  string             $childrenHTMLString
	 * @param  int                $currentLevel
	 * @param  boolean            $isActive
	 * @param  boolean            $isLast
	 * @param  int                $position
	 * @return string
	 */
	protected function getHTMLForCategory(sly_Model_Category $category, $childrenHTMLString, $currentLevel, $isActive, $isLast, $position) {
		$text         = $this->getCategoryText($category);
		$url          = $this->getCategoryUrl($category);
		$isActiveLeaf = $isActive && $this->isActiveLeaf($category);

		return $this->getHTMLForCategoryData($text, $url, $childrenHTMLString, $isActive, $isLast, $isActiveLeaf, $position);
	}

	/**
	 * Return the list item html for one categorydata set
	 *
	 * @param  string  $text
	 * @param  string  $url
	 * @param  string  $childrenHTMLString
	 * @param  boolean $isActive
	 * @param  boolean $isLast
	 * @param  boolean $isActiveLeaf
	 * @param  int     $position
	 * @return string
	 */
	protected function getHTMLForCategoryData($text, $url, $childrenHTMLString, $isActive, $isLast, $isActiveLeaf, $position) {
		return '
				<li class="num'.$position.($isActive ? ' active' : '').($isLast ? ' last' : '').($isActiveLeaf ? ' active_leaf' : '').'">
					<a href="'.$url.'"'.($isActive ? ' class="active"' : '').'>'.$text.'</a>
					'.$childrenHTMLString.'
				</li>';
	}

	/**
	 * return true if the category is the current category,
	 * of if it is parent of the current.
	 *
	 * @param  sly_Model_Category $category
	 * @return boolean
	 */
	protected function isLevelActive(sly_Model_Category $category) {
		return in_array($category->getId(), $this->getActivePathArray());
	}

	/**
	 * return true when the category is the active article
	 *
	 * @param  sly_Model_Category $category
	 * @return boolean
	 */
	protected function isActiveLeaf(sly_Model_Category $category) {
		return $this->activeArticleId == $category->getId();
	}

	/**
	 * filters a list of categories and returns the filtered list
	 *
	 * @param  array $categories
	 * @return array
	 */
	protected function filterCategories($categories) {
		return $categories;
	}

	/**
	 * returns the url for a category
	 *
	 * @param  sly_Model_Category $category
	 * @return string
	 */
	protected function getCategoryUrl(sly_Model_Category $category) {
		if ($this->isStartClang && $this->startArticleId == $category->getId()) {
			return './';
		}
		else {
			return $category->getUrl();
		}
	}

	/**
	 * returns the link text for a category
	 *
	 * @param  sly_Model_Category $category
	 * @return string
	 */
	protected function getCategoryText(sly_Model_Category $category) {
		$text = $category->getName();
		if ($this->useHTMLSpecialchars) $text = sly_html($text);
		return $text;
	}

	/**
	 * return the name of the active category in the given level
	 *
	 * @param  int $level
	 * @return string
	 */
	protected function getActiveCategoryForLevel($level) {
		return isset($this->activePathCategories[$level]) ? $this->activePathCategories[$level] : null;
	}

	/**
	 * public method
	 *
	 * Use getNavigationHTMLString to get the generated HTML-Output for the navigation.
	 *
	 * @return string The HTML-Output for the navigation
	 */
	public function getNavigationHTMLString() {
		return $this->naviString;
	}

	/**
	 *
	 * Use getPathString to get the path to the current active article.
	 *
	 * @param  string  $separator  separates the path entries from each other.
	 * @param  boolean $clickable  if true, alle entries are HTML links to the corresponding pages
	 * @param  int     $offset     der Wert, um den die Artikel-IDs im Gesamtbaum verschoben sind (in 99,9% der Fälle ist das 0)
	 * @return string              the breadcrumb path
	 */
	public function getBreadcrumbs($separator = '->', $clickable = false, $offset = 0) {
		$path = array();
		for ($i = 0 + $offset; $i <= $this->maxDepth; $i++) {
			$levelObject = $this->getActiveCategoryForLevel($i);
			if (is_null($levelObject)) break;

			$levelName = $this->getCategoryText($levelObject);
			if ($clickable) {
				$levelName = '<a href="'.$this->getCategoryUrl($levelObject).'">'.$levelName.'</a>';
			}
			$path[] = $levelName;
		}
		return implode($separator, $path);
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->getNavigationHTMLString();
	}
}
