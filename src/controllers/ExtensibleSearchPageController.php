<?php

namespace nglasl\extensible;

use SilverStripe\CMS\Search\SearchForm;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\Search\FulltextSearchable;
use Symbiote\Multisites\Multisites;

/**
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchPageController extends \PageController {

	public $service;

	private static $dependencies = array(
		'service' => '%$' . ExtensibleSearchService::class
	);

	private static $allowed_actions = array(
		'getForm',
		'getSearchForm',
		'getSearchResults'
	);

	/**
	 *	Determine whether the search page should start with a listing.
	 */

	public function index() {

		// Determine whether a search engine has been selected.

		$engine = $this->data()->SearchEngine;
		$classes = Config::inst()->get(FulltextSearchable::class, 'searchable_classes');
		if(!$engine || (($engine !== 'Full-Text') && !ClassInfo::exists($engine)) || (($engine === 'Full-Text') && (!is_array($classes) || (count($classes) === 0)))) {

			// The search engine has not been selected.

			return $this->httpError(404);
		}

		// Determine whether the search page should start with a listing.

		if($this->data()->StartWithListing) {

			// Display some search results.

			$request = $this->getRequest();
			return $this->getSearchResults(array(
				'Search' => $request->getVar('Search'),
				'SortBy' => $request->getVar('SortBy'),
				'SortDirection' => $request->getVar('SortDirection')
			), $this->getForm($request));
		}
		else {

			// Instantiate some default templates.

			$templates = array(
				'ExtensibleSearch',
				'ExtensibleSearchPage',
				'Page'
			);

			// Instantiate the search engine specific templates.

			if($engine !== 'Full-Text') {
				$templates = array_merge(array(
					$engine,
					"{$engine}Page"
				), $templates);
			}

			// Determine the template to use.

			$this->extend('updateTemplates', $templates);
			return $this->renderWith($templates);
		}
	}

	/**
	 *	Instantiate the search form.
	 *
	 *	@parameter <{REQUEST}> http request
	 *	@parameter <{DISPLAY_SORTING}> boolean
	 *	@return search form
	 */

	public function getForm($request = null, $sorting = true) {

		// Determine whether a search engine has been selected.

		$engine = $this->data()->SearchEngine;
		$configuration = Config::inst();
		$classes = $configuration->get(FulltextSearchable::class, 'searchable_classes');
		if(!$engine || (($engine !== 'Full-Text') && !ClassInfo::exists($engine)) || (($engine === 'Full-Text') && (!is_array($classes) || (count($classes) === 0)))) {

			// The search engine has not been selected.

			return null;
		}

		// Determine whether the request has been passed through.

		if(is_null($request)) {
			$request = $this->getRequest();
		}

		// Display the search.

		$fields = FieldList::create(
			TextField::create(
				'Search',
				_t('EXTENSIBLE_SEARCH.SEARCH', 'Search'),
				$request->getVar('Search')
			)->addExtraClass('extensible-search')->setAttribute('data-suggestions-enabled', $configuration->get(ExtensibleSearchSuggestion::class, 'enable_suggestions') ? 'true' : 'false')->setAttribute('data-extensible-search-page', $this->data()->ID)
		);

		// Determine whether sorting has been passed through from the template.

		if(is_string($sorting)) {
			$sorting = ($sorting === 'true');
		}

		// Determine whether to display the sorting selection.

		if($sorting) {

			// Display the sorting selection.

			$fields->push(DropdownField::create(
				'SortBy',
				_t('EXTENSIBLE_SEARCH.SORT_BY', 'Sort By'),
				$this->data()->getSelectableFields(),
				$request->getVar('SortBy') ? $request->getVar('SortBy') : $this->data()->SortBy
			)->setHasEmptyDefault(true));
			$fields->push(DropdownField::create(
				'SortDirection',
				_t('EXTENSIBLE_SEARCH.SORT_DIRECTION', 'Sort Direction'),
				array(
					'DESC' => _t('EXTENSIBLE_SEARCH.DESCENDING', 'Descending'),
					'ASC' => _t('EXTENSIBLE_SEARCH.ASCENDING', 'Ascending')
				),
				$request->getVar('SortDirection') ? $request->getVar('SortDirection') : $this->data()->SortDirection
			)->setHasEmptyDefault(true));
		}

		// Instantiate the search form.

		$form = SearchForm::create(
			$this,
			'getForm',
			$fields,
			FieldList::create(
				FormAction::create(
					'getSearchResults',
					_t('EXTENSIBLE_SEARCH.GO', 'Go')
				)
			)
		);

		// When using the full-text search engine, the classes to search needs to be initialised.

		if($engine === 'Full-Text') {
			$form->classesToSearch($classes);
		}

		// Allow extension customisation.

		$this->extend('updateExtensibleSearchForm', $form);
		return $form;
	}

	/**
	 *	Instantiate the search form.
	 *
	 *	@parameter <{REQUEST}> http request
	 *	@parameter <{DISPLAY_SORTING}> boolean
	 *	@return search form
	 */

	public function Form($request = null, $sorting = true) {

		// This provides consistency when it comes to defining parameters from the template.

		return $this->getForm($request, $sorting);
	}

	/**
	 *	Instantiate the search form, primarily outside the search page.
	 *
	 *	@parameter <{REQUEST}> http request
	 *	@parameter <{DISPLAY_SORTING}> boolean
	 *	@return search form
	 */

	public function getSearchForm($request = null, $sorting = false) {

		// Instantiate the search form, primarily excluding the sorting selection.

		$form = $this->getForm($request, $sorting);
		if($form) {

			// When the search form is displayed twice, this prevents a duplicate element ID.

			$form->setName('getSearchForm');

			// Replace the search title with a placeholder.

			$search = $form->Fields()->dataFieldByName('Search');
			$search->setAttribute('placeholder', $search->Title());
			$search->setTitle(null);
		}

		// Allow extension customisation.

		$this->extend('updateExtensibleSearchSearchForm', $form);
		return $form;
	}

	/**
	 *	Display the search form results.
	 *
	 *	@parameter <{SEARCH_PARAMETERS}> array
	 *	@parameter <{SEARCH_FORM}> search form
	 *	@return html text
	 */

	public function getSearchResults($data = null, $form = null) {

		// Determine whether a search engine has been selected.

		$page = $this->data();
		$engine = $page->SearchEngine;
		$classes = Config::inst()->get(FulltextSearchable::class, 'searchable_classes');
		if(!$engine || (($engine !== 'Full-Text') && !ClassInfo::exists($engine)) || (($engine === 'Full-Text') && (!is_array($classes) || (count($classes) === 0)))) {

			// The search engine has not been selected.

			return $this->httpError(404);
		}

		// The analytics require the time taken.

		$time = microtime(true);

		// This is because the search form pulls from the request directly.

		if(!isset($data['Search'])) {
			$data['Search'] = '';
		}
		$request = $this->getRequest();
		$request->offsetSet('Search', $data['Search']);

		// Determine whether the remaining search parameters have been passed through.

		if(!isset($data['SortBy']) || !$data['SortBy']) {
			$data['SortBy'] = $page->SortBy;
		}
		if(!isset($data['SortDirection']) || !$data['SortDirection']) {
			$data['SortDirection'] = $page->SortDirection;
		}
		if(!isset($form)) {
			$form = $this->getForm($request);
		}

		// Instantiate some default templates.

		$templates = array(
			'ExtensibleSearch',
			'ExtensibleSearchPage',
			'Page'
		);

		// Determine the search engine that has been selected.

		if($engine !== 'Full-Text') {

			// The analytics require the time taken.

			$time = microtime(true);

			// Determine the search engine specific search results.

			$results = singleton($engine)->getSearchResults($data, $form, $page);

			// The search results format needs to be correct.

			if(!isset($results['Results'])) {
				$results = array(
					'Results' => $results
				);
			}

			// Determine the number of search results.

			$count = isset($results['Count']) ? (int)$results['Count'] : count($results['Results']);

			// Instantiate the search engine specific templates.

			$templates = array_merge(array(
				"{$engine}_results",
				"{$engine}Page_results",
				'ExtensibleSearch_results',
				'ExtensibleSearchPage_results',
				'Page_results',
				$engine,
				"{$engine}Page"
			), $templates);
		}

		// Determine the full-text specific search results.

		else {

			// The paginated list needs to be manipulated, as filtering and sorting is not possible otherwise.

			$start = $request->getVar('start') ? (int)$request->getVar('start') : 0;
			$form->setPageLength(PHP_INT_MAX);

			// This is because the search form pulls from the request directly.

			$request->offsetSet('start', 0);
			$list = $form->getResults()->getList();

			// The search engine may only support limited hierarchy filtering for multiple sites.

			$filter = $page->SearchTrees()->column();
			if(count($filter) && (($hierarchy = $page->supports_hierarchy) || ClassInfo::exists(Multisites::class))) {

				// Apply the search trees filtering.

				$list = $list->filter($hierarchy ? 'ParentID' : 'SiteID', $filter);
			}

			// Apply custom filtering.

			$this->extend('updateFiltering', $list);

			// Apply the sorting.

			$list = $list->sort("{$data['SortBy']} {$data['SortDirection']}");

			// The paginated list needs to be instantiated again.

			$results = array(
				'Title' => _t('EXTENSIBLE_SEARCH.SEARCH_RESULTS', 'Search Results'),
				'Query' => $form->getSearchQuery(),
				'Results' => PaginatedList::create(
					$list
				)->setPageLength($page->ResultsPerPage)->setPageStart($start)->setTotalItems($count = $list->count())
			);

			// Instantiate the full-text specific templates.

			$templates = array_merge(array(
				'ExtensibleSearch_results',
				'ExtensibleSearchPage_results',
				'Page_results'
			), $templates);
		}

		// Determine the template to use.

		$this->extend('updateTemplates', $templates);
		$output = $this->customise($results)->renderWith($templates);
		$output->Count = $count;

		// Determine whether analytics are to be suppressed.

		if($request->getVar('analytics') !== 'false') {

			// Update the search page specific analytics.

			$this->service->logSearch($data['Search'], $count, microtime(true) - $time, $engine, $page->ID);
		}

		// Display the search form results.

		return $output;
	}

}
