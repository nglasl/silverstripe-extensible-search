<?php

namespace nglasl\extensible;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 *	This represents an archived collection of search analytics.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchArchive extends DataObject {

	private static $table_name = 'ExtensibleSearchArchive';

	private static $db = array(
		'StartingDate' => 'Date',
		'EndingDate' => 'Date'
	);

	private static $has_one = array(
		'ExtensibleSearchPage' => ExtensibleSearchPage::class
	);

	private static $has_many = array(
		'HistorySummary' => ExtensibleSearchArchived::class
	);

	private static $default_sort = 'ID DESC';

	private static $summary_fields = array(
		'TitleSummary'
	);

	public function canCreate($member = null, $context = array()) {

		return false;
	}

	public function canDelete($member = null) {

		return false;
	}

	/**
	 *	The archive date range.
	 *
	 *	@return string
	 */

	public function getTitle() {

		$starting = date('F Y', strtotime($this->StartingDate));
		$ending = date('F Y', strtotime($this->EndingDate));
		return "{$starting} → {$ending}";
	}

	public function getCMSFields() {

		$fields = parent::getCMSFields();

		// Remove any fields that are not required in their default state.

		$fields->removeByName('StartingDate');
		$fields->removeByName('EndingDate');
		$fields->removeByName('ExtensibleSearchPageID');
		$fields->removeByName('HistorySummary');

		// Instantiate the archived collection of search analytics.

		$fields->addFieldToTab('Root.Main', GridField::create(
			'HistorySummary',
			_t('EXTENSIBLE_SEARCH.SUMMARY', 'Summary'),
			$this->HistorySummary(),
			$summaryConfiguration = GridFieldConfig_Base::create()
		)->setModelClass(ExtensibleSearchArchived::class));

		// Instantiate an export button.

		$summaryConfiguration->addComponent(new GridFieldExportButton());

		// Update the custom summary fields to be sortable.

		$summaryConfiguration->getComponentByType(GridFieldSortableHeader::class)->setFieldSorting(array(
			'FrequencyPercentage' => 'Frequency'
		));
		$summaryConfiguration->removeComponentsByType(GridFieldFilterHeader::class);

		// Allow extension customisation.

		$this->extend('updateExtensibleSearchArchiveCMSFields', $fields);
		return $fields;
	}

	public function fieldLabels($includerelations = true) {

		return array(
			'TitleSummary' => _t('EXTENSIBLE_SEARCH.DATE_RANGE', 'Date Range')
		);
	}

	/**
	 *	The archive date range as HTML.
	 *
	 *	@return html
	 */

	public function getTitleSummary() {

		$starting = date('F Y', strtotime($this->StartingDate));
		$ending = date('F Y', strtotime($this->EndingDate));

		// The following is required so HTML isn't automatically escaped.

		$output = DBHTMLText::create();
		$output->setValue("<strong>{$starting}</strong> → <strong>{$ending}</strong>");
		return $output;
	}

}
