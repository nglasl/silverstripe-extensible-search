<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class SearchSuggestion extends DataObject {
	private static $db = array(
		'Title'				=> 'Varchar(128)',
		'Frequency'			=> 'Int',
	);
	
	private static $summary_fields = array(
		'Title', 'Frequency', 
	);
	
	public static function track_suggestion(SearchRecord $search) {
		if (strlen($search->Title) < 3) {
			return;
		}
		$existing = SearchSuggestion::get()->filter('Title', $search->Title)->first();
		$num = SearchRecord::get()->filter('Title', $search->Title)->count();
		
		if ($existing) {
			$existing->Frequency = $num;
		} else {
			$existing = SearchSuggestion::create(array(
				'Title'	=> $search->Title,
				'Frequency'		=> $num,
			));
		}

		$existing->write();
	}
}
