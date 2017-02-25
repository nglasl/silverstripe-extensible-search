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
			'Term' => _t('ExtensibleSearch.SearchTerm','Search Term'),
			'FrequencySummary' => _t('ExtensibleSearch.Frequency','Frequency'),
			'FrequencyPercentage' => _t('ExtensibleSearch.FrequencyP','Frequency %'),
			'FrequencyPercentage' => _t('ExtensibleSearch.AvarageTimeTaken','Average Time Taken (s)'),
			'ApprovedField' => _t('ExtensibleSearch.HasResults','Has Results?')
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
