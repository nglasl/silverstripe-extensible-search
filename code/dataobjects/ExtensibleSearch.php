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
		'TimeSummary' => 'Time',
		'Term' => 'Search Term',
		'TimeTakenSummary' => 'Time Taken (s)',
		'Results' => 'Results',
		'SearchEngine' => 'Search Engine'
	);

	/**
	 *	Allow the ability to disable search analytics.
	 */

	private static $enable_analytics = true;

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

}
