;(function($) {

	// Toggle a search suggestion's approval.

	function update(input) {

		$.post('extensible-search-api/toggleSuggestionApproved', {
			suggestion: input.closest('tr').data('id')
		});
	}

	// Trigger an interface update to represent edit functionality.

	$(document).on('mouseenter', '#Form_EditForm_Suggestions td.col-ApprovedField', function() {

		$(this).next().children('a.edit-link').css('visibility', 'hidden');
	});

	$(document).on('mouseleave', '#Form_EditForm_Suggestions td.col-ApprovedField', function() {

		$(this).next().children('a.edit-link').css('visibility', 'visible');
	});

	// Toggle the selected search suggestion's approval.

	$(document).on('click', '#Form_EditForm_Suggestions td.col-ApprovedField', function() {

		// Make sure this change is reflected in the respective field.

		var input = $(this).children('input.approved');
		input[0].checked = !input[0].checked;
		update(input);
		return false;
	});

	// Prevent event propagation using a separate binding.

	$(document).on('click', '#Form_EditForm_Suggestions input.approved', function(event) {

		event.stopPropagation();

		// Make sure the edit form doesn't detect changes.

		$('#Form_EditForm').removeClass('changed');
		update($(this));
	});

})(jQuery);
