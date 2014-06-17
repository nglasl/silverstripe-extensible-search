<?php

/**
 * A page type specifically used for displaying search results.
 *
 * This is an alternative encapsulation of search logic as it comprises much more than the out of the
 * box example. To use this instead of the default implementation, your search form call in Page should first
 * retrieve the SearchPage to use as its context.
 *
 * @author Nathan Glasl <nathan@silverstripe.com.au>
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license http://silverstripe.org/bsd-license/
 */

class SearchPage extends Page {

	public static $db = array(
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

		$fields->addFieldToTab('Root.Main', new CheckboxField('StartWithListing', _t('SearchPage.START_LISTING', 'Display initial listing - useful for filterable "data type" lists')), 'Content');

		if (class_exists('ListingTemplate')) {
			$templates = DataObject::get('ListingTemplate');
			if ($templates) {
				$templates = $templates->map();
			} else {
				$templates = array();
			}

			$label = _t('SearchPage.CONTENT_TEMPLATE', 'Listing Template - if not set, theme template will be used');
			$fields->addFieldToTab('Root.Main', $template = new DropdownField('ListingTemplateID', $label, $templates, '', null), 'Content');
			$template->setEmptyString('(results template)');
		}

		$perPage = array('5' => '5', '10' => '10', '15' => '15', '20' => '20');
		$fields->addFieldToTab('Root.Main',new DropdownField('ResultsPerPage', _t('SearchPage.RESULTS_PER_PAGE', 'Results per page'), $perPage), 'Content');

		$fields->addFieldToTab('Root.Main', new TreeMultiselectField('SearchTrees', 'Restrict results to these subtrees', 'Page'), 'Content');

		if (!$this->SortBy) {
			$this->SortBy = 'Created';
		}

		$objFields = $this->getSelectableFields();

		// Remove content and groups from being sortable (as they are not relevant).

		$sortFields = $objFields;
		unset($sortFields['Content']);
		unset($sortFields['Groups']);
		$fields->addFieldToTab('Root.Main', new DropdownField('SortBy', _t('SearchPage.SORT_BY', 'Sort By'), $sortFields), 'Content');
		$fields->addFieldToTab('Root.Main', new DropdownField('SortDir', _t('SearchPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()), 'Content');

		$types = SiteTree::page_type_classes();
		$source = array_combine($types, $types);
		asort($source);

		// add in any explicitly configured
		if($this->hasMethod('updateSource')) {
			$this->updateSource($source);
		}

		ksort($source);

		$source = array_merge($source, self::$additional_search_types);

		$types = new MultiValueDropdownField('SearchType', _t('SearchPage.SEARCH_ITEM_TYPE', 'Search items of type'), $source);
		$fields->addFieldToTab('Root.Main', $types, 'Content');

		$fields->addFieldToTab('Root.Main', new MultiValueDropdownField('SearchOnFields', _t('SearchPage.INCLUDE_FIELDS', 'Search On Fields'), $objFields), 'Content');

		if($this->hasMethod('getQueryBuilders')) {
			$parsers = $this->getQueryBuilders();
			$options = array();
			foreach ($parsers as $key => $objCls) {
				$obj = new $objCls;
				$options[$key] = $obj->title;
			}

			$fields->addFieldToTab('Root.Main', new DropdownField('QueryType', _t('SearchPage.QUERY_TYPE', 'Query Type'), $options), 'Content');
		}

		$boostVals = array();
		for ($i = 1; $i <= 5; $i++) {
			$boostVals[$i] = $i;
		}

		$fields->addFieldToTab(
			'Root.Main',
			new KeyValueField('BoostFields', _t('SearchPage.BOOST_FIELDS', 'Boost values'), $objFields, $boostVals),
			'Content'
		);

		$fields->addFieldToTab(
			'Root.Main',
			$f = new KeyValueField('BoostMatchFields', _t('SearchPage.BOOST_MATCH_FIELDS', 'Boost fields with field/value matches'), array(), $boostVals),
			'Content'
		);

		$f->setRightTitle('Enter a field name, followed by the value to boost if found in the result set, eg "title:Home" ');

		$fields->addFieldToTab(
			'Root.Main',
			$kv = new KeyValueField('FilterFields', _t('SearchPage.FILTER_FIELDS', 'Fields to filter by')),
			'Content'
		);

		$fields->addFieldToTab('Root.Main', new HeaderField('FacetHeader', _t('SearchPage.FACET_HEADER', 'Facet Settings')), 'Content');

		$fields->addFieldToTab(
			'Root.Main',
			new MultiValueDropdownField('FacetFields', _t('SearchPage.FACET_FIELDS', 'Fields to create facets for'), $objFields),
			'Content'
		);

		$fields->addFieldToTab(
			'Root.Main',
			new MultiValueTextField('CustomFacetFields', _t('SearchPage.CUSTOM_FACET_FIELDS', 'Additional fields to create facets for')),
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
			new KeyValueField('FacetMapping', _t('SearchPage.FACET_MAPPING', 'Mapping of facet title to nice title'), $facetMappingFields),
			'Content'
		);

		$fields->addFieldToTab(
			'Root.Main',
			new KeyValueField('FacetQueries', _t('SearchPage.FACET_QUERIES', 'Fields to create query facets for')),
			'Content'
		);

		$fields->addFieldToTab('Root.Main',
			new NumericField('MinFacetCount', _t('SearchPage.MIN_FACET_COUNT', 'Minimum facet count for inclusion in facet results'), 2),
			'Content'
		);

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	/**
	 * Ensures that there is always a search page
	 * by checking if there's an instance of
	 * a base SearchPage. If there
	 * is not, one is created when the DB is built.
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		if(SiteTree::get_create_default_pages()){
			$page = DataObject::get_one('SearchPage');

			// Make sure that the search page hasn't been inherited.

			if(!($page && $page->exists()) && (count(ClassInfo::subclassesFor('SearchPage')) === 1)) {
				$page = SearchPage::create();
				$page->Title = _t('SearchPage.DEFAULT_PAGE_TITLE', 'Search');
				$page->Content = '';
				$page->ResultsPerPage = 10;
				$page->Status = 'New page';
				$page->write();

				DB::alteration_message('Search page created', 'created');
			}
		}

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

class SearchPage_Controller extends Page_Controller {
}
