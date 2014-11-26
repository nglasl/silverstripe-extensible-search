;(function($) {

	// Highlight the selected search suggestion.

	function highlight(input) {

		var colour = input.is(':checked') ? 'green' : 'red';
		input.css('box-shadow', '0 0 5px 4px #FFFAD6, 0 0 15px 5px ' + colour);
	}

	// Update the selected search suggestion.

	function update(input) {

	}

	// Bind the mouse events dynamically.

	$.entwine('ss', function($) {

		$('#Form_EditForm_Suggestions td.col-ApprovedField').entwine({

			// Trigger an interface update to highlight the selected search suggestion.

			onmouseenter: function() {

				$(this).next().children().hide();
				highlight($(this).children('input.approved'));
			},
			onmouseleave: function() {

				$(this).next().children().show();
				$(this).children('input.approved').css('box-shadow', 'none');
			},

			// Trigger an update against the selected search suggestion.

			onclick: function() {

				var input = $(this).children('input.approved');
				input.prop('checked', !input.prop('checked'));

				// Trigger the update.

				update(input);
				highlight(input);
				return false;
			}
		});

		// Prevent event propagation using a separate binding.

		$('#Form_EditForm_Suggestions input.approved').entwine({

			// Trigger an update against the selected search suggestion.

			onclick: function(event) {

				event.stopPropagation();
				var input = $(this);
				if(!input.is(':checked')) {
					$('#Form_EditForm').removeClass('changed');
				}

				// Trigger the update.

				update(input);
				highlight(input);
			}
		});
	});

})(jQuery);
