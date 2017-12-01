<?php

namespace nglasl\extensible;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 *	Passes the current request over to the `ExtensibleSearchService`.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchAPI extends Controller {

	public $service;

	private static $dependencies = array(
		'service' => '%$' . ExtensibleSearchService::class
	);

	private static $allowed_actions = array(
		'toggleSuggestionApproved',
		'getSuggestions'
	);

	/**
	 *	Reject a direct request.
	 */

	public function index() {

		return $this->httpError(404);
	}

	/**
	 *	Toggle a search suggestion's approval.
	 */

	public function toggleSuggestionApproved($request) {

		// Restrict this functionality appropriately.

		$user = Member::currentUserID();
		if(Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS') && ($status = $this->service->toggleSuggestionApproved($request->postVar('suggestion')))) {

			// Display an appropriate CMS notification.

			$this->getResponse()->setStatusDescription($status);
			return $status;
		}
		else {
			return $this->httpError(404);
		}
	}

	/**
	 *	Retrieve the most relevant search suggestions that have been approved.
	 *
	 *	@URLparameter term <{SEARCH_TERM}> string
	 *	@return JSON
	 */

	public function getSuggestions($request) {

		if(Config::inst()->get(ExtensibleSearchSuggestion::class, 'enable_suggestions') && ($suggestions = $this->service->getSuggestions($request->getVar('term'), $request->getVar('page')))) {

			// Return the search suggestions as JSON.

			$this->getResponse()->addHeader('Content-Type', 'application/json');

			// JSON_PRETTY_PRINT.

			return json_encode($suggestions, 128);
		}
		else {
			return $this->httpError(404);
		}
	}

}
