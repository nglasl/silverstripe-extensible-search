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

						$.each(ESPPageStore.getType('SearchTerms'), function (i, term) {
							overlay.find('.recent-searches-list').prepend(
								resentsearch.clone()
									.children('a')
									.prop('href',term.link)
									.text(term.title)
									.parent()
							);
						});

						$('.recent-searches').show();

						overlay.find('div > ul > li.list-item')
							.each(function(){
								$(this).attr('href',
									'//' + window.location.host
									+ $(this).parents('form').attr('action')
									+ '?Search=' + $(this).text()
								);
							});
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
					});
				}
			});

		}

	});

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

/*
 * jQuery Dropdown: A simple dropdown plugin
 *
 * Contribute: https://github.com/claviska/jquery-dropdown
 *
 * @license: MIT license: http://opensource.org/licenses/MIT
 *
 */
if (jQuery) (function ($) {

    $.extend($.fn, {
        jqDropdown: function (method, data) {

            switch (method) {
                case 'show':
                    show(null, $(this));
                    return $(this);
                case 'hide':
                    hide();
                    return $(this);
                case 'attach':
                    return $(this).attr('data-jq-dropdown', data);
                case 'detach':
                    hide();
                    return $(this).removeAttr('data-jq-dropdown');
                case 'disable':
                    return $(this).addClass('jq-dropdown-disabled');
                case 'enable':
                    hide();
                    return $(this).removeClass('jq-dropdown-disabled');
            }

        }
    });

    function show(event, object) {

        var trigger = event ? $(this) : object,
            jqDropdown = trigger.siblings('.esp-dropdown').first(),
            isOpen = trigger.hasClass('jq-dropdown-open');

        // In some cases we don't want to show it
        if (event) {
            if ($(event.target).hasClass('jq-dropdown-ignore')) return;

            event.preventDefault();
            event.stopPropagation();
        } else {
            if (trigger !== object.target && $(object.target).hasClass('jq-dropdown-ignore')) return;
        }
        hide();

        if (isOpen || trigger.hasClass('jq-dropdown-disabled')) return;

        // Show it
        trigger.addClass('jq-dropdown-open');
        jqDropdown
            .data('jq-dropdown-trigger', trigger)
            .show();

        // Trigger the show callback
        jqDropdown
            .trigger('show', {
                jqDropdown: jqDropdown,
                trigger: trigger
            });

    }

    function hide(event) {

        // In some cases we don't hide them
        var targetGroup = event ? $(event.target).parents() : null;

        // Are we clicking anywhere in a jq-dropdown?
        if (targetGroup && targetGroup.is('.jq-dropdown')) {
            // Is it a jq-dropdown menu?
            if (targetGroup.is('.jq-dropdown-menu')) {
                // Did we click on an option? If so close it.
                if (!targetGroup.is('A')) return;
            } else {
                // Nope, it's a panel. Leave it open.
                return;
            }
        }

        // Hide any jq-dropdown that may be showing
        $(document).find('.jq-dropdown:visible').each(function () {
            var jqDropdown = $(this);
            jqDropdown
                .hide()
                .removeData('jq-dropdown-trigger')
                .trigger('hide', { jqDropdown: jqDropdown });
        });

        // Remove all jq-dropdown-open classes
        $(document).find('.jq-dropdown-open').removeClass('jq-dropdown-open');

    }

    $(document).on('click.jq-dropdown', '[data-jq-dropdown]', show);
    $(document).on('click.jq-dropdown', hide);

})(jQuery);