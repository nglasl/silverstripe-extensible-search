<?php

/**
 * A page type specifically used for displaying search results.
 *
 * This is an alternative encapsulation of search logic as it comprises much more than the out of the
 * box example. To use this instead of the default implementation, your search form call in Page should first
 * retrieve the ExtensibleSearchPage to use as its context.
 *
 * @author Nathan Glasl <nathan@silverstripe.com.au>
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license http://silverstripe.org/bsd-license/
 */

class ExtensibleSearchPage extends Page {

	// listing template ID is not a has_one, because we may not have the listing page module

	private static $db = array(
		'SearchEngine' => 'Varchar(255)',
		'ResultsPerPage' => 'Int',
		'SortBy' => "Varchar(64)",
		'SortDir' => "Enum('Ascending,Descending')",
		'DisplayForm' => 'Boolean',
		'StartWithListing' => 'Boolean',
		'ListingTemplateID' => 'Int'
	);

	// The default full-text search string that will be used to return all "start with listing" results.

	public static $default_search = '';

	public static $supports_hierarchy = false;

	private static $search_engine_extensions = array();

	private static $has_many = array(
		'History' => 'ExtensibleSearch',
		'Suggestions' => 'ExtensibleSearchSuggestion'
	);

	private static $many_many = array(
		'SearchTrees'			=> 'Page',
	);

	private static $defaults = array(
		'ShowInMenus' => 0,
		'ShowInSearch' => 0
	);

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css(EXTENSIBLE_SEARCH_PATH . '/css/extensible-search.css');

		// Restrict the search suggestion approval appropriately.

		$user = Member::currentUserID();
		if(Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS')) {
			Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-approval.js');
		}

		// Determine if full text search is enabled.

		$engines = array(
			'' => ''
		);
		$searchable = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_array($searchable) && (count($searchable) > 0)) {
			$engines['Full-Text'] = 'Full-Text';
		}

		// Retrieve a list of search engine extensions currently applied that end with 'Search'.

		$extensions = self::config()->search_engine_extensions;
		foreach($extensions as $extension) {
			$exists = ClassInfo::exists($extension) && (ClassInfo::exists("{$extension}Controller") || ClassInfo::exists("{$extension}_Controller"));
			$has = $this->hasExtension($extension) && (ModelAsController::controller_for($this)->hasExtension("{$extension}Controller") || ModelAsController::controller_for($this)->hasExtension("{$extension}_Controller"));
			if($exists && $has) {
				$engines[$extension] = $extension;
			}
		}

		// Allow selection of the search engine extension to use.

		$fields->addFieldToTab('Root.Main', DropdownField::create('SearchEngine', 'Search Engine', $engines)->setRightTitle('The default full-text search engine will be available out of the box'), 'Content');

		// Make sure a search engine is being used before allowing customisation.

		if($this->SearchEngine) {

			// Determine the CMS customisation available to the current search engine/wrapper.

			$fields->addFieldToTab('Root.Main', new CheckboxField('StartWithListing', _t('ExtensibleSearchPage.START_LISTING', 'Display initial listing - useful for filterable "data type" lists')), 'Content');

			if(class_exists('ListingTemplate')) {
				$templates = DataObject::get('ListingTemplate');
				if ($templates) {
					$templates = $templates->map();
				} else {
					$templates = array();
				}

				$label = _t('ExtensibleSearchPage.CONTENT_TEMPLATE', 'Listing Template - if not set, theme template will be used');
				$fields->addFieldToTab('Root.Main', $template = DropdownField::create('ListingTemplateID', $label, $templates, '', null)->setEmptyString('(results template)'), 'Content');
				$template->setEmptyString('(results template)');
			}

			$perPage = array('5' => '5', '10' => '10', '15' => '15', '20' => '20');
			$fields->addFieldToTab('Root.Main',new DropdownField('ResultsPerPage', _t('ExtensibleSearchPage.RESULTS_PER_PAGE', 'Results per page'), $perPage), 'Content');

			if($this->SearchEngine) {
				$support = self::$supports_hierarchy;

				// Determine whether the current engine/wrapper supports hierarchy.

				if(($this->SearchEngine !== 'Full-Text') && $this->data()->extension_instances) {
					$engine = $this->SearchEngine;
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
				if($support || ClassInfo::exists('Multisites')) {
					$fields->addFieldToTab('Root.Main', $tree = TreeMultiselectField::create('SearchTrees', 'Restrict results to these subtrees', 'SiteTree'), 'Content');
					if(!$support) {
						$tree->setDisableFunction(function($page) {
							return ($page->ParentID !== 0);
						});
						$tree->setRightTitle('The selected search engine does not support further restrictions');
					}
				}
			}

			if (!$this->SortBy) {
				$this->SortBy = 'Created';
			}

			$sortFields = $this->getSelectableFields();
			$fields->addFieldToTab('Root.Main', new DropdownField('SortBy', _t('ExtensibleSearchPage.SORT_BY', 'Sort By'), $sortFields), 'Content');
			$fields->addFieldToTab('Root.Main', new DropdownField('SortDir', _t('ExtensibleSearchPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()), 'Content');

			$this->extend('updateExtensibleSearchPageCMSFields', $fields);
		}
		else {
			$fields->addFieldToTab('Root.Main', LiteralField::create(
				'SearchEngineNotification',
				"<p class='extensible-search notification'><strong>Select a Search Engine</strong></p>"
			), 'Title');
		}

		// Retrieve the extensible search analytics, when enabled.

		if(Config::inst()->get('ExtensibleSearch', 'enable_analytics')) {

			// Retrieve the search analytics.

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
				$result = ArrayData::create($result);
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
			$summaryConfiguration->removeComponentsByType('GridFieldFilterHeader');
			$summaryConfiguration->addComponent($summaryExport = new GridFieldExportButton());
			$summaryConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'FrequencyPercentage' => 'Frequency'
			));

			// Update the export fields, since we're not using a data list.

			$summaryDisplay = array(
				'Term' => 'Search Term',
				'Frequency' => 'Frequency',
				'FrequencyPercentage' => 'Frequency %',
				'AverageTimeTaken' => 'Average Time Taken (s)',
				'Results' => 'Has Results?'
			);
			$summaryExport->setExportColumns($summaryDisplay);

			// Update the summary fields.

			$summaryConfiguration->getComponentByType('GridFieldDataColumns')->setDisplayFields($summaryDisplay);

			// Instantiate the search analytic history display.

			$fields->addFieldToTab('Root.SearchAnalytics', $history = GridField::create(
				'History',
				'History',
				$history
			)->setModelClass('ExtensibleSearch'));
			$historyConfiguration = $history->getConfig();
			$historyConfiguration->removeComponentsByType('GridFieldFilterHeader');
			$historyConfiguration->addComponent($historyExport = new GridFieldExportButton());

			// Update the custom summary fields to be sortable.

			$historyConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'TimeSummary' => 'Created',
				'TimeTakenSummary' => 'Time'
			));
		}

		// Retrieve the extensible search suggestions, when enabled.

		if(Config::inst()->get('ExtensibleSearchSuggestion', 'enable_suggestions')) {

			// Instantiate the search suggestion display.

			$fields->addFieldToTab('Root.SearchSuggestions', GridField::create(
				'Suggestions',
				'Suggestions',
				$this->Suggestions(),
				$suggestionsConfiguration = GridFieldConfig_RecordEditor::create()
			)->setModelClass('ExtensibleSearchSuggestion'));
			$suggestionsConfiguration->removeComponentsByType('GridFieldFilterHeader');

			// Update the custom summary fields to be sortable.

			$suggestionsConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'FrequencySummary' => 'Frequency',
				'FrequencyPercentage' => 'Frequency',
				'ApprovedField' => 'Approved'
			));
		}
		return $fields;
	}

	/**
	 * Ensures that there is always a search page
	 * by checking if there's an instance of
	 * a base ExtensibleSearchPage. If there
	 * is not, one is created when the DB is built.
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		if(SiteTree::get_create_default_pages()){
			$page = DataObject::get_one('ExtensibleSearchPage');
			if(!($page && $page->exists())) {
				$page = ExtensibleSearchPage::create();
				$page->Title = _t('ExtensibleSearchPage.DEFAULT_PAGE_TITLE', 'Search Page');
				$page->Content = '';
				$page->ResultsPerPage = 10;
				$page->Status = 'New page';
				$page->write();

				DB::alteration_message('Search page created', 'created');
			}
		}

	}

	public function getSelectableFields() {

		// Attempt to trigger this method on the current search engine extension instead.

		if(($this->SearchEngine !== 'Full-Text') && $this->extension_instances) {
			$engine = $this->SearchEngine;
			foreach($this->extension_instances as $instance) {
				if((get_class($instance) === $engine)) {
					$instance->setOwner($this);
					if(method_exists($instance, 'getSelectableFields')) {
						return $instance->getSelectableFields();
					}
					$instance->clearOwner();
					break;
				}
			}
		}

		// TO DO determine what data objects we're searching on
		// TO DO determine the fields for these data objects
		$sortFields = array();
		$sortFields['LastEdited'] = 'LastEdited';
		$sortFields['Created'] = 'Created';
		$sortFields['ID'] = 'ID';
		$sortFields['Title'] = 'Title';
		ksort($sortFields);
		return $sortFields;
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
		if(is_null($engine) || (($engine === 'Full-Text') && (!is_array($fulltext) || (count($fulltext) === 0)))) {
			return $this->httpError(404);
		}

		// This default search listing will be displayed when the search page has loaded.

		if ($this->StartWithListing) {
			$_GET['SortBy'] = isset($_GET['SortBy']) ? $_GET['SortBy'] : $this->data()->SortBy;
			$_GET['SortDir'] = isset($_GET['SortDir']) ? $_GET['SortDir'] : $this->data()->SortDir;

			// The default full-text search string to return all results.

			$data = $this->data();
			$_GET['Search'] = $data::$default_search;

			// Construct the default search string used for the current engine/wrapper.

			if(($this->data()->SearchEngine !== 'Full-Text') && $this->data()->extension_instances) {
				$engine = $this->data()->SearchEngine;
				foreach($this->data()->extension_instances as $instance) {
					if((get_class($instance) === $engine)) {
						$instance->setOwner($this);
						if(isset($instance::$default_search)) {
							$_GET['Search'] = $instance::$default_search;
						}
						$instance->clearOwner();
						break;
					}
				}
			}
			$this->DefaultListing = true;
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
			return $this->httpError(404);
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
			$sortDir = isset($_GET['SortDir']) ? $_GET['SortDir'] : $this->data()->SortDir;
			$fields->push(new DropdownField('SortBy', _t('ExtensibleSearchPage.SORT_BY', 'Sort By'), $objFields, $sortBy));
			$fields->push(new DropdownField('SortDir', _t('ExtensibleSearchPage.SORT_DIR', 'Sort Direction'), $this->data()->dbObject('SortDir')->enumValues(), $sortDir));
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
		if(is_null($engine) || (($engine === 'Full-Text') && (!is_array($fulltext) || (count($fulltext) === 0)))) {
			return $this->httpError(404);
		}

		// Attempt to retrieve the results for the current search engine extension.

		if(($engine !== 'Full-Text') && $this->extension_instances) {
			foreach($this->extension_instances as $instance) {
				if((get_class($instance) === "{$engine}Controller") || (get_class($instance) === "{$engine}_Controller")) {
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
		if(is_null($sort = $this->data()->SortBy)) {
			$sort = 'Relevance';
		}
		$direction = ($this->data()->SortDir === 'Ascending') ? 'ASC' : 'DESC';

		// Apply any site tree restrictions.

		$filter = implode(', ', $this->SearchTrees()->map('ID', 'ID')->toArray());
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
		if($filter && ($support || ClassInfo::exists('Multisites'))) {
			$field = $support ? 'ParentID' : 'SiteID';
			$filter = "{$field} IN({$filter})";
		}
		$results = (is_array($searchable) && (count($searchable) > 0) && $form) ? $form->getExtendedResults($this->data()->ResultsPerPage, "{$sort} {$direction}", $filter, $data) : null;

		// Render the full-text results using a listing template where defined.

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
