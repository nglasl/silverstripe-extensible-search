;(function($) {

	// Update the selected search suggestion.

	function update(input) {

		// Trigger an update against the extensible search controller.

		$.post($('div.urlsegment a.preview').text() + '/toggleSuggestionApproved', {
			suggestion: input.closest('tr').data('id')
		});
	}

	// Bind the mouse events dynamically.

	$.entwine('ss', function($) {

		$('#Form_EditForm_Suggestions td.col-ApprovedField').entwine({

			// Trigger an interface update to represent edit functionality.

			onmouseenter: function() {

				$(this).next().children('a.edit-link').css('visibility', 'hidden');
			},
			onmouseleave: function() {

				$(this).next().children('a.edit-link').css('visibility', 'visible');
			},

			// Trigger an update against the selected search suggestion.

			onclick: function() {

				// Make sure this change is reflected in the respective field.

				var input = $(this).children('input.approved');
				input[0].checked = !input[0].checked;
				update(input);
				return false;
			}
		});

		// Prevent event propagation using a separate binding.

		$('#Form_EditForm_Suggestions input.approved').entwine({

			// Trigger an update against the selected search suggestion.

			onclick: function(event) {

				event.stopPropagation();

				// Make sure the edit form doesn't detect changes.

				$('#Form_EditForm').removeClass('changed');
				update($(this));
			}
		});
	});

})(jQuery);
