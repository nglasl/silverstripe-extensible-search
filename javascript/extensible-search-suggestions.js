;(function($) {
    $(window).load(function() {

		// Trigger an autocomplete for the most relevant search suggestions.

		$('div.extensible-search input[name=Search]').entwine({
			onmatch: function() {

				// Retrieve the most relevant search suggestions.

				var search = $(this);
				var URL = search.parents('form').attr('action').replace('getForm', 'getSuggestions');
				search.autocomplete({
					source: function(request, response) {
						$.get(URL, {
							term: request.term
						})
						.success(function(data) {
							response(data);
						});
					},

					// Enforce a minimum autocomplete length.

					minLength: 3
				});
			}
		});

	});
})(jQuery);
