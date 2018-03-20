;
(function ($) {
    $(function () {

        // Bind autocomplete to the primary search form.

        var searchInputs = $('input.extensible-search');
        if (searchInputs.length) {
            searchInputs.each(function () {
                var search = $(this);
                // Retrieve the search suggestions that have been approved.

                var suggestions = [];
                $.get('extensible-search-api/getPageSuggestions', {
                    page: search.data('extensible-search-page')
                })
                        .done(function (data) {

                            suggestions = data;
                        });

                // Initialise the autocomplete.

                search.autocomplete({

                    // Determine whether to disable search suggestions, based on configuration.

                    disabled: !search.data('suggestions-enabled'),

                    // Enforce a minimum autocomplete length.

                    minLength: 3,

                    // Determine the most relevant search suggestions that have been approved.

                    source: function (request, response) {

                        // Perform client side filtering, which provides a massive performance increase!

                        var term = search.val();
                        var options = [];
                        $.each(suggestions, function () {

                            if (term === this.substr(0, term.length)) {
                                options.push({
                                    'label': term + '<strong>' + this.substr(term.length) + '</strong>',
                                    'value': this
                                });

                                // Enforce a limit.

                                if (options.length === 5) {
                                    return false;
                                }
                            }
                        });
                        response(options);
                    }
                })

                        // This needs to render HTML.

                        .data('ui-autocomplete')._renderItem = function (ul, item) {

                    return $('<li>').append($('<div>').html(item.label)).appendTo(ul);
                };
            });
        }
    });
})(jQuery);
