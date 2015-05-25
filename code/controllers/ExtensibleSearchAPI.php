<?php

/**
 *	Passes the current request over to the ExtensibleSearchService.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class ExtensibleSearchAPI extends Controller {

	public $service;

	private static $dependencies = array(
		'service' => '%$ExtensibleSearchService'
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
	 *	Display an error page on invalid request.
	 *
	 *	@parameter <{ERROR_CODE}> integer
	 *	@parameter <{ERROR_MESSAGE}> string
	 */

	public function httpError($code, $message = null) {

		// Determine the error page for the given status code.

		$errorPages = ErrorPage::get()->filter('ErrorCode', $code);

		// Allow extension customisation.

		$this->extend('updateErrorPages', $errorPages);

		// Retrieve the error page response.

		if($errorPage = $errorPages->first()) {
			Requirements::clear();
			Requirements::clear_combined_files();
			$response = ModelAsController::controller_for($errorPage)->handleRequest(new SS_HTTPRequest('GET', ''), DataModel::inst());
			throw new SS_HTTPResponse_Exception($response, $code);
		}

		// Retrieve the cached error page response.

		else if(file_exists($cachedPage = ErrorPage::get_filepath_for_errorcode($code, class_exists('Translatable') ? Translatable::get_current_locale() : null))) {
			$response = new SS_HTTPResponse();
			$response->setStatusCode($code);
			$response->setBody(file_get_contents($cachedPage));
			throw new SS_HTTPResponse_Exception($response, $code);
		}
		else {
			return parent::httpError($code, $message);
		}
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

		if(Config::inst()->get('ExtensibleSearchSuggestion', 'enable_suggestions') && ($suggestions = $this->service->getSuggestions($request->getVar('term'), $request->getVar('page')))) {

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
