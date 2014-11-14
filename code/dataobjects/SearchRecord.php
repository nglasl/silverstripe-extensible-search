<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class SearchRecord extends DataObject {
	private static $db = array(
		'Title'				=> 'Varchar(128)',
		'NumResults'		=> 'Int',
		'Time'				=> 'Float',
	);
	
	private static $summary_fields = array(
		'Title', 'NumResults', 'Time',
	);
	
	private static $enabled = true;
	
	public static function record_search ($term, $numresults, $time = -1) {
		if (!self::config()->enabled) {
			return;
		}

		if ($numresults <= 0) {
			return;
		}

		$tracked = SearchRecord::create(array(
			'Title'			=> trim($term),
			'NumResults'	=> $numresults,
			'Time'			=> $time
		));
		
		$tracked->write();
		SearchSuggestion::track_suggestion($tracked);
		
		return $tracked;
	}
}
