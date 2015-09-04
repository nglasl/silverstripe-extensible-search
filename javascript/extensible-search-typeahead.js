;(function($) {
	$(window).load(function() {

		// Bind autocomplete to the search form.

		var search = $('input.extensible-search.typeahead');
		var resentsearch = $('.recentsearch').first().clone();//grab a rescent search list item for cloning
		$('.recentsearch').remove();//remove any blank ones
		$('.recent-searches-list').each(function(){
			var list = $(this);
			$.each(DpcLocalStore.getType('SearchTerms'), function (i, term) {
				list.prepend(resentsearch.clone().children('a').prop('href',term.link).text(term.title).parent());
			});
		});
		$('.recent-searches').show();
		search.siblings('.esp-search-suggestions').find('.panel > .list-group > .list-group-item > a').each(function(){
			$(this).attr('href', '//' + window.location.host + $(this).parents('form').attr('action') + '?Search=' + $(this).text() );
		});
		if(search.length) {
			search.keyup(function() {
				var data = {'page': 12};
				var currSearch = $(this);
				var suggestions = currSearch.siblings('.esp-search-suggestions');
				if(currSearch.val().length > 2) {
					jQuery.each( currSearch.parents('form').serializeArray(), function(i, field) {
						data[field.name] = field.value;
					});
					$.get('extensible-search-api/getTypeahead',
						data
					)
					.success(function(data) {
						suggestions.children('#esp-typeahead').remove();
						var list = '';
						$.each(data, function (i, value) {
							url = currSearch.parents('form').serializeArray();
							for(var i = 0; i < 100; i++) {
								if(url[i].name === 'Search') {
									url[i].value = value;
									break;
								}
							}
							url = window.location.host + currSearch.parents('form').attr('action') + '?' + $.param(url);
							list = list + '<li class="list-group-item small"><a href="//' + url + '">' + value + '</a></li>';
						});
						suggestions.prepend(
							'<div id="esp-typeahead" class="">'
							+ '<ul class="list-group">'
							+ list
							+ '</ul>'
							+ '</div>'
						);
					});
				} else {
					suggestions.children('#typeahead').remove();
				}
			});
		}

	});
})(jQuery);
