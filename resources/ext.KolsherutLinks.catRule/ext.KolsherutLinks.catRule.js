/* global window, document, mw, $ */

mw.loader.using( [ 'jquery.suggestions' ], function () {
	// Hide all blank non-required category fields.
	$('.ksl-category-optional input:text')
		.filter(function() { return this.value == ""; })
		.parents('.mw-htmlform-field-HTMLTextField.ksl-category-name').hide();

	// As long as there are blank category fields, show an "Add Category" button.
	const handleChange = function () {
		// Make sure add-category link and maximum-reached notice exist.
		if ($('#ksl-add-category').length == 0) {
			$('.mw-htmlform-field-HTMLTextField.ksl-category-name').last().after(
				'<a href="#" id="ksl-add-category">' + mw.message( 'kolsherutlinks-details-category-add' ).text() + '</a>'
			).after(
				'<div id="ksl-category-maximum-reached">' + mw.message( 'kolsherutlinks-details-category-maximum-reached' ).text() + '</a>'
			);
		}
		// Are there any visible empty fields?
		$emptyVisibleFields = $('.ksl-category-name:visible input:text').filter(function() { return this.value == ""; });
		$emptyHiddenFields = $('.ksl-category-name:hidden input:text').filter(function() { return this.value == ""; });
		if ($emptyVisibleFields.length == 0 && $emptyHiddenFields.length == 0) {
			// Show maximum-reached notice and make sure add-category link is hidden.
			$('#ksl-category-maximum-reached').show();
			$('#ksl-add-category').hide();
		} else if ($emptyVisibleFields.length == 0) {
			// Make sure link is visible and notice is hidden.
			$('#ksl-add-category').show();
			$('#ksl-category-maximum-reached').hide();
		} else {
			// Hide link and notice.
			$('#ksl-add-category').hide();
			$('#ksl-category-maximum-reached').hide();
		}
	};
	$('.ksl-category-name input:text').keyup(handleChange);
	handleChange();

	// When add-category link is clicked, show the next hidden field.
	$('#ksl-add-category').click( function (e) {
		e.preventDefault();
		$('.mw-htmlform-field-HTMLTextField.ksl-category-name:hidden' ).first().show();
		$('#ksl-add-category').hide();
	} );

	// Attach content area and category suggestions to text inputs via jquery.suggestions.js
	const categories = mw.config.get('kslAllCategories');
	if ( categories != null ) {
		$('.ksl-category-name input[type=text]').suggestions({
			fetch: function ( val ) {
				var $el = $( this );
				$el.suggestions( 'suggestions', categories.filter( c => c.indexOf( val ) === 0 ) );
			}
		});
	}
	const contentAreas = mw.config.get('kslAllContentAreas');
	if ( contentAreas != null ) {
		$('.ksl-content-area-name input[type=text]').suggestions({
			fetch: function ( val ) {
				var $el = $( this );
				$el.suggestions( 'suggestions', contentAreas.filter( c => c.indexOf( val ) === 0 ) );
			}
		});
	}

} );
