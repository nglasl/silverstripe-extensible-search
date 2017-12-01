<?php

namespace nglasl\extensible;

/**
 *	This is the foundation for a custom search engine implementation.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

abstract class CustomSearchEngine {

	/**
	 *	Depending on whether the search engine supports hierarchy filtering based on parent ID, this may also be configured.
	 */

	public $supports_hierarchy = false;

	/**
	 *	Determine the search engine specific selectable fields, primarily for sorting.
	 */

	abstract public function getSelectableFields($page = null);

	abstract public function getSearchResults($data = null, $form = null, $page = null);

}
