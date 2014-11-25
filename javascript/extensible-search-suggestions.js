;(function($) {
	$(window).load(function() {

		// Bind autocomplete to the search form.

		var search = $('div.extensible-search input[name=Search]');
		var URL = search.parents('form').attr('action').replace('getForm', 'getSuggestions');
		search.autocomplete({

			// Enforce a minimum autocomplete length.

			minLength: 3,

			// Retrieve the most relevant search suggestions.

			source: function(request, response) {
				$.get(URL, {
					term: request.term
				})
				.success(function(data) {
					response(data);
				});
			}
		});

	});
})(jQuery);
