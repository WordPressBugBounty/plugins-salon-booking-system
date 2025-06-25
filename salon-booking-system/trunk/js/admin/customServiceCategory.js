jQuery(function ($) {
    var addingTerm = false;

    sln_categoryLogo($);
    
    function sln_categorySend(e){
        e.preventDefault();
        e.stopPropagation();
		var form = $(this).parents('form');

		if ( addingTerm ) {
			// If we're adding a term, noop the button to avoid duplicate requests.
			return false;
		}

		addingTerm = true;
		form.find( '.submit .spinner' ).addClass( 'is-active' );

		/**
		 * Does a request to the server to add a new term to the database
		 *
		 * @param {string} r The response from the server.
		 *
		 * @return {void}
		 */
		let serializeArray = $('#addtag').serializeArray();
        let form_data = new FormData();
        serializeArray.forEach(function(e){form_data.append(e['name'], e['value'])})
		$('input[type="file"]').each(function(){form_data.append(this.name, $(this)[0].files[0])});
		$.ajax({
            url: ajaxurl,
            data: form_data, 
            contentType: false,
            processData: false,
            type: 'POST',
            success: function(r){
			var res, parent, term, indent, i;

			addingTerm = false;
			form.find( '.submit .spinner' ).removeClass( 'is-active' );

			$('#ajax-response').empty();
			res = wpAjax.parseAjaxResponse( r, 'ajax-response' );

			if ( res.errors && res.responses[0].errors[0].code === 'empty_term_name' ) {
				validateForm( form );
			}

			if ( ! res || res.errors ) {
				return;
			}

			parent = form.find( 'select#parent' ).val();

			// If the parent exists on this page, insert it below. Else insert it at the top of the list.
			if ( parent > 0 && $('#tag-' + parent ).length > 0 ) {
				// As the parent exists, insert the version with - - - prefixed.
				$( '.tags #tag-' + parent ).after( res.responses[0].supplemental.noparents );
			} else {
				// As the parent is not visible, insert the version with Parent - Child - ThisTerm.
				$( '.tags' ).prepend( res.responses[0].supplemental.parents );
			}

			$('.tags .no-items').remove();

			if ( form.find('select#parent') ) {
				// Parents field exists, Add new term to the list.
				term = res.responses[1].supplemental;

				// Create an indent for the Parent field.
				indent = '';
				for ( i = 0; i < res.responses[1].position; i++ )
					indent += '&nbsp;&nbsp;&nbsp;';

				form.find( 'select#parent option:selected' ).after( '<option value="' + term.term_id + '">' + indent + term.name + '</option>' );
			}

			$('input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="reset"]):visible, textarea:visible', form).val('');
		}
        });

		return false;
	}
    setTimeout(function(){$('#addtag #submit').off('click').on( 'click', sln_categorySend)}, 1);

});

function sln_categoryLogo($) {
    $("[data-action=select-logo]").on("click", function() {
        $("#" + $(this).attr("data-target")).trigger("click");
    });

    $("[data-action=select-file-logo]").on("change", function() {
        $(this)
            .closest("form")
            .find("input:first")
            .trigger("click");
            var reader = new FileReader();

        reader.onload = function (e) {
            $("#logo img").attr("src", e.target.result);
        }
        reader.readAsDataURL(this.files[0]);
        $("#logo img").attr("src", $(this).val());
        $("#" + $(this).attr("data-target")).val($(this).val());
        $('#logo').removeClass('hide');
        $('#select_logo').addClass('hide');
    });

    $("[data-action=delete-logo]").on("click", function() {
        $("#" + $(this).attr("data-target-reset")).val("");
        $("#" + $(this).attr("data-target-show")).removeClass("hide");
        $("#" + $(this).attr("data-target-remove")).addClass('hide');
    });
}