;(function ($) {
	$(function () {
		$('.searchPageForm input[name=Search]').entwine({
			onmatch: function (e) {
				var form = $(this).parents('form');
				var url = form.attr('action').replace(/getForm/, 'suggest');
				
				$(this).autocomplete({
					source: function (request, response) {
						$.get(url, {term: request.term}).success(function (data) {
							response(data.results);
						})
					},
					minLength: 3
				})
			}
		})
	});
})(jQuery);