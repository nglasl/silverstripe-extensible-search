;(function($) {
	window.ESPPageStore = new PageStore;
	$(window).load(function() {

		$.fn.extend({
			nearest: function (pattern) {

				var found = null;
				var domobj = null;

				do {
					domobj = this.parent();
					found = domobj.find(pattern);
				} while (found === null && domobj !== null)

				return found;
			}
		});


		var overlays = $('.esp-overlay');

		if(overlays.length) {

			bindSearchCapture();

			overlays.each(function (i , overlay) {
				overlay =  $(overlay);
				var search = overlay.nearest('input.extensible-search.typeahead');
				if(search.length) {

					search.after(overlay);

					if (ESPPageStore.getType('SearchTerms').length && overlay.children('.recent-searches').length) {

						var resentsearch = overlay.find('.recentsearch').first().clone();
						overlay.find('.recentsearch').remove();

						overlay.find('.search-suggestions-list > li.list-item > a')
							.each(function(){
								$(this).attr('href',
									'//' + window.location.host
									+ $(this).parents('form').attr('action')
									+ '?Search=' + $(this).text()
								);
							});

						$.each(ESPPageStore.getType('SearchTerms'), function (i, term) {
							overlay.find('.recent-searches-list').prepend(
								resentsearch.clone()
									.children('a')
									.prop('href',term.link)
									.text(term.title)
									.parent()
									.show()
							);
						});

						$('.recent-searches').show();

					}

					search.keyup(function() {
						var currSearch = $(this);
						var data = {'page': currSearch.data('extensible-search-page')};
						if(currSearch.val().length > 2) {
							var list = overlay.find('.search-typeahead-list');
							searchAjax(currSearch, data, list, 'extensible-search-api/getTypeahead');
							var list = overlay.find('.search-suggestions-list');
							searchAjax(currSearch, data, list, 'extensible-search-api/getSuggestions');
						}
					})
					.on({
						focus: function () {
							showOverlay(search, overlay);
						}
					});
				}
			});

		}

	});

	function showOverlay(search, overlay) {
		overlay.show();
		search.is();
		$(document).on("focusin click", function () {
			// Are we clicking or focused anywhere in the overlay or search
			if (overlay.has($(event.target)).length && $(event.target).is('a')) {
				//If the focus is a link don't hide the overlay so we can tab through them
				return;
			} else if ($(event.target).is(overlay)
					|| $(event.target).is(search)
					|| overlay.has($(event.target)).length
			) {
				//else the user has missed the links but hit the overlay return focus to the search input
				//Prevent event propagation to prevent an endless loop
				search.focus(function( event ) {
					event.stopPropagation();
				});
				search.focus();
				return;
			}
			overlay.hide();
		});
	}

	function searchAjax(currSearch, data, list, endpoint) {
		jQuery.each( currSearch.parents('form').serializeArray(), function(i, field) {
			data[field.name] = field.value;
		});
		$.get(endpoint,
			data
		)
		.success(function(data) {

			var listitem = list.children('.list-item').first().clone();

			if(!data.length) {
				list.find('.list-item').remove();
				//Store a clone of a list item so we can repopulate if data is found from another query
				list.prepend(listitem.clone().hide());
				return;
			}

			list.find('.list-item').remove();

			var formArray = currSearch.parents('form').serializeArray();
			var searchPos = 0;
			for(searchPos; searchPos < 100; searchPos++) {
				if(formArray[searchPos].name === 'Search') {
					break;
				}
			}
			$.each(data, function (i, value) {
				formArray[searchPos].value = value;
				url = currSearch.parents('form').attr('action') + '?' + $.param(formArray);
				list.prepend(listitem.clone()
					.children('a')
					.prop('href',url)
					.text(value)
					.parent()
				);
			});

			list.children('.list-item').show();
		});
	}

	/*
	 * Page Store
	 * Uses local storage to track search pages that have links clicked on them. This helps users allowing us to
	 * return recent searches the users has made and found useful
	 */
	var bindSearchCapture = function () {
		$(document).on('click', '#SearchResults a', function (e) {
			var url = location.pathname + location.search;
			var searchTerm = $('#SearchForm_getForm_Search').val();
			var item = {
				link: url,
				title: searchTerm,
				type: 'SearchTerms',
				class: ''
			}
			ESPPageStore.recordView(item);
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