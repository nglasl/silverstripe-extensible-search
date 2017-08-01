<?php

/**
 *	This creates an archived collection of analytics for each search page.
 *	NOTE: The search analytics will be purged after this has taken place.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchArchiveTask extends BuildTask {

	protected $title = 'Extensible Search Archiving';

	protected $description = 'This creates an archived collection of analytics for each search page.';

	/**
	 *	The number of analytics to archive for each search page.
	 */

	private static $number_to_archive = 100;

	public function run($request) {

		increase_time_limit_to();

		// Determine whether a search page has analytics.

		foreach(ExtensibleSearchPage::get() as $page) {
			$history = $page->History();
			if($history->exists()) {

				// Instantiate an archive.

				$archive = ExtensibleSearchArchive::create(
					array(
						'StartingDate' => $history->min('Created'),
						'EndingDate' => $history->max('Created'),
						'ExtensibleSearchPageID' => $page->ID
					)
				);
				$archive->write();

				// Determine the search page specific analytics.

				$counter = 0;
				foreach($page->getHistorySummary() as $summary) {

					// Determine whether the number of analytics to archive has been reached.

					if($counter++ === self::config()->number_to_archive) {
						break;
					}

					// Instantiate an archived search analytic, and place it in the archive.

					$archived = ExtensibleSearchArchived::create(
						$summary->toMap()
					);
					$archived->ArchiveID = $archive->ID;
					$archived->write();
				}

				// The search analytics will be purged now that they've been archived.

				DB::alteration_message("{$history->count()} Archived");
				$query = new SQLDelete(
					'ExtensibleSearch',
					"ExtensibleSearchPageID = {$page->ID}"
				);
				$query->execute();

				// The search suggestion frequencies depend on the analytics, so these require updating.

				$query = new SQLUpdate(
					'ExtensibleSearchSuggestion',
					array(
						'Frequency' => 0
					),
					"ExtensibleSearchPageID = {$page->ID}"
				);
				$query->execute();
			}
		}
		DB::alteration_message('<strong>Complete!</strong>');
	}

}
