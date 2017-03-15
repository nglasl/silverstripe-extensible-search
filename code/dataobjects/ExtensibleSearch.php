<?php

/**
 *	Details of a user search that are retrieved for analytics.
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearch extends DataObject {

	private static $db = array(
		'Term' => 'Varchar(255)',
		'Results' => 'Int',
		'Time' => 'Float',
		'SearchEngine' => 'Varchar(255)'
	);

	private static $has_one = array(
		'ExtensibleSearchPage' => 'ExtensibleSearchPage'
	);

	private static $default_sort = 'ID DESC';

	private static $summary_fields = array(
		'TimeSummary',
		'Term',
		'TimeTakenSummary',
		'Results',
		'SearchEngineSummary'
	);

	/**
	 *	Allow the ability to disable search analytics.
	 */

	private static $enable_analytics = true;

	public function fieldLabels($includerelations = true) {

		return array(
			'TimeSummary' => _t('EXTENSIBLE_SEARCH.TIME', 'Time'),
			'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
			'TimeTakenSummary' => _t('EXTENSIBLE_SEARCH.TIME_TAKEN', 'Time Taken (s)'),
			'Results' => _t('EXTENSIBLE_SEARCH.RESULTS', 'Results'),
			'SearchEngineSummary' => _t('EXTENSIBLE_SEARCH.SEARCH_ENGINE', 'Search Engine')
		);
	}

	/**
	 *	Retrieve the log time for display purposes.
	 *
	 *	@return string
	 */

	public function getTimeSummary() {

		return $this->dbObject('Created')->Format('jS F Y, g:i:sa');
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

		$configuration = Config::inst()->get('ExtensibleSearchPage', 'search_engine_extensions');
		return isset($configuration[$this->SearchEngine]) ? $configuration[$this->SearchEngine] : $this->SearchEngine;
	}

}
