<?php

/**
 *	The page used to display search results, allowing user customisation and developer extension, including analytics and suggestions.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchPage extends Page {

	/**
	 *	The listing template relationship is manually defined, as the listing page module may not be present.
	 */

	private static $db = array(
		'SearchEngine' => 'Varchar(255)',
		'SortBy' => 'Varchar(255)',
		'SortDirection' => "Enum('DESC, ASC', 'DESC')",
		'StartWithListing' => 'Boolean',
		'ResultsPerPage' => 'Int',
		'ListingTemplateID' => 'Int'
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
	 *	The custom search engines that may be selected.
	 */

	private static $search_engine_extensions = array(
	);

	/**
	 *	The full-text search engine does not support hierarchy filtering.
	 */

	public static $supports_hierarchy = false;

	/**
	 *	The process to automatically create a search page by default, executed on project build.
	 */

	public function requireDefaultRecords() {

		parent::requireDefaultRecords();
		$mode = Versioned::get_reading_mode();
		Versioned::reading_stage('Stage');

		// Determine whether the search page can be created.

		if(self::config()->create_default_pages) {

			// Determine whether a search page already exists.

			if(!ExtensibleSearchPage::get()->first()) {

				// Instantiate a search page.

				$page = ExtensibleSearchPage::create();
				$page->Title = 'Search Page';
				$page->writeToStage('Stage');
				DB::alteration_message('"Default" Extensible Search Page', 'created');
			}
		}

		// This is required to support multiple sites.

		else if(ClassInfo::exists('Multisites')) {
			foreach(Site::get() as $site) {

				// Determine whether a search page already exists.

				if(!ExtensibleSearchPage::get()->filter('SiteID', $site->ID)->first()) {

					// Instantiate a search page.

					$page = ExtensibleSearchPage::create();
					$page->ParentID = $site->ID;
					$page->Title = 'Search Page';
					$page->writeToStage('Stage');
					DB::alteration_message("\"{$site->Title}\" Extensible Search Page", 'created');
				}
			}
		}
		Versioned::set_reading_mode($mode);
	}

	/**
	 *	Display the CMS search page configuration, search engine listing, analytics and suggestions.
	 */

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css(EXTENSIBLE_SEARCH_PATH . '/css/extensible-search.css');

		// Restrict the search suggestion functionality appropriately.

		$user = Member::currentUserID();
		if(Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS')) {
			Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-approval.js');
		}

		// Instantiate the search engine listing that will be available.

		$engines = array();

		// Determine the custom search engines.

		foreach(self::config()->search_engine_extensions as $extension => $display) {

			// The configuration may define an optional display title.

			if(is_numeric($extension)) {
				$extension = $display;
			}

			// Determine whether the custom search engine extensions have been applied correctly.

			if(ClassInfo::exists($extension) && ClassInfo::exists("{$extension}_Controller") && $this->hasExtension($extension) && ModelAsController::controller_for($this)->hasExtension("{$extension}_Controller")) {
				$engines[$extension] = $display;
			}
		}

		// Determine whether the full-text search engine has been enabled.

		$configuration = Config::inst();
		$classes = $configuration->get('FulltextSearchable', 'searchable_classes');
		if($count = (is_array($classes) && (count($classes) > 0))) {
			$engines['Full-Text'] = 'Full-Text';
		}

		// Display the search engine selection.

		$fields->addFieldToTab('Root.Main', DropdownField::create(
			'SearchEngine',
			'Search Engine',
			$engines
		)->setHasEmptyDefault(true)->setRightTitle('This will need to be saved before further customisation is available'), 'Title');

		// Determine whether a search engine has been selected.

		if($this->SearchEngine && ((($this->SearchEngine !== 'Full-Text') && ClassInfo::exists($this->SearchEngine)) || (($this->SearchEngine === 'Full-Text') && $count))) {

			// Determine whether the search engine supports hierarchy.

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

			// The search engine will only support limited hierarchy for multiple sites.

			if($hierarchy || ClassInfo::exists('Multisites')) {

				// Display the search trees selection.

				$fields->addFieldToTab('Root.Main', $tree = TreeMultiselectField::create(
					'SearchTrees',
					'Search Trees',
					'SiteTree'
				), 'Content');
				if(!$hierarchy) {

					// Update the selection to reflect the limited hierarchy for multiple sites.

					$tree->setDisableFunction(function($page) {

						return ($page->ParentID != 0);
					});
					$tree->setRightTitle('The selected <strong>search engine</strong> only supports limited hierarchy');
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

			// Allow listing template configuration when the listing page module is present.

			if(ClassInfo::exists('ListingTemplate')) {

				// Determine the listing templates that exist.

				$templates = ListingTemplate::get()->map();

				// Display the listing template selection.

				$fields->addFieldToTab('Root.Main', DropdownField::create(
					'ListingTemplateID',
					'Listing Template',
					$templates
				)->setHasEmptyDefault(true), 'Content');
			}
		}
		else {

			// The search engine will need to be selected before customisation is available.

			$fields->addFieldToTab('Root.Main', LiteralField::create(
				'SearchEngineNotification',
				"<p class='extensible-search notification'><strong>Select a Search Engine</strong></p>"
			), 'SearchEngine');
		}

		// Determine whether analytics have been enabled.

		if($configuration->get('ExtensibleSearch', 'enable_analytics')) {

			// Retrieve the extensible search analytics.

			$history = $this->History();
			$query = new SQLQuery(
				"Term, COUNT(*) AS Frequency, ((COUNT(*) * 100.00) / {$history->count()}) AS FrequencyPercentage, AVG(Time) AS AverageTimeTaken, (Results > 0) AS Results",
				'ExtensibleSearch',
				"ExtensibleSearchPageID = {$this->ID}",
				array(
					'Frequency' => 'DESC',
					'Term' => 'ASC'
				),
				'Term'
			);
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

			// Instantiate the search analytic summary display.

			$fields->addFieldToTab('Root.SearchAnalytics', $summary = GridField::create(
				'Summary',
				'Summary',
				$analytics
			)->setModelClass('ExtensibleSearch'));
			$summaryConfiguration = $summary->getConfig();

			// Update the display columns, and instantiate an export button.

			$summaryDisplay = array(
				'Term' => 'Search Term',
				'Frequency' => 'Frequency',
				'FrequencyPercentage' => 'Frequency %',
				'AverageTimeTaken' => 'Average Time Taken (s)',
				'Results' => 'Has Results?'
			);
			$summaryConfiguration->getComponentByType('GridFieldDataColumns')->setDisplayFields($summaryDisplay);
			$summaryConfiguration->addComponent($summaryExport = new GridFieldExportButton());
			$summaryExport->setExportColumns($summaryDisplay);

			// Update the custom summary fields to be sortable.

			$summaryConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'FrequencyPercentage' => 'Frequency'
			));
			$summaryConfiguration->removeComponentsByType('GridFieldFilterHeader');

			// Instantiate the search analytic history display.

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
				'TimeTakenSummary' => 'Time'
			));
			$historyConfiguration->removeComponentsByType('GridFieldFilterHeader');
		}

		// Determine whether suggestions have been enabled.

		if($configuration->get('ExtensibleSearchSuggestion', 'enable_suggestions')) {

			// Instantiate the search suggestion display.

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

		// Allow extension.

		$this->extend('updateExtensibleSearchPageCMSFields', $fields);
		return $fields;
	}

	/**
	 *	Initialise the default sorting.
	 */

	public function onBeforeWrite() {

		parent::onBeforeWrite();

		// Determine whether the default sorting needs to be initialised.

		$changed = $this->getChangedFields();
		if($this->SearchEngine && isset($changed['SearchEngine']) && ($changed['SearchEngine']['before'] != $changed['SearchEngine']['after'])) {

			// The sorting will need to be present in the selectable fields.

			$selectable = $this->getSelectableFields();
			if(!isset($selectable[$this->SortBy])) {

				// The sorting was not present, therefore initialise a default.

				$this->SortBy = ($this->SearchEngine !== 'Full-Text') ? 'LastEdited' : 'Relevance';
			}
		}
		if(!$this->SortDirection) {
			$this->SortDirection = 'DESC';
		}
	}

	/**
	 *	Determine the search engine specific selectable fields.
	 *
	 *	@return array(string, string)
	 */

	public function getSelectableFields() {

		// Instantiate the default selectable fields, just in case the selected search engine does not provide any.

		$selectable = array(
			'LastEdited' => 'Last Edited',
			'ID' => 'Created'
		);

		// Determine the search engine.

		if(($this->SearchEngine !== 'Full-Text') && ClassInfo::exists($this->SearchEngine)) {

			// Determine the custom search engine specific selectable fields.

			foreach($this->extension_instances as $instance) {
				if(get_class($instance) === $this->SearchEngine) {
					$instance->setOwner($this);
					$fields = method_exists($instance, 'getSelectableFields') ? $instance->getSelectableFields() : array();
					return $fields + $selectable;
				}
			}
		}
		else if(($this->SearchEngine === 'Full-Text') && is_array($classes = Config::inst()->get('FulltextSearchable', 'searchable_classes')) && (count($classes) > 0)) {
			$selectable = array(
				'Relevance' => 'Relevance'
			) + $selectable;

			// Determine the full-text specific selectable fields.

			foreach($classes as $class) {
				$fields = DataObject::database_fields($class);

				// Determine the most appropriate fields.

				if(isset($fields['Title'])) {
					$selectable['Title'] = 'Title';
				}
				if(isset($fields['MenuTitle'])) {
					$selectable['MenuTitle'] = 'Navigation Title';
				}
				if(isset($fields['Sort'])) {
					$selectable['Sort'] = 'Display Order';
				}
			}
		}

		// Allow extension, so custom fields may be selectable.

		$this->extend('updateExtensibleSearchPageSelectableFields', $selectable);
		return $selectable;
	}

}

class ExtensibleSearchPage_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'getForm',
		'getSearchResults',
		'results'
	);

	public $service;

	private static $dependencies = array(
		'service' => '%$ExtensibleSearchService'
	);

	public function index() {

		// Don't allow searching without a valid search engine.

		$engine = $this->data()->SearchEngine;
		$fulltext = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_null($engine) || (($engine !== 'Full-Text') && !ClassInfo::exists($engine)) || (($engine === 'Full-Text') && (!is_array($fulltext) || (count($fulltext) === 0)))) {
			return $this->httpError(404);
		}

		// This default search listing will be displayed when the search page has loaded.

		if ($this->StartWithListing) {
			$_GET['SortBy'] = isset($_GET['SortBy']) ? $_GET['SortBy'] : $this->data()->SortBy;
			$_GET['SortDirection'] = isset($_GET['SortDirection']) ? $_GET['SortDirection'] : $this->data()->SortDirection;

			// The default full-text search string to return all results.

			$data = $this->data();
			$_GET['Search'] = '';
			return $this->getSearchResults($_GET, $this->getForm());
		}
		return array();
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

	public function getForm($filters = true) {

		// Don't allow searching without a valid search engine.

		$engine = $this->data()->SearchEngine;
		$fulltext = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_null($engine) || (($engine === 'Full-Text') && (!is_array($fulltext) || (count($fulltext) === 0)))) {
			return null;
		}

		// Construct the search form.

		$fields = new FieldList(
			TextField::create('Search', _t('SearchForm.SEARCH', 'Search'), isset($_GET['Search']) ? $_GET['Search'] : '')->addExtraClass('extensible-search search')->setAttribute('data-suggestions-enabled', Config::inst()->get('ExtensibleSearchSuggestion', 'enable_suggestions') ? 'true' : 'false')->setAttribute('data-extensible-search-page', $this->data()->ID)
		);

		// When filters have been enabled, display these in the form.

		if($filters) {
			$objFields = $this->data()->getSelectableFields();

			// Remove content and groups from being sortable (as they are not relevant).

			unset($objFields['Content']);
			unset($objFields['Groups']);

			// Remove any custom field types and display the sortable options nicely to the user.

			foreach($objFields as &$field) {
				if($customType = strpos($field, ':')) {
					$field = substr($field, 0, $customType);
				}

				// Add spaces between words, other characters and numbers.

				$field = ltrim(preg_replace(array(
					'/([A-Z][a-z]+)/',
					'/([A-Z]{2,})/',
					'/([_.0-9]+)/'
				), ' $0', $field));
			}
			$sortBy = isset($_GET['SortBy']) ? $_GET['SortBy'] : $this->data()->SortBy;
			$sorting = array(
				'DESC' => 'Descending',
				'ASC' => 'Ascending'
			);
			$sortDir = isset($_GET['SortDirection']) ? $_GET['SortDirection'] : $this->data()->SortDirection;
			$fields->push(new DropdownField('SortBy', _t('ExtensibleSearchPage.SORT_BY', 'Sort By'), $objFields, $sortBy));
			$fields->push(new DropdownField('SortDirection', _t('ExtensibleSearchPage.SORT_DIR', 'Sort Direction'), $sorting, $sortDir));
		}

		$actions = new FieldList(new FormAction('getSearchResults', _t('SearchForm.GO', 'Search')));

		$form = new SearchForm($this, 'getForm', $fields, $actions);
		$searchable = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_array($searchable) && (count($searchable) > 0)) {
			$form->classesToSearch($searchable);
		}
		$form->addExtraClass('searchPageForm');
		$form->setFormMethod('GET');
		$form->disableSecurityToken();
		return $form;
	}

	/**
	 * Process and render search results (taken from @Link ContentControllerSearchExtension with slightly altered parameters).
	 *
	 * @param array $data The raw request data submitted by user
	 * @param SearchForm $form The form instance that was submitted
	 */
	public function getSearchResults($data = null, $form = null) {

		// Keep track of the search time taken.

		$startTime = microtime(true);

		// Don't allow searching without a valid search engine.

		$engine = $this->data()->SearchEngine;
		$fulltext = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_null($engine) || (($engine !== 'Full-Text') && !ClassInfo::exists($engine)) || (($engine === 'Full-Text') && (!is_array($fulltext) || (count($fulltext) === 0)))) {
			return $this->httpError(404);
		}

		// Attempt to retrieve the results for the current search engine extension.

		if(($engine !== 'Full-Text') && $this->extension_instances) {
			foreach($this->extension_instances as $instance) {
				if(get_class($instance) === "{$engine}_Controller") {
					$instance->setOwner($this);
					if(method_exists($instance, 'getSearchResults')) {

						// Keep track of the search time taken, for the current search engine extension.

						$startTime = microtime(true);
						$customisation = $instance->getSearchResults($data, $form);
						$output = $this->customise($customisation)->renderWith(array("{$engine}_results", "{$engine}Page_results", 'ExtensibleSearch_results', 'ExtensibleSearchPage_results', 'Page_results', "{$engine}", "{$engine}Page", 'ExtensibleSearch', 'ExtensibleSearchPage', 'Page'));
						$totalTime = microtime(true) - $startTime;

						// Log the details of a user search for analytics.

						$this->service->logSearch($data['Search'], (isset($customisation['Results']) && ($results = $customisation['Results'])) ? count($results) : 0, $totalTime, $engine, $this->data()->ID);
						return $output;
					}
					$instance->clearOwner();
					break;
				}
			}
		}

		// Fall back to displaying the full-text results.

		$searchable = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		// these should use what's in $data
		if(is_null($sort = $this->data()->SortBy)) {
			$sort = 'Relevance';
		}
		$direction = $this->data()->SortDirection;

		// Apply any site tree restrictions.

		$filter = $this->SearchTrees()->column();
		$page = $this->data();
		$support = $page::$supports_hierarchy;

		// Determine whether the current engine/wrapper supports hierarchy.

		if(($this->data()->SearchEngine !== 'Full-Text') && $this->data()->extension_instances) {
			$engine = $this->data()->SearchEngine;
			foreach($this->data()->extension_instances as $instance) {
				if((get_class($instance) === $engine)) {
					$instance->setOwner($this);
					if(isset($instance::$supports_hierarchy)) {
						$support = $instance::$supports_hierarchy;
					}
					$instance->clearOwner();
					break;
				}
			}
		}
		$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
		$_GET['start'] = 0;
		$results = (is_array($searchable) && (count($searchable) > 0) && $form) ? $form->getResults(null, $data) : null;

		// Apply filters, sorting, and correct the permissions.

		if($results) {
			$items = $results->getList();
			if(count($filter) && ($support || ClassInfo::exists('Multisites'))) {
				$items = $items->filter($support ? 'ParentID' : 'SiteID', $filter);
			}
			$items = $items->sort("{$sort} {$direction}");
			$results = PaginatedList::create($items);
			$results->setPageStart($start);
			$results->setPageLength($this->data()->ResultsPerPage);
			$results->setTotalItems(count($items));
			$results->setLimitItems(true);
		}

		// Render the full-text results using a listing template where defined.
		// should probably check that listing template still exists at this point

		if($this->data()->ListingTemplateID && $results) {
			$template = DataObject::get_by_id('ListingTemplate', $this->data()->ListingTemplateID);
			if($template && $template->exists()) {
				$render = $this->data()->customise(array(
					'Items' => $results
				));
				$viewer = SSViewer::fromString($template->ItemTemplate);
				$results = $viewer->process($render);
			}
		}

		// Render everything into the search page template.

		$customisation = array(
			'Results' => $results,
			'Query' => $form ? $form->getSearchQuery() : null,
			'Title' => _t('ExtensibleSearchPage.SearchResults', 'Search Results')
		);
		$output = $this->customise($customisation)->renderWith(array('ExtensibleSearch_results', 'ExtensibleSearchPage_results', 'Page_results', 'ExtensibleSearch', 'ExtensibleSearchPage', 'Page'));
		$totalTime = microtime(true) - $startTime;

		// Log the details of a user search for analytics.

		$this->service->logSearch($data['Search'], $results ? count($results) : 0, $totalTime, $engine, $this->data()->ID);
		return $output;
	}

	/**
	 * Allow calling by /search/results for displaying a results page.
	 *
	 * @param type $data
	 * @param type $form
	 * @return string
	 */
	public function results($data = null, $form = null) {

		return $this->getSearchResults($data, $form);
	}

}
