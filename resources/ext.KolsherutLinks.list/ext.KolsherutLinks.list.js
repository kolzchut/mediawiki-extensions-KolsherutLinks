/* global window, document, mw, OO, $ */

/**
 * Note that this module relies on the jquery.tablesorter.pager bundle of 
 * the tablesorter and pager plugins and jQuery itself, all in versions
 * different from those that ship with MediaWiki. These versions of the
 * plugins are much more feature-rich and provide functionality required
 * by the KolsherutLinks admin interface, and the bundled jQuery base is
 * their dependency.
 */
mw.loader.using( [ 'jquery.tablesorter.pager' ], function () {
	// Define pager options
	var pagerOptions = {
		// target the pager markup - see the HTML block below
		container: $(".kolsherutlinks-list-pager"),
		// output string - default is '{page}/{totalPages}';
		// possible variables: {size}, {page}, {totalPages}, {filteredPages}, {startRow}, {endRow}, {filteredRows} and {totalRows}
		// also {page:input} & {startRow:input} will add a modifiable input in place of the value
		output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
		// if true, the table will remain the same height no matter how many records are displayed. The space is made up by an empty
		// table row set to a height to compensate; default is false
		fixedHeight: true,
		// remove rows from the table to speed up the sort of large tables.
		// setting this to false, only hides the non-visible rows; needed if you plan to add/remove rows with the pager enabled.
		removeRows: false,
		// go to page selector - select dropdown that sets the current page
		cssGoto: '.gotoPage'
	};

	// Initialize tablesorter and pager plugin
	$('table.kolsherut-links-links')
		.tablesorter({
			theme: 'blue',
			headerTemplate : '{content} {icon}', // new in v2.7. Needed to add the bootstrap icon!
			widthFixed: true,
			widgets: [/*'zebra',*/ 'columns', 'filter'],
			headers: {5: {sorter: false}}
		})
		.tablesorterPager(pagerOptions);

	$('table.kolsherut-links-rules')
		.tablesorter({
			theme: 'blue',
			headerTemplate : '{content} {icon}', // new in v2.7. Needed to add the bootstrap icon!
			widthFixed: true,
			widgets: ['columns', 'filter']
		})
		.tablesorterPager(pagerOptions);

} );
