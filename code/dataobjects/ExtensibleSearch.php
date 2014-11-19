<?php

/**
 *	Details of a user search that are retrieved for analytics.
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearch extends DataObject {

	private static $db = array(
		'Term' => 'Varchar(255)',
		'Results' => 'Int',
		'Time' => 'Float',
		'SearchEngine' => 'Varchar(255)'
	);

	/**
	 *	Allow the ability to disable search analytics.
	 */

	private static $enable_analytics = true;

}
