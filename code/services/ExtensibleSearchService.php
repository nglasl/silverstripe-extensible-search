<?php

/**
 *	Handles the search analytics and suggestions, while providing any additional functionality required by the module.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchService {

	/**
	 *	Log the details of a user search for analytics.
	 *
	 *	@parameter <{SEARCH_TERM}> string
	 *	@parameter <{NUMBER_OF_SEARCH_RESULTS}> integer
	 *	@parameter <{SEARCH_TIME}> float
	 *	@parameter <{SEARCH_ENGINE}> string
	 *	@return extensible search
	 */

	public function logSearch($term, $results, $time, $engine) {

		// Make sure the search analytics are enabled.

		if(!Config::inst()->get('ExtensibleSearch', 'enable_analytics')) {
			return null;
		}

		// Log the details of the user search.

		$term = trim($term);
		$search = ExtensibleSearch::create(array(
			'Term'	=> $term,
			'Results' => $results,
			'Time' => $time,
			'SearchEngine' => $engine
		));
		$search->write();

		// Log the details of the user search as a suggestion.

		if($results > 0) {
			$this->logSuggestion($term);
		}
		return $search;
	}

	/**
	 *	Log a user search generated suggestion.
	 *
	 *	@parameter <{SEARCH_TERM}> string
	 *	@return extensible search suggestion
	 */

	public function logSuggestion($term) {

		// Make sure the suggestions are enabled, and the search matches the minimum autocomplete length.

		if(!Config::inst()->get('ExtensibleSearchSuggestion', 'enable_suggestions') || (strlen($term) < 3)) {
			return null;
		}

		// Make sure the suggestion doesn't already exist.

		$existing = ExtensibleSearchSuggestion::get()->filter(array(
			'Term' => $term
		))->first();
		if(!$existing) {

			// Log the suggestion.

			$existing = ExtensibleSearchSuggestion::create(array(
				'Term' => $term
			));
			$existing->write();
		}
		return $existing;
	}

}
