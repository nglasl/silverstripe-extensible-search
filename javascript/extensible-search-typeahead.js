;(function($) {
	window.ESPPageStore = new PageStore;
	$(window).load(function() {


		var search = $('input.extensible-search.typeahead');
		var resentsearch = $('.recentsearch').first().clone();//grab a rescent search list item for cloning
		$('.recentsearch').remove();//remove any blank ones
		$('.recent-searches-list').each(function(){
			var list = $(this);
			$.each(ESPPageStore.getType('SearchTerms'), function (i, term) {
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

	/*
	 * Page Store
	 * Uses local storage to track search pages that have links clicked on them. This helps users allowing us to
	 * return recent searches the users has made and found useful
	 */
	var bindSearchCapture = function () {
		$(document).on('click', 'div.search-result a', function (e) {
			var url = location.pathname + location.search;
			var searchTerm = $('#SearchForm_getForm_Search').val();
			var item = {
				link: url,
				title: searchTerm,
				type: 'SearchTerms',
				class: ''
			}
			DpcLocalStore.recordView(item);
		});
	};

	function PageStore() {
		this.items = {'all': []};
		this.numberToStore = 5;

		if (window.localStorage) {
			var rawData = window.localStorage.getItem('pageview');
			if (rawData && rawData.length) {
				this.items = JSON.parse(rawData);
			}
		}
	}

	PageStore.prototype.empty = function () {
		if (window.localStorage) {
			window.localStorage.clear();
			this.items = {'all': []};
		}
	}

	PageStore.prototype.getType = function (cat) {
		if (!this.items[cat]) {
			this.items[cat] = [];
		}
		return this.items[cat];
	}

	PageStore.prototype.recordView = function (pageItem) {
		if (pageItem.type && pageItem.type.length) {
			var addTo = this.getType(pageItem.type);

			for (var i in addTo) {
				if (!addTo[i].link) {
					addTo.splice(i, 1);
					continue;
				}
				if (addTo[i].link == pageItem.link) {
					addTo.splice(i, 1);
				}
			}

			addTo.unshift({
				'link': pageItem.link,
				'class': pageItem.classes,
				'title': pageItem.title
			});
			if (addTo.length > this.numberToStore) {
				addTo.pop();
			}

			this.save();
		}
	}

	PageStore.prototype.save = function () {
		if (window.localStorage) {
			window.localStorage.setItem('pageview', JSON.stringify(this.items));
		}
	}

})(jQuery);
