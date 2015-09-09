;(function($) {
	window.ESPPageStore = new PageStore;
	$(window).load(function() {

		var search = $('input.extensible-search.typeahead');

		if(search.length) {

			bindSearchCapture();

			if (ESPPageStore.getType('SearchTerms').length && $('.recent-searches').length) {

				var resentsearch = $('.recentsearch').first().clone();
				$('.recentsearch').remove();

				$('.recent-searches-list').each(function(){
					var list = $(this);
					$.each(ESPPageStore.getType('SearchTerms'), function (i, term) {
						list.prepend(resentsearch.clone().children('a').prop('href',term.link).text(term.title).parent());
					});
				});
				$('.recent-searches').show();

				search.siblings('.esp-search-suggestions')
					.find('.panel > .list-group > .list-group-item > a')
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
				var suggestions = currSearch.siblings('.esp-search-suggestions');
				if(currSearch.val().length > 2 && suggestions.length > 0) {
					jQuery.each( currSearch.parents('form').serializeArray(), function(i, field) {
						data[field.name] = field.value;
					});
					$.get('extensible-search-api/getTypeahead',
						data
					)
					.success(function(data) {

						if(data === null) return;

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