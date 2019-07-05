<?php

namespace nglasl\extensible;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

/**
 *	Details of a user search that are retrieved for analytics.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearch extends DataObject {

	private static $table_name = 'ExtensibleSearch';

	private static $db = array(
		'Term' => 'Varchar(255)',
		'Results' => 'Int',
		'Time' => 'Float',
		'SearchEngine' => 'Varchar(255)'
	);

	private static $has_one = array(
		'ExtensibleSearchPage' => ExtensibleSearchPage::class
	);

	private static $default_sort = 'ID DESC';

	private static $summary_fields = array(
		'Created.Nice',
		'Term',
		'TimeTakenSummary',
		'Results',
		'SearchEngineSummary'
	);


	public function canView($member = null) {

		return true;
	}

	public function fieldLabels($includerelations = true) {

		return array(
			'Created.Nice' => _t('EXTENSIBLE_SEARCH.TIME', 'Time'),
			'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
			'TimeTakenSummary' => _t('EXTENSIBLE_SEARCH.TIME_TAKEN', 'Time Taken (s)'),
			'Results' => _t('EXTENSIBLE_SEARCH.RESULTS', 'Results'),
			'SearchEngineSummary' => _t('EXTENSIBLE_SEARCH.SEARCH_ENGINE', 'Search Engine')
		);
	}

	/**
	 *	Retrieve the search time for display purposes.
	 *
	 *	@return float
	 */

	public function getTimeTakenSummary() {

		return round($this->Time, 5);
	}

	/**
	 *	Retrieve the search engine for display purposes.
	 *
	 *	@return string
	 */

	public function getSearchEngineSummary() {

		$configuration = Config::inst()->get(ExtensibleSearchPage::class, 'custom_search_engines');
		return isset($configuration[$this->SearchEngine]) ? $configuration[$this->SearchEngine] : $this->SearchEngine;
	}

}
