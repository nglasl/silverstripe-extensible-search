<?php

/**
 * A controller extension that provides additional methods on page controllers
 * to allow for better searching using an extensible search page
 *
 * @author marcus@silverstripe.com.au
 * @license http://silverstripe.org/bsd-license/
 */
class ExtensibleSearchExtension extends Extension {

	private static $allowed_actions = array(
		'SearchForm',
		'results'
	);

	public function onAfterInit() {

		if(Config::inst()->get('ExtensibleSearchSuggestion', 'enable_suggestions')) {
			Requirements::css('framework/thirdparty/jquery-ui-themes/smoothness/jquery-ui.min.css');
			Requirements::javascript('framework/thirdparty/jquery-ui/jquery-ui.min.js');
			Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-suggestions.js');
		}
		if(Config::inst()->get('ExtensibleSearchSuggestion', 'enable_typeahead')) {
			Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-typeahead.js');
		}
	}

	/**
	 * Returns the default search page for this site
	 *
	 * @return ExtensibleSearchPage
	 */
	public function getSearchPage() {

		$page = ExtensibleSearchPage::get();
		if(class_exists('Multisites')) {
			$siteID = Multisites::inst()->getCurrentSiteId();
			$page = $page->filter('SiteID', $siteID);
		}
		return $page->first();
	}

	/**
	 * Get the list of facet values for the given term
	 *
	 * @param String $term
	 */
	public function Facets($term=null) {
		$sp = $this->owner->getSearchPage();
		if ($sp && $sp->hasMethod('currentFacets')) {
			$facets = $sp->currentFacets($term);
			return $facets;
		}
	}

	/**
	 * The current search query that is being run by the search page.
	 *
	 * @return String
	 */
	public function SearchQuery() {
		$sp = $this->owner->getSearchPage();
		if ($sp) {
			return $sp->SearchQuery();
		}
	}

	/**
	 * Site search form
	 */
	public function SearchForm() {

		// Retrieve the search form input, excluding any filters.

		$form = (($page = $this->owner->getSearchPage()) && $page->SearchEngine) ? ModelAsController::controller_for($page)->getForm(false) : null;

		// Update the search input to account for usability.

		if($form) {
			$search = $form->Fields()->dataFieldByName('Search');
			$search->setAttribute('placeholder', $search->Title());
			$search->setTitle('');
		}
		return $form;
	}

	public function SearchSuggestions() {
		$results = ExtensibleSearchSuggestion::get()->filter(array(
			'Approved' => 1,
			'ExtensibleSearchPageID' => $this->getExtensibleSearchPage()
		))->sort('Frequency', 'DESC')->limit(5);

		return $results;
	}

	public function getExtensibleSearchPage() {

		$id = class_exists('Multisites') ? $this->owner->SiteID : 0;

		$page = ExtensibleSearchPage::get()->filter('ParentID', $id)->first();

		if (!$page) {
			if ($id) {
				$page = ExtensibleSearchPage::get()->filter('SiteID', $id)->first();
			} else {
				$page = ExtensibleSearchPage::get()->first();
			}
		}

		return $page->ID;
	}

}
