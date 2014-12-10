;(function($) {
	$(window).load(function() {

		// Bind autocomplete to the search form.

		var search = $('div.extensible-search input[name=Search]');
		if(search.length) {
			search.autocomplete({

				// Determine whether to disable search suggestions, based on configuration.

				disabled: !search.data('suggestions-enabled'),

				// Enforce a minimum autocomplete length.

				minLength: 3,

				// Retrieve the most relevant search suggestions that have been approved.

				source: function(request, response) {

					$.get('extensible-search-api/getSuggestions', {
						term: request.term,
						page: search.data('extensible-search-page')
					})
					.success(function(data) {

						response(data);
					});
				}
			});
		}

	});
})(jQuery);
