<?php

/**
 * @author Stephen McMahon <stephen@silverstripe.com.au>
 */
class ExtensibleSearchPageSuggestions extends Extension {

	public function updateFormFields(&$fields) {

		$search = $fields->fieldByName('Search');
		$search->setAttribute('data-toggle', 'dropdown')
				->setAttribute('aria-haspopup', 'true')
				->setAttribute('aria-expanded', 'true');

		$fields->replaceField('Search', $search);
	}
}
