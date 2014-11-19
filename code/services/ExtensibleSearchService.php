<?php

/**
 *	Handles the search analytics, while providing any additional functionality required by the module.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchService {

	/**
	 *	Log the details of a user search for analytics.
	 *
	 *	@parameter <{SEARCH_TERM}> string
	 *	@parameter <{NUMBER_OF_SEARCH_RESULTS}> integer
	 *	@parameter <{SEARCH_TIME}> float
	 *	@return extensible search
	 */

	public function logSearch($term, $results, $time, $engine) {

		// Make sure the search analytics are enabled.

		if(!Config::inst()->get('ExtensibleSearch', 'enable_analytics')) {
			return null;
		}

		// Log the details of the user search.

		$search = ExtensibleSearch::create(array(
			'Term'	=> trim($term),
			'Results' => $results,
			'Time' => $time,
			'SearchEngine' => $engine
		));
		$search->write();
		return $search;
	}

}
