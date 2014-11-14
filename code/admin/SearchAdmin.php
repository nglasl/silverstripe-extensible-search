<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class SearchAdmin extends ModelAdmin {
	private static $url_segment = 'extensible-search';
	private static $menu_title = 'Search';
	private static $managed_models = array('SearchSuggestion', 'SearchRecord');
	
	
}
