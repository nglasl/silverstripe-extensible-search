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

	private static $db = array(
		'SearchEngine'						=> 'Varchar(255)',
		'ResultsPerPage'					=> 'Int',
		'SortBy'							=> "Varchar(64)",
		'SortDir'							=> "Enum('Ascending,Descending')",
		'QueryType'							=> 'Varchar',
		'StartWithListing'					=> 'Boolean',			// whether to start display with a *:* search
		'SearchType'						=> 'MultiValueField',	// types that a user can search within
		'SearchOnFields'					=> 'MultiValueField',
		'BoostFields'						=> 'MultiValueField',
		'BoostMatchFields'					=> 'MultiValueField',

		// faceting fields
		'FacetFields'						=> 'MultiValueField',
		'CustomFacetFields'					=> 'MultiValueField',
		'FacetMapping'						=> 'MultiValueField',
		'FacetQueries'						=> 'MultiValueField',
		'MinFacetCount'						=> 'Int',

		// filter fields (not used for relevance, just for restricting data set)
		'FilterFields'						=> 'MultiValueField',

		// not a has_one, because we may not have the listing page module
		'ListingTemplateID'					=> 'Int',
	);

	// Define the DB fields that are supported by full-text search customisation.

	public static $support = array(
		'ResultsPerPage'					=> 1,
		'SortBy'							=> 1,
		'SortDir'							=> 1,
		'QueryType'							=> 0,
		'StartWithListing'					=> 1,
		'SearchType'						=> 0,
		'SearchOnFields'					=> 0,
		'BoostFields'						=> 0,
		'BoostMatchFields'					=> 0,
		'FacetFields'						=> 0,
		'CustomFacetFields'					=> 0,
		'FacetMapping'						=> 0,
		'FacetQueries'						=> 0,
		'MinFacetCount'						=> 0,
		'FilterFields'						=> 0,
		'ListingTemplateID'					=> 1,
		'SearchTrees'						=> 1
	);

	// The default full-text search string that will be used to return all "start with listing" results.

	public $default_search = '';

	private static $many_many = array(
		'SearchTrees'			=> 'Page',
	);

	private static $defaults = array(
		'ShowInMenus'			=> 0
	);
	public static $additional_search_types = array();

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css(EXTENSIBLE_SEARCH_PATH . '/css/extensible-search.css');
		Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-approval.js');

		// Determine if full text search is enabled.

		$engines = array(
			'' => ''
		);
		$searchable = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_array($searchable) && (count($searchable) > 0)) {
			$engines['Full-Text'] = 'Full-Text';
		}

		// Retrieve a list of search engine extensions currently applied that end with 'Search'.

		$extensions = $this->get_extensions(get_class());
		foreach($extensions as $extension) {
			$reversed = strrev($extension);
			if(strpos($reversed, strrev('Search')) === 0) {
				$engine = strrev(substr($reversed, 6));
				$engines[$engine] = $engine;
			}
		}

		// Allow selection of the search engine extension to use.

		$fields->addFieldToTab('Root.Main', new DropdownField('SearchEngine', 'Search Engine', $engines), 'Content');

		// Make sure a search engine is being used before allowing customisation.

		if($this->SearchEngine) {

			// Construct the support array to determine the CMS customisation available to the current search engine/wrapper.

			$support = self::$support;
			if(($this->SearchEngine !== 'Full-Text') && $this->extension_instances) {
				$engine = "{$this->SearchEngine}Search";
				foreach($this->extension_instances as $instance) {
					if((get_class($instance) === $engine)) {
						$instance->setOwner($this);
						if(isset($instance::$support)) {
							$support = array_merge($support, $instance::$support);
						}
						$instance->clearOwner();
						break;
					}
				}
			}

			// Use the support array to determine the CMS customisation available to the current search engine/wrapper.

			if($support['StartWithListing']) {
				$fields->addFieldToTab('Root.Main', new CheckboxField('StartWithListing', _t('ExtensibleSearchPage.START_LISTING', 'Display initial listing - useful for filterable "data type" lists')), 'Content');
			}

			if (class_exists('ListingTemplate') && $support['ListingTemplateID']) {
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

			if($support['ResultsPerPage']) {
				$perPage = array('5' => '5', '10' => '10', '15' => '15', '20' => '20');
				$fields->addFieldToTab('Root.Main',new DropdownField('ResultsPerPage', _t('ExtensibleSearchPage.RESULTS_PER_PAGE', 'Results per page'), $perPage), 'Content');
			}

			if($support['SearchTrees']) {
				$fields->addFieldToTab('Root.Main', new TreeMultiselectField('SearchTrees', 'Restrict results to these subtrees', 'Page'), 'Content');
			}

			if (!$this->SortBy) {
				$this->SortBy = 'Created';
			}
			$objFields = $this->getSelectableFields();

			if($support['SortBy']) {
				$sortFields = $objFields;

				// Remove content and groups from being sortable (as they are not relevant).

				unset($sortFields['Content']);
				unset($sortFields['Groups']);
				$fields->addFieldToTab('Root.Main', new DropdownField('SortBy', _t('ExtensibleSearchPage.SORT_BY', 'Sort By'), $sortFields), 'Content');
			}
			if($support['SortDir']) {
				$fields->addFieldToTab('Root.Main', new DropdownField('SortDir', _t('ExtensibleSearchPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()), 'Content');
			}

			$types = SiteTree::page_type_classes();
			$source = array_combine($types, $types);

			if(($this->SearchEngine !== 'Full-Text') && $this->extension_instances) {
				$engine = "{$this->SearchEngine}Search";
				foreach($this->extension_instances as $instance) {
					if((get_class($instance) === $engine)) {
						$instance->setOwner($this);

						// Trigger the following methods on the current search engine extension.

						if(method_exists($instance, 'updateSource')) {
							// add in any explicitly configured
							asort($source);
							$instance->updateSource($source);
						}
						if(method_exists($instance, 'getQueryBuilders') && $support['QueryType']) {
							$parsers = $instance->getQueryBuilders();
							$options = array();
							foreach ($parsers as $key => $objCls) {
								$obj = new $objCls;
								$options[$key] = $obj->title;
							}

							$fields->addFieldToTab('Root.Main', new DropdownField('QueryType', _t('ExtensibleSearchPage.QUERY_TYPE', 'Query Type'), $options), 'Content');
						}
						$instance->clearOwner();
						break;
					}
				}
			}

			if($support['SearchType']) {
				ksort($source);
				$source = array_merge($source, self::$additional_search_types);
				$types = new MultiValueDropdownField('SearchType', _t('ExtensibleSearchPage.SEARCH_ITEM_TYPE', 'Search items of type'), $source);
				$fields->addFieldToTab('Root.Main', $types, 'Content');
			}

			if($support['SearchOnFields']) {
				$fields->addFieldToTab('Root.Main', new MultiValueDropdownField('SearchOnFields', _t('ExtensibleSearchPage.INCLUDE_FIELDS', 'Search On Fields'), $objFields), 'Content');
			}

			$boostVals = array();
			for ($i = 1; $i <= 5; $i++) {
				$boostVals[$i] = $i;
			}

			if($support['BoostFields']) {
				$fields->addFieldToTab(
					'Root.Main',
					new KeyValueField('BoostFields', _t('ExtensibleSearchPage.BOOST_FIELDS', 'Boost values'), $objFields, $boostVals),
					'Content'
				);
			}

			if($support['BoostMatchFields']) {
				$fields->addFieldToTab(
					'Root.Main',
					$f = new KeyValueField('BoostMatchFields', _t('ExtensibleSearchPage.BOOST_MATCH_FIELDS', 'Boost fields with field/value matches'), array(), $boostVals),
					'Content'
				);
				$f->setRightTitle('Enter a field name, followed by the value to boost if found in the result set, eg "title:Home" ');
			}

			if($support['FilterFields']) {
				$fields->addFieldToTab(
					'Root.Main',
					$kv = new KeyValueField('FilterFields', _t('ExtensibleSearchPage.FILTER_FIELDS', 'Fields to filter by')),
					'Content'
				);
			}

			if($this->SearchEngine !== 'Full-Text') {
				$fields->addFieldToTab('Root.Main', new HeaderField('FacetHeader', _t('ExtensibleSearchPage.FACET_HEADER', 'Facet Settings')), 'Content');
			}

			if($support['FacetFields']) {
				$fields->addFieldToTab(
					'Root.Main',
					new MultiValueDropdownField('FacetFields', _t('ExtensibleSearchPage.FACET_FIELDS', 'Fields to create facets for'), $objFields),
					'Content'
				);
			}

			if($support['CustomFacetFields']) {
				$fields->addFieldToTab(
					'Root.Main',
					new MultiValueTextField('CustomFacetFields', _t('ExtensibleSearchPage.CUSTOM_FACET_FIELDS', 'Additional fields to create facets for')),
					'Content'
				);
			}

			if($support['FacetMapping']) {
				$facetMappingFields = $objFields;
				if ($this->CustomFacetFields && ($cff = $this->CustomFacetFields->getValues())) {
					foreach ($cff as $facetField) {
						$facetMappingFields[$facetField] = $facetField;
					}
				}
				$fields->addFieldToTab(
					'Root.Main',
					new KeyValueField('FacetMapping', _t('ExtensibleSearchPage.FACET_MAPPING', 'Mapping of facet title to nice title'), $facetMappingFields),
					'Content'
				);
			}

			if($support['FacetQueries']) {
				$fields->addFieldToTab(
					'Root.Main',
					new KeyValueField('FacetQueries', _t('ExtensibleSearchPage.FACET_QUERIES', 'Fields to create query facets for')),
					'Content'
				);
			}

			if($support['MinFacetCount']) {
				$fields->addFieldToTab('Root.Main',
					new NumericField('MinFacetCount', _t('ExtensibleSearchPage.MIN_FACET_COUNT', 'Minimum facet count for inclusion in facet results'), 2),
					'Content'
				);
			}

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

			// Make sure the user search details are correctly sorted for result validation.

			$log = ExtensibleSearch::get();

			// Determine the unique search terms.

			$grouped = GroupedList::create($log)->GroupedBy('Term');
			$output = ArrayList::create();
			foreach($grouped as $search) {
				$searches = $search->toMap();
				$searches = $searches['Children'];

				// Determine the number of duplicate search terms, and the average search time.

				$count = 0;
				$averageTime = 0;
				foreach($searches as $entry) {
					$count++;
					$averageTime += $entry->Time;
				}
				$search->setField('Frequency', $count);
				$search->setField('AverageTimeTaken', round($averageTime / $count, 5));

				// Determine the result validation.

				$search->setField('Validation', ($searches[0]->Results > 0) ? 'true' : 'false');

				// Cast the array data to a temporary data object, so we may export.

				$object = DataObject::create();
				foreach($search->toMap() as $attribute => $value) {
					$object->$attribute = $value;
				}
				$output->push($object);
			}

			// Instantiate the search analytic summary display.

			$fields->addFieldToTab('Root.SearchAnalytics', $summary = GridField::create(
				'Summary',
				'Summary',
				$output->sort(array(
					'Frequency' => 'DESC',
					'Term'
				))
			)->setModelClass('ExtensibleSearch'));
			$summaryConfiguration = $summary->getConfig();
			$summaryConfiguration->removeComponentsByType('GridFieldFilterHeader');
			$summaryConfiguration->addComponent($summaryExport = new GridFieldExportButton());

			// Update the export fields, since we're not using a data list.

			$summaryDisplay = array(
				'Term' => 'Search Term',
				'Frequency' => 'Frequency',
				'AverageTimeTaken' => 'Average Time Taken (s)',
				'Validation' => 'Has Results?'
			);
			$summaryExport->setExportColumns($summaryDisplay);

			// Update the summary fields.

			$summaryConfiguration->getComponentByType('GridFieldDataColumns')->setDisplayFields($summaryDisplay);

			// Instantiate the search analytic history display.

			$fields->addFieldToTab('Root.SearchAnalytics', $history = GridField::create(
				'History',
				'History',
				$log
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
				ExtensibleSearchSuggestion::get(),
				$suggestionsConfiguration = GridFieldConfig_RecordEditor::create()
			)->setModelClass('ExtensibleSearchSuggestion'));
			$suggestionsConfiguration->removeComponentsByType('GridFieldFilterHeader');

			// Update the custom summary fields to be sortable.

			$suggestionsConfiguration->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
				'FrequencySummary' => 'Frequency',
				'ApprovedField' => 'Approved'
			));
		}
		return $fields;
	}

	/**
	 * Return the fields that can be selected for sorting operations.
	 *
	 * @param String $listType
	 * @return array
	 */
	public function getSelectableFields($listType = null, $excludeGeo = true) {

		// Attempt to trigger this method on the current search engine extension instead.

		if(($this->SearchEngine !== 'Full-Text') && $this->extension_instances) {
			$engine = "{$this->SearchEngine}Search";
			foreach($this->extension_instances as $instance) {
				if((get_class($instance) === $engine)) {
					$instance->setOwner($this);
					if(method_exists($instance, 'getSelectableFields')) {
						return $instance->getSelectableFields($listType, $excludeGeo);
					}
					$instance->clearOwner();
					break;
				}
			}
		}

		// Complete this method as normal.

		if (!$listType) {
			$listType = $this->searchableTypes('Page');
		}

		$availableFields = $this->getAllSearchableFieldsFor($listType);
		$objFields = array_combine(array_keys($availableFields), array_keys($availableFields));
		$objFields['LastEdited'] = 'LastEdited';
		$objFields['Created'] = 'Created';
		$objFields['ID'] = 'ID';
		$objFields['score'] = 'Score';

		ksort($objFields);
		return $objFields;
	}

	/**
	 * Get all the searchable fields for a given set of classes
	 * @param type $classNames
	 */
	public function getAllSearchableFieldsFor($classNames) {
		$allfields = array();
		foreach ($classNames as $className) {
			$sng = null;
			if (is_object($className)) {
				$sng = $className;
				$className = get_class($className);
			}
			if (!$sng) {
				$sng = singleton($className);
			}
			$fields = $sng->searchableFields();
			$allfields = array_merge($allfields, $fields);
		}

		return $allfields;
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

	/**
	 * Get the list of field -> query items to be used for faceting by query
	 */
	public function queryFacets() {
		$fields = array();
		if ($this->FacetQueries && $fq = $this->FacetQueries->getValues()) {
			$fields = array_flip($fq);
		}
		return $fields;
	}

	/**
	 * Returns a url parameter string that was just used to execute the current query.
	 *
	 * This is useful for ensuring the parameters used in the search can be passed on again
	 * for subsequent queries.
	 *
	 * @param array $exclusions
	 *			A list of elements that should be excluded from the final query string
	 *
	 * @return String
	 */
	function SearchQuery() {
		$parts = parse_url($_SERVER['REQUEST_URI']);
		if(!$parts) {
			throw new InvalidArgumentException("Can't parse URL: " . $uri);
		}

		// Parse params and add new variable
		$params = array();
		if(isset($parts['query'])) {
			parse_str($parts['query'], $params);
			if (count($params)) {
				return http_build_query($params);
			}
		}
	}

	/**
	 * get the list of types that we've selected to search on
	 */
	public function searchableTypes($default = null) {
		$listType = $this->SearchType ? $this->SearchType->getValues() : null;
		if (!$listType) {
			$listType = $default ? array($default) : null;
		}
		return $listType;
	}

}

class ExtensibleSearchPage_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'getForm',
		'getSearchResults',
		'results',
		'getSuggestions'
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

			$_GET['Search'] = $this->data()->default_search;

			// Construct the default search string used for the current engine/wrapper.

			if(($this->SearchEngine !== 'Full-Text') && $this->data()->extension_instances) {
				$engine = "{$this->SearchEngine}Search";
				foreach($this->data()->extension_instances as $instance) {
					if((get_class($instance) === $engine)) {
						$instance->setOwner($this);
						if(isset($instance->default_search)) {
							$_GET['Search'] = $instance->default_search;
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

		// Display the error page for the given status code.

		if($response = ErrorPage::response_for($code)) {
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
			TextField::create('Search', _t('SearchForm.SEARCH', 'Search'), isset($_GET['Search']) ? $_GET['Search'] : '')->addExtraClass('extensible-search')->setAttribute('data-suggestions-enabled', Config::inst()->get('ExtensibleSearchSuggestion', 'enable_suggestions') ? 'true' : 'false')
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
				$field = ltrim(preg_replace('/[A-Z]+[^A-Z]/', ' $0', $field));
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
			$extension = "{$engine}Search_Controller";
			foreach($this->extension_instances as $instance) {
				if((get_class($instance) === $extension)) {
					$instance->setOwner($this);
					if(method_exists($instance, 'getSearchResults')) {

						// Keep track of the search time taken, for the current search engine extension.

						$startTime = microtime(true);
						$customisation = $instance->getSearchResults($data, $form);
						$output = $this->customise($customisation)->renderWith(array("{$engine}Search_results", "{$engine}SearchPage_results", 'ExtensibleSearch_results', 'ExtensibleSearchPage_results', 'Page_results', "{$engine}Search", "{$engine}SearchPage", 'ExtensibleSearch', 'ExtensibleSearchPage', 'Page'));
						$totalTime = microtime(true) - $startTime;

						// Log the details of a user search for analytics.

						$this->service->logSearch($data['Search'], (isset($customisation['Results']) && ($results = $customisation['Results'])) ? count($results) : 0, $totalTime, $engine);
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
		if($filter) {
			$filter = "ParentID IN({$filter})";
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

		$this->service->logSearch($data['Search'], $results ? count($results) : 0, $totalTime, $engine);
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

	/**
	 *	Retrieve the most relevant search suggestions that have been approved.
	 */

	public function getSuggestions($request) {

		$term = $request->getVar('term');
		$suggestions = ExtensibleSearchSuggestion::get()->filter(array(
			'Term:StartsWith' => $term,
			'Approved' => 1
		))->limit(5);

		// Retrieve these search suggestions as JSON.

		$this->getResponse()->addHeader('Content-Type', 'application/json');
		return Convert::raw2json($suggestions->column('Term'));
	}

}
