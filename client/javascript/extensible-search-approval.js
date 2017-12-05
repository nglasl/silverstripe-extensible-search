;(function($) {

	var page = $(document);

	// Toggle a search suggestion's approval.

	function update(input) {

		$.post('extensible-search-api/toggleSuggestionApproved', {
			suggestion: input.closest('tr').data('id')
		});
	}

	// Trigger an interface update to represent edit functionality.

	page.on('mouseenter', '#Form_EditForm_Suggestions td.col-ApprovedField', function() {

		$(this).next().children('a.edit-link').css('visibility', 'hidden');
	});

	page.on('mouseleave', '#Form_EditForm_Suggestions td.col-ApprovedField', function() {

		$(this).next().children('a.edit-link').css('visibility', 'visible');
	});

	// Toggle the selected search suggestion's approval.

	page.on('click', '#Form_EditForm_Suggestions td.col-ApprovedField', function() {

		// Make sure this change is reflected in the respective field.

		var input = $(this).children('input.approved');
		input[0].checked = !input[0].checked;
		update(input);
		return false;
	});

	// Prevent event propagation using a separate binding.

	page.on('click', '#Form_EditForm_Suggestions input.approved', function(event) {

		event.stopPropagation();
		update($(this));

		// This is required to ensure the events are triggered in the correct order.

		setTimeout(function() {

			// Make sure the edit form doesn't detect changes.

			$('form#Form_EditForm').removeClass('changed');
		}, 0);
	});

})(jQuery);
