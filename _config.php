<?php

/**
 *	The extensible search specific configuration settings.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

if(!class_exists('MultiValueField')) {
	exit('The <strong>extensible search</strong> module requires the <strong>multivalue field</strong> module.');
}
if(!defined('EXTENSIBLE_SEARCH_PATH')) {
	define('EXTENSIBLE_SEARCH_PATH', rtrim(basename(dirname(__FILE__))));
}
