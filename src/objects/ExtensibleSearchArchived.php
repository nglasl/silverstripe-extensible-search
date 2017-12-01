<?php

namespace nglasl\extensible;

use SilverStripe\ORM\DataObject;

/**
 *	This represents an archived search analytic.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchArchived extends DataObject {

	private static $table_name = 'ExtensibleSearchArchived';

	private static $db = array(
		'Term' => 'Varchar(255)',
		'Frequency' => 'Int',
		'FrequencyPercentage' => 'Varchar(255)',
		'AverageTimeTaken' => 'Varchar(255)',
		'Results' => 'Varchar(255)'
	);

	private static $has_one = array(
		'Archive' => ExtensibleSearchArchive::class
	);

	private static $summary_fields = array(
		'Term',
		'Frequency',
		'FrequencyPercentage',
		'AverageTimeTaken',
		'Results'
	);

	public function canEdit($member = null) {

		return false;
	}

	public function canCreate($member = null, $context = array()) {

		return false;
	}

	public function canDelete($member = null) {

		return false;
	}

	public function fieldLabels($includerelations = true) {

		return array(
			'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
			'Frequency' => _t('EXTENSIBLE_SEARCH.FREQUENCY', 'Frequency'),
			'FrequencyPercentage' => _t('EXTENSIBLE_SEARCH.FREQUENCY_%', 'Frequency %'),
			'AverageTimeTaken' => _t('EXTENSIBLE_SEARCH.AVERAGE_TIME_TAKEN', 'Average Time Taken (s)'),
			'Results' => _t('EXTENSIBLE_SEARCH.HAS_RESULTS?', 'Has Results?')
		);
	}

}
