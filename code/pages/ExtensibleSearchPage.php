<?php

/**
 *	The page used to display search results, analytics and suggestions, allowing user customisation and developer extension.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchPage extends Page {

	private static $db = array(
		'SearchEngine' => 'Varchar(255)',
		'SortBy' => 'Varchar(255)',
		'SortDirection' => "Enum('DESC, ASC', 'DESC')",
		'StartWithListing' => 'Boolean',
		'ResultsPerPage' => 'Int'
	);

	private static $defaults = array(
		'ShowInMenus' => 0,
		'ShowInSearch' => 0,
		'ResultsPerPage' => 10
	);

	private static $has_many = array(
		'History' => 'ExtensibleSearch',
		'Suggestions' => 'ExtensibleSearchSuggestion'
	);

	private static $many_many = array(
		'SearchTrees' => 'SiteTree'
	);

	/**
	 *	The search engine extensions that are available.
	 */

	private static $search_engine_extensions = array();

	/**
	 *	The full-text search engine does not support hierarchy filtering.
	 */

	public static $supports_hierarchy = false;

	/**
	 *	Instantiate a search page, should one not exist.
	 */

	public function requireDefaultRecords() {

		parent::requireDefaultRecords();
		$mode = Versioned::get_reading_mode();
		Versioned::reading_stage('Stage');

		// Determine whether pages should be created.

		if(self::config()->create_default_pages) {

			// Determine whether an extensible search page already exists.

			if(!ExtensibleSearchPage::get()->first()) {

				// Instantiate an extensible search page.

				$page = ExtensibleSearchPage::create();
				$page->Title = 'Search Page';
				$page->write();
				DB::alteration_message('"Default" Extensible Search Page', 'created');
			}
		}

		// This is required to support multiple sites.

		else if(ClassInfo::exists('Multisites')) {
			foreach(Site::get() as $site) {

				// Determine whether an extensible search page already exists.

				if(!ExtensibleSearchPage::get()->filter('SiteID', $site->ID)->first()) {

					// Instantiate an extensible search page.

					$page = ExtensibleSearchPage::create();
					$page->ParentID = $site->ID;
					$page->Title = 'Search Page';
					$page->write();
					DB::alteration_message("\"{$site->Title}\" Extensible Search Page", 'created');
				}
			}
		}
		Versioned::set_reading_mode($mode);
	}

	/**
	 *	Display the search engine specific configuration, and the search page specific analytics and suggestions.
	 */

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css(EXTENSIBLE_SEARCH_PATH . '/css/extensible-search.css');

		// Determine the search engine extensions that are available.

		$engines = array();
		foreach(self::config()->search_engine_extensions as $extension => $display) {

			// The search engine extensions may define an optional display title.

			if(is_numeric($extension)) {
				$extension = $display;
			}

			// Determine whether the search engine extensions have been applied correctly.

			if(ClassInfo::exists($extension) && ClassInfo::exists("{$extension}_Controller") && $this->hasExtension($extension) && ModelAsController::controller_for($this)->hasExtension("{$extension}_Controller")) {
				$engines[$extension] = $display;
			}
		}

		// Determine whether the full-text search engine is available.

		$configuration = Config::inst();
		$classes = $configuration->get('FulltextSearchable', 'searchable_classes');
		if(is_array($classes) && (count($classes) > 0)) {
			$engines['Full-Text'] = 'Full-Text';
		}

		// Display the search engine selection.

		$fields->addFieldToTab('Root.Main', DropdownField::create(
			'SearchEngine',
			'Search Engine',
			$engines
		)->setHasEmptyDefault(true)->setRightTitle('This needs to be saved before further customisation is available'), 'Title');

		// Determine whether a search engine has been selected.

		if($this->SearchEngine && isset($engines[$this->SearchEngine])) {

			// Display a search engine specific notice.

			$fields->addFieldToTab('Root.Main', LiteralField::create(
				'SearchEngineNotice',
				"<p class='extensible-search notice'><strong>{$engines[$this->SearchEngine]} Search Page</strong></p>"
			), 'Title');

			// Determine whether the search engine supports hierarchy filtering.

			$hierarchy = self::$supports_hierarchy;
			if($this->SearchEngine !== 'Full-Text') {
				foreach($this->extension_instances as $instance) {
					if(get_class($instance) === $this->SearchEngine) {
						$instance->setOwner($this);
						if(isset($instance::$supports_hierarchy)) {
							$hierarchy = $instance::$supports_hierarchy;
						}
						$instance->clearOwner();
						break;
					}
				}
			}

			// The search engine may only support limited hierarchy filtering for multiple sites.

			if($hierarchy || ClassInfo::exists('Multisites')) {

				// Display the search trees selection.

				$fields->addFieldToTab('Root.Main', $tree = TreeMultiselectField::create(
					'SearchTrees',
					'Search Trees',
					'SiteTree'
				), 'Content');

				// Determine whether the search engine only supports limited hierarchy filtering.

				if(!$hierarchy) {

					// Update the search trees to reflect this.

					$tree->setDisableFunction(function($page) {

						return ($page->ParentID != 0);
					});
					$tree->setRightTitle('This <strong>search engine</strong> only supports limited hierarchy');
				}
			}

			// Display the sorting selection.

			$fields->addFieldToTab('Root.Main', DropdownField::create(
				'SortBy',
				'Sort By',
				$this->getSelectableFields()
			), 'Content');
			$fields->addFieldToTab('Root.Main', DropdownField::create(
				'SortDirection',
				'Sort Direction',
				array(
					'DESC' => 'Descending',
					'ASC' => 'Ascending'
				)
			), 'Content');

			// Display the start with listing selection.

			$fields->addFieldToTab('Root.Main', CheckboxField::create(
				'StartWithListing',
				'Start With Listing?'
			)->addExtraClass('start-with-listing'), 'Content');

			// Display the results per page selection.

			$fields->addFieldToTab('Root.Main', NumericField::create(
				'ResultsPerPage'
			), 'Content');
		}
		else {

			// The search engine has not been selected.

			$fields->addFieldToTab('Root.Main', LiteralField::create(
				'SearchEngineNotification',
				"<p class='extensible-search notification'><strong>Select a Search Engine</strong></p>"
			), 'Title');
		}

		// Determine whether analytics have been enabled.

		if($configuration->get('ExtensibleSearch', 'enable_analytics')) {

			// Determine the search page specific analytics.

			$history = $this->History();
			$query = new SQLSelect(
				"Term, COUNT(*) AS Frequency, ((COUNT(*) * 100.00) / {$history->count()}) AS FrequencyPercentage, AVG(Time) AS AverageTimeTaken, (Results > 0) AS Results",
				'ExtensibleSearch',
				"ExtensibleSearchPageID = {$this->ID}",
				array(
					'Frequency' => 'DESC',
					'Term' => 'ASC'
				),
				'Term'
			);

			// These will require display formatting.

			$analytics = ArrayList::create();
			foreach($query->execute() as $result) {
				$result = ArrayData::create(
					$result
				);
				$result->FrequencyPercentage = sprintf('%.2f %%', $result->FrequencyPercentage);
				$result->AverageTimeTaken = sprintf('%.5f', $result->AverageTimeTaken);
				$result->Results = $result->Results ? 'true' : 'false';
				$analytics->push($result);
			}

			// Instantiate the analytic summary.

			$fields->addFieldToTab('Root.SearchAnalytics', $summary = GridField::create(
				'Summary',
				'Summary',
				$analytics
			)->setModelClass('ExtensibleSearch'));
			$summaryConfiguration = $summary->getConfig();

			// Update the display columns.

			$summaryDisplay = array(
				'Term' => 'Search Term',
				'Frequency' => 'Frequency',
				'FrequencyPercentage' => 'Frequency %',
				'AverageTimeTaken' => 'Average Time Taken (s)',
				'Results' => 'Has Results?'
			);
			$summaryConfiguration->getComponentByType('GridFieldDataColumns')->setDisplayFields($summaryDisplay);

			// Instantiate an export button.

			$summaryConfiguration->addComponent($summaryExport = new GridFieldExportButton());
			$summaryExport->setExportColumns($summaryDisplay);

			// Update the custom summary fields to be sortable.

			$summaryConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'FrequencyPercentage' => 'Frequency'
			));
			$summaryConfiguration->removeComponentsByType('GridFieldFilterHeader');

			// Instantiate the analytic history.

			$fields->addFieldToTab('Root.SearchAnalytics', $history = GridField::create(
				'History',
				'History',
				$history
			)->setModelClass('ExtensibleSearch'));
			$historyConfiguration = $history->getConfig();

			// Instantiate an export button.

			$historyConfiguration->addComponent(new GridFieldExportButton());

			// Update the custom summary fields to be sortable.

			$historyConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'TimeSummary' => 'Created',
				'TimeTakenSummary' => 'Time',
				'SearchEngineSummary' => 'SearchEngine'
			));
			$historyConfiguration->removeComponentsByType('GridFieldFilterHeader');
		}

		// Determine whether suggestions have been enabled.

		if($configuration->get('ExtensibleSearchSuggestion', 'enable_suggestions')) {

			// Appropriately restrict the approval functionality.

			$user = Member::currentUserID();
			if(Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS')) {
				Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-approval.js');
			}

			// Determine the search page specific suggestions.

			$fields->addFieldToTab('Root.SearchSuggestions', GridField::create(
				'Suggestions',
				'Suggestions',
				$this->Suggestions(),
				$suggestionsConfiguration = GridFieldConfig_RecordEditor::create()
			)->setModelClass('ExtensibleSearchSuggestion'));

			// Update the custom summary fields to be sortable.

			$suggestionsConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'FrequencySummary' => 'Frequency',
				'FrequencyPercentage' => 'Frequency',
				'ApprovedField' => 'Approved'
			));
			$suggestionsConfiguration->removeComponentsByType('GridFieldFilterHeader');
		}

		// Allow extension customisation.

		$this->extend('updateExtensibleSearchPageCMSFields', $fields);
		return $fields;
	}

	/**
	 *	Initialise the default search engine specific sorting.
	 */

	public function onBeforeWrite() {

		parent::onBeforeWrite();

		// Determine whether a new search engine has been selected.

		$changed = $this->getChangedFields();
		if($this->SearchEngine && isset($changed['SearchEngine']) && ($changed['SearchEngine']['before'] != $changed['SearchEngine']['after'])) {

			// Determine whether the sort by is a selectable field.

			$selectable = $this->getSelectableFields();
			if(!isset($selectable[$this->SortBy])) {

				// Initialise the default search engine specific sort by.

				$this->SortBy = ($this->SearchEngine !== 'Full-Text') ? 'LastEdited' : 'Relevance';
			}
		}

		// Initialise the default search engine specific sort direction.

		if(!$this->SortDirection) {
			$this->SortDirection = 'DESC';
		}
	}

	/**
	 *	Determine the search engine specific selectable fields, primarily for sorting.
	 *
	 *	@return array(string, string)
	 */

	public function getSelectableFields() {

		// Instantiate some default selectable fields, just in case the search engine does not provide any.

		$selectable = array(
			'LastEdited' => 'Last Edited',
			'ID' => 'Created',
			'ClassName' => 'Type'
		);

		// Determine the search engine that has been selected.

		if(($this->SearchEngine !== 'Full-Text') && ClassInfo::exists($this->SearchEngine)) {

			// Determine the search engine specific selectable fields.

			foreach($this->extension_instances as $instance) {
				if(get_class($instance) === $this->SearchEngine) {
					$instance->setOwner($this);
					$fields = method_exists($instance, 'getSelectableFields') ? $instance->getSelectableFields() : array();
					return $fields + $selectable;
				}
			}
		}
		else if(($this->SearchEngine === 'Full-Text') && is_array($classes = Config::inst()->get('FulltextSearchable', 'searchable_classes')) && (count($classes) > 0)) {

			// Determine the full-text specific selectable fields.

			$selectable = array(
				'Relevance' => 'Relevance'
			) + $selectable;
			foreach($classes as $class) {
				$fields = DataObject::database_fields($class);

				// Determine the most appropriate fields, primarily for sorting.

				if(isset($fields['Title'])) {
					$selectable['Title'] = 'Title';
				}
				if(isset($fields['MenuTitle'])) {
					$selectable['MenuTitle'] = 'Navigation Title';
				}
				if(isset($fields['Sort'])) {
					$selectable['Sort'] = 'Display Order';
				}

				// This is specific to file searching.

				if(isset($fields['Name'])) {
					$selectable['Name'] = 'File Name';
				}
			}
		}

		// Allow extension customisation, so custom fields may be selectable.

		$this->extend('updateExtensibleSearchPageSelectableFields', $selectable);
		return $selectable;
	}

}

class ExtensibleSearchPage_Controller extends Page_Controller {

	public $service;

	private static $dependencies = array(
		'service' => '%$ExtensibleSearchService'
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
		$classes = Config::inst()->get('FulltextSearchable', 'searchable_classes');
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
	 *	Display an error page on invalid request.
	 *
	 *	@parameter <{ERROR_CODE}> integer
	 *	@parameter <{ERROR_MESSAGE}> string
	 */

	public function httpError($code, $message = null) {

		// Determine the error page for the given status code.

		$errorPages = ErrorPage::get()->filter('ErrorCode', $code);

		// Allow extension customisation.

		$this->extend('updateErrorPages', $errorPages);

		// Retrieve the error page response.

		if($errorPage = $errorPages->first()) {
			Requirements::clear();
			Requirements::clear_combined_files();
			$response = ModelAsController::controller_for($errorPage)->handleRequest(new SS_HTTPRequest('GET', ''), DataModel::inst());
			throw new SS_HTTPResponse_Exception($response, $code);
		}

		// Retrieve the cached error page response.

		else if(file_exists($cachedPage = ErrorPage::get_filepath_for_errorcode($code, class_exists('Translatable') ? Translatable::get_current_locale() : null))) {
			$response = new SS_HTTPResponse();
			$response->setStatusCode($code);
			$response->setBody(file_get_contents($cachedPage));
			throw new SS_HTTPResponse_Exception($response, $code);
		}
		else {
			return parent::httpError($code, $message);
		}
	}

	/**
	 *	Instantiate the search form.
	 *
	 *	@parameter <{REQUEST}> ss http request
	 *	@parameter <{DISPLAY_SORTING}> boolean
	 *	@return search form
	 */

	public function getForm($request = null, $sorting = true) {

		// Determine whether a search engine has been selected.

		$engine = $this->data()->SearchEngine;
		$configuration = Config::inst();
		$classes = $configuration->get('FulltextSearchable', 'searchable_classes');
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
				'Search',
				$request->getVar('Search')
			)->addExtraClass('extensible-search')->setAttribute('data-suggestions-enabled', $configuration->get('ExtensibleSearchSuggestion', 'enable_suggestions') ? 'true' : 'false')->setAttribute('data-extensible-search-page', $this->data()->ID)
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
				'Sort By',
				$this->data()->getSelectableFields(),
				$request->getVar('SortBy') ? $request->getVar('SortBy') : $this->data()->SortBy
			)->setHasEmptyDefault(true));
			$fields->push(DropdownField::create(
				'SortDirection',
				'Sort Direction',
				array(
					'DESC' => 'Descending',
					'ASC' => 'Ascending'
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
					'Go'
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
	 *	@parameter <{REQUEST}> ss http request
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
	 *	@parameter <{REQUEST}> ss http request
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
		$classes = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(!$engine || (($engine !== 'Full-Text') && !ClassInfo::exists($engine)) || (($engine === 'Full-Text') && (!is_array($classes) || (count($classes) === 0)))) {

			// The search engine has not been selected.

			return $this->httpError(404);
		}

		// The analytics require the time taken.

		$time = microtime(true);

		// Determine whether search parameters have been passed through.

		if(!isset($data['Search'])) {
			$data['Search'] = '';
		}
		if(!isset($data['SortBy']) || !$data['SortBy']) {
			$data['SortBy'] = $page->SortBy;
		}
		if(!isset($data['SortDirection']) || !$data['SortDirection']) {
			$data['SortDirection'] = $page->SortDirection;
		}
		$request = $this->getRequest();
		if(!isset($form)) {
			$this->getForm($request);
		}

		// Instantiate some default templates.

		$templates = array(
			'ExtensibleSearch',
			'ExtensibleSearchPage',
			'Page'
		);

		// Determine the search engine that has been selected.

		if($engine !== 'Full-Text') {

			// Determine the search engine specific search results.

			$results = array(
				'Results' => null
			);
			foreach($this->extension_instances as $instance) {
				if(get_class($instance) === "{$engine}_Controller") {
					$instance->setOwner($this);
					if(method_exists($instance, 'getSearchResults')) {

						// The analytics require the time taken.

						$time = microtime(true);
						$results = $instance->getSearchResults($data, $form);

						// The search results format needs to be correct.

						if(!isset($results['Results'])) {
							$results = array(
								'Results' => $results
							);
						}
					}
					$instance->clearOwner();
					break;
				}
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
			$_GET['start'] = 0;
			$list = $form->getResults(PHP_INT_MAX, $data)->getList();

			// The search engine may only support limited hierarchy filtering for multiple sites.

			$filter = $page->SearchTrees()->column();
			if(count($filter) && (($hierarchy = $page::$supports_hierarchy) || ClassInfo::exists('Multisites'))) {

				// Apply the search trees filtering.

				$list = $list->filter($hierarchy ? 'ParentID' : 'SiteID', $filter);
			}

			// Apply the sorting.

			$list = $list->sort("{$data['SortBy']} {$data['SortDirection']}");

			// The paginated list needs to be instantiated again.

			$results = array(
				'Title' => 'Search Results',
				'Query' => $form->getSearchQuery($data),
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

		// Determine whether analytics are to be suppressed.

		if($request->getVar('analytics') !== 'false') {

			// Update the search page specific analytics.

			$this->service->logSearch($data['Search'], $count, microtime(true) - $time, $engine, $page->ID);
		}

		// Display the search form results.

		return $output;
	}

}
