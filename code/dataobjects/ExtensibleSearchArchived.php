<?php

/**
 *	This represents an archived search analytic.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchArchived extends DataObject {

	private static $db = array(
		'Term' => 'Varchar(255)',
		'Frequency' => 'Int',
		'FrequencyPercentage' => 'Varchar(255)',
		'AverageTimeTaken' => 'Varchar(255)',
		'Results' => 'Varchar(255)'
	);

	private static $has_one = array(
		'Archive' => 'ExtensibleSearchArchive'
	);

	private static $summary_fields = array(
		'Term',
		'Frequency',
		'FrequencyPercentage',
		'AverageTimeTaken',
		'Results'
	);

	private static $field_labels = array(
		'Term' => 'Search Term',
		'Frequency' => 'Frequency',
		'FrequencyPercentage' => 'Frequency %',
		'AverageTimeTaken' => 'Average Time Taken (s)',
		'Results' => 'Has Results?'
	);

	/**
	 *	Make field_labels translatable
	 */

	function fieldLabels($includerelations = true) {
		return array(
			'Term' => _t('EXTENSIBLE_SEARCH.SearchTerm','Search Term'),
			'Frequency' => _t('EXTENSIBLE_SEARCH.Frequency','Frequency'),
			'FrequencyPercentage' => _t('EXTENSIBLE_SEARCH.FrequencyP','Frequency %'),
			'AverageTimeTaken' => _t('EXTENSIBLE_SEARCH.AverageTimeTaken','Average Time Taken (s)'),
			'Results' => _t('EXTENSIBLE_SEARCH.HasResults','Has Results?')
		);
	}

	public function canEdit($member = null) {

		return false;
	}

	public function canCreate($member = null) {

		return false;
	}

	public function canDelete($member = null) {

		return false;
	}

}
