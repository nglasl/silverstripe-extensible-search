<?php

/**
 *	This extension is used to implement a search form, primarily outside the search page.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchExtension extends Extension {

	private static $allowed_actions = array(
		'getSearchForm'
	);

	/**
	 *	Instantiate the search form, primarily outside the search page.
	 *
	 *	@parameter <{REQUEST}> ss http request
	 *	@parameter <{DISPLAY_SORTING}> boolean
	 *	@return search form
	 */

	public function getSearchForm($request = null, $sorting = false) {

		// Instantiate the search form, primarily excluding the sorting selection.

		return ($page = $this->owner->getSearchPage()) ? ModelAsController::controller_for($page)->getSearchForm($request, $sorting) : null;
	}

	/**
	 *	Retrieve the search page.
	 *
	 *	@return extensible search page
	 */

	public function getSearchPage() {

		$pages = ExtensibleSearchPage::get();

		// This is required to support multiple sites.

		if(ClassInfo::exists('Multisites')) {
			$pages = $pages->filter('SiteID', $this->owner->SiteID);
		}
		return $pages->first();
	}

}
