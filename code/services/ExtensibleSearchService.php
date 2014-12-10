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
	 *	@parameter <{EXTENSIBLE_SEARCH_PAGE_ID}> integer
	 *	@return extensible search
	 */

	public function logSearch($term, $results, $time, $engine, $pageID = 0) {

		// Make sure the search analytics are enabled.

		if(!Config::inst()->get('ExtensibleSearch', 'enable_analytics')) {
			return null;
		}

		// Log the details of the user search.

		$search = ExtensibleSearch::create(array(
			'Term'	=> $term,
			'Results' => $results,
			'Time' => $time,
			'SearchEngine' => $engine,
			'ExtensibleSearchPageID' => $pageID
		));
		$search->write();

		// Log the details of the user search as a suggestion.

		if($results > 0) {
			$this->logSuggestion($term, $pageID);
		}
		return $search;
	}

	/**
	 *	Log a user search generated suggestion.
	 *
	 *	@parameter <{SEARCH_TERM}> string
	 *	@parameter <{EXTENSIBLE_SEARCH_PAGE_ID}> integer
	 *	@return extensible search suggestion
	 */

	public function logSuggestion($term, $pageID = 0) {

		// Make sure the search matches the minimum autocomplete length.

		if(strlen($term) < 3) {
			return null;
		}

		// Make sure the suggestion doesn't already exist.

		$suggestion = ExtensibleSearchSuggestion::get()->filter(array(
			'Term' => $term,
			'ExtensibleSearchPageID' => $pageID
		))->first();

		// Store the frequency to make search suggestion relevance more efficient.

		$frequency = ExtensibleSearch::get()->filter(array(
			'Term' => $term,
			'ExtensibleSearchPageID' => $pageID
		))->count();
		if($suggestion) {
			$suggestion->Frequency = $frequency;
		}
		else {

			// Log the suggestion.

			$suggestion = ExtensibleSearchSuggestion::create(array(
				'Term' => $term,
				'Frequency' => $frequency,
				'Approved' => (int)Config::inst()->get('ExtensibleSearchSuggestion', 'automatic_approval'),
				'ExtensibleSearchPageID' => $pageID
			));
		}
		$suggestion->write();
		return $suggestion;
	}

	/**
	 *	Toggle a search suggestion's approval.
	 *
	 *	@parameter <{SUGGESTION_ID}> integer
	 *	@return string
	 */

	public function toggleSuggestionApproved($ID) {

		if($suggestion = ExtensibleSearchSuggestion::get()->byID($ID)) {

			// Update the search suggestion.

			$approved = !$suggestion->Approved;
			$suggestion->Approved = $approved;
			$suggestion->write();

			// Determine the approval status.

			$status = $approved ? 'Approved' : 'Disapproved';
			return "{$status} \"{$suggestion->Term}\"!";
		}
		else {
			return null;
		}
	}

	/**
	 *	Retrieve the most relevant search suggestions.
	 *
	 *	@parameter <{SEARCH_TERM}> string
	 *	@parameter <{EXTENSIBLE_SEARCH_PAGE_ID}> integer
	 *	@parameter <{LIMIT}> integer
	 *	@parameter <{APPROVED_ONLY}> boolean
	 *	@return array
	 */

	public function getSuggestions($term, $pageID = 0, $limit = 5, $approved = true) {

		// Make sure the search matches the minimum autocomplete length.

		if($term && (strlen($term) > 2)) {
			$suggestions = ExtensibleSearchSuggestion::get()->filter(array(
				'Term:StartsWith' => $term,
				'Approved' => (int)$approved
			))->limit($limit);

			// Make sure the current user has search permission.

			$pageID = (int)$pageID;
			if(ExtensibleSearchPage::get_by_id('ExtensibleSearchPage', $pageID)->canView()) {

				// Retrieve the appropriate search suggestions.

				$suggestions = $suggestions->where("ExtensibleSearchPageID = {$pageID} OR ExtensibleSearchPageID = 0");
			}
			else {

				// Retrieve search suggestions with no permission restriction.

				$suggestions = $suggestions->filter('ExtensibleSearchPageID', 0);
			}

			// Make sure duplicate search suggestions don't appear.

			return array_unique($suggestions->column('Term'));
		}
		else {
			return null;
		}
	}

}
