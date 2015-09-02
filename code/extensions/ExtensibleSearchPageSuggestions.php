<?php

/**
 * @author Stephen McMahon <stephen@silverstripe.com.au>
 */
class ExtensibleSearchPageSuggestions extends Extension {

	public function updateFormFields(&$fields) {

		$search = $fields->fieldByName('Search');
		$search->setAttribute('data-toggle', 'dropdown')
				->addExtraClass('typeahead')
				->setAttribute('aria-haspopup', 'true')
				->setAttribute('autocomplete', 'off')
				->setAttribute('aria-expanded', 'true');

		$fields->replaceField('Search', $search);
	}
}
