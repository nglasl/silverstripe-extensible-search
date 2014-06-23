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

	public static $db = array(
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

	public static $many_many = array(
		'SearchTrees'			=> 'Page',
	);

	/**
	 *
	 * @var array
	 */
	public static $additional_search_types = array();

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		// Retrieve a list of search engine extensions currently applied that end with 'SearchPage'.

		$extensions = $this->get_extensions(get_class());
		foreach($extensions as $key => &$extension) {
			$reversed = strrev($extension);
			if(strpos($reversed, strrev('SearchPage')) === 0) {
				$extension = strrev(substr($reversed, 10));
			}
			else {
				unset($extensions[$key]);
			}
		}
		$additional = array('');

		// Determine if full text search is enabled.

		$searchable = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(is_array($searchable) && (count($searchable) > 0)) {
			$additional['Full-Text'] = 'Full-Text';
		}

		// Allow selection of the search engine extension to use.

		$fields->addFieldToTab('Root.Main', new DropdownField('SearchEngine', 'Search Engine', array_merge($additional, $extensions)), 'Content');

		// Make sure a search engine is being used before allowing customisation.

		if($this->SearchEngine) {
			$fields->addFieldToTab('Root.Main', new CheckboxField('StartWithListing', _t('ExtensibleSearchPage.START_LISTING', 'Display initial listing - useful for filterable "data type" lists')), 'Content');

			if (class_exists('ListingTemplate')) {
				$templates = DataObject::get('ListingTemplate');
				if ($templates) {
					$templates = $templates->map();
				} else {
					$templates = array();
				}

				$label = _t('ExtensibleSearchPage.CONTENT_TEMPLATE', 'Listing Template - if not set, theme template will be used');
				$fields->addFieldToTab('Root.Main', $template = new DropdownField('ListingTemplateID', $label, $templates, '', null), 'Content');
				$template->setEmptyString('(results template)');
			}

			$perPage = array('5' => '5', '10' => '10', '15' => '15', '20' => '20');
			$fields->addFieldToTab('Root.Main',new DropdownField('ResultsPerPage', _t('ExtensibleSearchPage.RESULTS_PER_PAGE', 'Results per page'), $perPage), 'Content');

			$fields->addFieldToTab('Root.Main', new TreeMultiselectField('SearchTrees', 'Restrict results to these subtrees', 'Page'), 'Content');

			if (!$this->SortBy) {
				$this->SortBy = 'Created';
			}

			$objFields = $this->getSelectableFields();

			// Remove content and groups from being sortable (as they are not relevant).

			$sortFields = $objFields;
			unset($sortFields['Content']);
			unset($sortFields['Groups']);
			$fields->addFieldToTab('Root.Main', new DropdownField('SortBy', _t('ExtensibleSearchPage.SORT_BY', 'Sort By'), $sortFields), 'Content');
			$fields->addFieldToTab('Root.Main', new DropdownField('SortDir', _t('ExtensibleSearchPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()), 'Content');

			$types = SiteTree::page_type_classes();
			$source = array_combine($types, $types);
			asort($source);

			// add in any explicitly configured
			if($this->hasMethod('updateSource')) {
				$this->updateSource($source);
			}

			ksort($source);

			$source = array_merge($source, self::$additional_search_types);

			$types = new MultiValueDropdownField('SearchType', _t('ExtensibleSearchPage.SEARCH_ITEM_TYPE', 'Search items of type'), $source);
			$fields->addFieldToTab('Root.Main', $types, 'Content');

			$fields->addFieldToTab('Root.Main', new MultiValueDropdownField('SearchOnFields', _t('ExtensibleSearchPage.INCLUDE_FIELDS', 'Search On Fields'), $objFields), 'Content');

			if($this->hasMethod('getQueryBuilders')) {
				$parsers = $this->getQueryBuilders();
				$options = array();
				foreach ($parsers as $key => $objCls) {
					$obj = new $objCls;
					$options[$key] = $obj->title;
				}

				$fields->addFieldToTab('Root.Main', new DropdownField('QueryType', _t('ExtensibleSearchPage.QUERY_TYPE', 'Query Type'), $options), 'Content');
			}

			$boostVals = array();
			for ($i = 1; $i <= 5; $i++) {
				$boostVals[$i] = $i;
			}

			$fields->addFieldToTab(
				'Root.Main',
				new KeyValueField('BoostFields', _t('ExtensibleSearchPage.BOOST_FIELDS', 'Boost values'), $objFields, $boostVals),
				'Content'
			);

			$fields->addFieldToTab(
				'Root.Main',
				$f = new KeyValueField('BoostMatchFields', _t('ExtensibleSearchPage.BOOST_MATCH_FIELDS', 'Boost fields with field/value matches'), array(), $boostVals),
				'Content'
			);

			$f->setRightTitle('Enter a field name, followed by the value to boost if found in the result set, eg "title:Home" ');

			$fields->addFieldToTab(
				'Root.Main',
				$kv = new KeyValueField('FilterFields', _t('ExtensibleSearchPage.FILTER_FIELDS', 'Fields to filter by')),
				'Content'
			);

			$fields->addFieldToTab('Root.Main', new HeaderField('FacetHeader', _t('ExtensibleSearchPage.FACET_HEADER', 'Facet Settings')), 'Content');

			$fields->addFieldToTab(
				'Root.Main',
				new MultiValueDropdownField('FacetFields', _t('ExtensibleSearchPage.FACET_FIELDS', 'Fields to create facets for'), $objFields),
				'Content'
			);

			$fields->addFieldToTab(
				'Root.Main',
				new MultiValueTextField('CustomFacetFields', _t('ExtensibleSearchPage.CUSTOM_FACET_FIELDS', 'Additional fields to create facets for')),
				'Content'
			);

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

			$fields->addFieldToTab(
				'Root.Main',
				new KeyValueField('FacetQueries', _t('ExtensibleSearchPage.FACET_QUERIES', 'Fields to create query facets for')),
				'Content'
			);

			$fields->addFieldToTab('Root.Main',
				new NumericField('MinFacetCount', _t('ExtensibleSearchPage.MIN_FACET_COUNT', 'Minimum facet count for inclusion in facet results'), 2),
				'Content'
			);
		}
		else {
			Requirements::css(EXTENSIBLE_SEARCH_PAGE_PATH . '/css/extensible-search-page.css');
			$fields->addFieldToTab('Root.Main', LiteralField::create(
				'SearchEngineNotification',
				"<p class='extensible-search-page notification'><strong>Select a Search Engine</strong></p>"
			), 'Title');
		}

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	/**
	 * Return the fields that can be selected for sorting operations.
	 *
	 * @param String $listType
	 * @return array
	 */
	public function getSelectableFields($listType = null) {
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

			// Make sure that the search page hasn't been inherited.

			if(!($page && $page->exists()) && (count(ClassInfo::subclassesFor('ExtensibleSearchPage')) === 1)) {
				$page = ExtensibleSearchPage::create();
				$page->Title = _t('ExtensibleSearchPage.DEFAULT_PAGE_TITLE', 'Search');
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
		'Form',
		'results',
	);

	public function index() {
		if ($this->StartWithListing) {
			$_GET['SortBy'] = isset($_GET['SortBy']) ? $_GET['SortBy'] : $this->data()->SortBy;
			$_GET['SortDir'] = isset($_GET['SortDir']) ? $_GET['SortDir'] : $this->data()->SortDir;
			$_GET['Search'] = '*:*';
			$this->DefaultListing = true;

			return $this->results();
		}
		return array();
	}

	public function Form() {
		$searchable = Config::inst()->get('FulltextSearchable', 'searchable_classes');
		if(!is_array($searchable) || (count($searchable) === 0)) {
			return null;
		}
		$fields = new FieldList(
			new TextField('Search', _t('ExtensibleSearchPage.SEARCH','Search'), isset($_GET['Search']) ? $_GET['Search'] : '')
		);

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

		$actions = new FieldList(new FormAction('results', _t('ExtensibleSearchPage.DO_SEARCH', 'Search')));

		$form = new SearchForm($this, 'Form', $fields, $actions);
		$form->classesToSearch($searchable);
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
	 * @param SS_HTTPRequest $request Request generated for this action
	 */
	public function results($data = null, $form = null) {
		$data = array(
			'Results' => $form->getResults(),
			'Query' => $form->getSearchQuery(),
			'Title' => _t('ExtensibleSearchPage.SearchResults', 'Search Results')
		);
		return $this->owner->customise($data)->renderWith(array('SearchPage_results', 'Page_results', 'Page'));
	}

}
