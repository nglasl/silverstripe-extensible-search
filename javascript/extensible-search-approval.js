;(function($) {

	// Highlight the selected search suggestion.

	function highlight(input) {

		var colour = input[0].checked ? 'green' : 'red';
		input.css('box-shadow', '0 0 5px 4px #FFFAD6, 0 0 15px 5px ' + colour);
	}

	// Update the selected search suggestion.

	function update(input) {

		// Trigger an update against the extensible search controller.

		$.post($('div.urlsegment a.preview').text() + '/suggestionApproval', {
			suggestion: input.closest('tr').data('id'),
			approved: input[0].checked ? 1 : 0
		},
		function() {

			// Trigger an interface update to represent the current change.

			highlight(input);
		});
	}

	// Bind the mouse events dynamically.

	$.entwine('ss', function($) {

		$('#Form_EditForm_Suggestions td.col-ApprovedField').entwine({

			// Trigger an interface update to the edit button visibility.

			onmouseenter: function() {

				$(this).next().children('a.edit-link').css('visibility', 'hidden')
			},
			onmouseleave: function() {

				$(this).next().children('a.edit-link').css('visibility', 'visible')
			},

			// Trigger an update against the selected search suggestion.

			onclick: function() {

				var input = $(this).children('input.approved');
				input[0].checked = !input[0].checked;

				// Trigger the update.

				update(input);
				return false;
			}
		});

		// Prevent event propagation using a separate binding.

		$('#Form_EditForm_Suggestions input.approved').entwine({

			// Trigger an update against the selected search suggestion.

			onclick: function(event) {

				event.stopPropagation();
				var input = $(this);
				if(!input[0].checked) {
					$('#Form_EditForm').removeClass('changed');
				}

				// Trigger the update.

				update(input);
			}
		});
	});

})(jQuery);
