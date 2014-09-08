<?php

if (!class_exists('MultiValueField')) {
	exit("The extensible search module requires the multivaluefield module from https://github.com/nyeholt/silverstripe-multivaluefield");
}
if(!defined('EXTENSIBLE_SEARCH_PAGE_PATH')) {
	define('EXTENSIBLE_SEARCH_PAGE_PATH', rtrim(basename(dirname(__FILE__))));
}
