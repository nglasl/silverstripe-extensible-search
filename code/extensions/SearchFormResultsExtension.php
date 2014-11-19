<?php

/**
 *	The search form extension required to customise the full-text search results.
 *
 *	@author Nathan Glasl, <nathan@silverstripe.com.au>
 */

class SearchFormResultsExtension extends Extension {

	public $service;

	private static $dependencies = array(
		'service' => '%$ExtensibleSearchService'
	);

	// These functions have been copied from the base search form class, updated to support both sort and filter functionality.

	public function getExtendedResults($pageLength = null, $sort = 'Relevance DESC', $filter = '', $data = null) {
	 	// legacy usage: $data was defaulting to $_REQUEST, parameter not passed in doc.silverstripe.org tutorials
		if(!isset($data) || !is_array($data)) $data = $_REQUEST;

		// set language (if present)
		if(class_exists('Translatable')) {
			if(singleton('SiteTree')->hasExtension('Translatable') && isset($data['searchlocale'])) {
				if($data['searchlocale'] == "ALL") {
					Translatable::disable_locale_filter();
				} else {
					$origLocale = Translatable::get_current_locale();

					Translatable::set_current_locale($data['searchlocale']);
				}
			}
		}
		$search = $data['Search'];
	 	$andProcessor = create_function('$matches','
	 		return " +" . $matches[2] . " +" . $matches[4] . " ";
	 	');
	 	$notProcessor = create_function('$matches', '
	 		return " -" . $matches[3];
	 	');

	 	$keywords = preg_replace_callback('/()("[^()"]+")( and )("[^"()]+")()/i', $andProcessor, $search);
	 	$keywords = preg_replace_callback('/(^| )([^() ]+)( and )([^ ()]+)( |$)/i', $andProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )(not )("[^"()]+")/i', $notProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )(not )([^() ]+)( |$)/i', $notProcessor, $keywords);

		$keywords = $this->addStarsToKeywords($keywords);

		if(!$pageLength) $pageLength = $this->owner->pageLength;
		$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;

		$startTime = microtime(true);
		if(strpos($keywords, '"') !== false || strpos($keywords, '+') !== false || strpos($keywords, '-') !== false || strpos($keywords, '*') !== false) {
			$results = DB::getConn()->searchEngine($this->owner->classesToSearch, $keywords, $start, $pageLength, $sort, $filter, true);
		} else {
			$results = DB::getConn()->searchEngine($this->owner->classesToSearch, $keywords, $start, $pageLength);
		}
		$totalTime = microtime(true) - $startTime;
		$this->service->logSearch($search, $results->getTotalItems(), $totalTime, 'Full-Text');

		// filter by permission
		if($results) foreach($results as $result) {
			if(!$result->canView()) $results->remove($result);
		}

		// reset locale
		if(class_exists('Translatable')) {
			if(singleton('SiteTree')->hasExtension('Translatable') && isset($data['searchlocale'])) {
				if($data['searchlocale'] == "ALL") {
					Translatable::enable_locale_filter();
				} else {
					Translatable::set_current_locale($origLocale);
				}
			}
		}

		return $results;
	}

	public function addStarsToKeywords($keywords) {
		if(!trim($keywords)) return "";
		// Add * to each keyword
		$splitWords = preg_split("/ +/" , trim($keywords));
		while(list($i,$word) = each($splitWords)) {
			if($word[0] == '"') {
				while(list($i,$subword) = each($splitWords)) {
					$word .= ' ' . $subword;
					if(substr($subword,-1) == '"') break;
				}
			} else {
				$word .= '*';
			}
			$newWords[] = $word;
		}
		return implode(" ", $newWords);
	}

}
