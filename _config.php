<?php

/**
 *	The extensible search specific configuration settings.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

if(!defined('EXTENSIBLE_SEARCH_PATH')) {
	define('EXTENSIBLE_SEARCH_PATH', rtrim(basename(dirname(__FILE__))));
}

// Update the current extensible search page image.

Config::inst()->update('ExtensibleSearchPage', 'icon', EXTENSIBLE_SEARCH_PATH . '/images/search.png');
