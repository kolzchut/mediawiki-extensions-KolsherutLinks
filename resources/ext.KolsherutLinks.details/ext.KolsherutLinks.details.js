/* global window, document, mw, $ */

mw.loader.using( [ 'mediawiki.api', 'jquery.tablesorter.pager' ], function () {
	$('table.kolsherut-page-rules').tablesorter({
		theme: 'blue',
		headerTemplate : '{content} {icon}', // new in v2.7. Needed to add the bootstrap icon!
		widthFixed: true,
		widgets: ['columns'],
		headers: {1: {sorter: false}}
	});
	$('table.kolsherut-content-area-rules').tablesorter({
		theme: 'blue',
		headerTemplate : '{content} {icon}', // new in v2.7. Needed to add the bootstrap icon!
		widthFixed: true,
		widgets: ['columns'],
		headers: {2: {sorter: false}}
	});
	$('table.kolsherut-category-rules').tablesorter({
		theme: 'blue',
		headerTemplate : '{content} {icon}', // new in v2.7. Needed to add the bootstrap icon!
		widthFixed: true,
		widgets: ['columns'],
		headers: {3: {sorter: false}}
	});
} );
