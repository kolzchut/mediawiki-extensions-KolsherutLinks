<?php

namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use MediaWiki\MediaWikiServices;
use PermissionsError;
use SpecialPage;

/**
 * Filterable list view of Kol Sherut links and display rules.
 *
 * @ingroup SpecialPage
 */
class SpecialKolsherutLinksList extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'KolsherutLinksList' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'kolsherutlinks-list-desc' )->text();
	}

	/**
	 * Special page: Filterable list view of Kol Sherut links and display rules.
	 * @param string|null $par Parameters passed to the page
	 */
	public function execute( $par ) {
		$output = $this->getOutput();
		$this->setHeaders();

		// Does the user have access?
		$hasAccess = MediaWikiServices::getInstance()->getPermissionManager()->userHasRight(
			$this->getUser(), 'manage-kolsherut-links'
		);
		if ( !$hasAccess ) {
			throw new PermissionsError( 'manage-kolsherut-links' );
		}

		// Link back to list page
		$listPage = SpecialPage::getTitleFor( 'KolsherutLinksRules' );
		$output->addHTML(
			'<p class="ksl-list-link"><a href="' . $listPage->getLocalURL() . '">'
			. $this->msg( 'kolsherutlinks-details-all-rules-link' )->text() . '</a></p>'
		);

		// Query all rules and pull their categories.
		$linksCategories = [];
		$res = KolsherutLinks::getAllRules();
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			foreach ( [ 'content_area',	'category_1', 'category_2', 'category_3', 'category_4' ] as $field ) {
				if ( !empty( $row[$field] ) ) {
					$category = Category::newFromName( $row[$field] );
					$linksCategories[ $row['link_id'] ][ $category->getID() ] =
						!empty( $category ) ? $category->getTitle()->getBaseText() : $row[$field];
				}
			}
		}

		// Provide link to add new link.
		$detailsPage = SpecialPage::getTitleFor( 'KolsherutLinksDetails' );
		$addUrl = $detailsPage->getLocalURL( [ 'op' => 'create' ] );
		$output->addHTML( '
			<div class="ksl-links-add">
				<a class="btn btn-primary" href="' . $addUrl . '">' . $this->msg( 'kolsherutlinks-list-op-add' ) . '</a>
			</div>
		' );

		// Build sortable table with dynamic column seaching and pager.
		$output->allowClickjacking();
		$output->addModules( [ 'ext.KolsherutLinks.list', 'ext.KolsherutLinks.confirmation' ] );
		$output->addHTML( '
			<table class="mw-datatable kolsherut-links-links">
				<thead>
					<tr>
						<th>ID</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-url' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-text' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-pagecount' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-categories' ) . '</th>
						<th></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th>ID</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-url' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-text' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-pagecount' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-list-header-categories' ) . '</th>
						<th></th>
					</tr>
				</tfoot>
				<tbody>
		' );
		$res = KolsherutLinks::getAllLinks();
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$link_id = $row['link_id'];
			$detailsUrl = $detailsPage->getLocalURL( [ 'link_id' => $link_id ] );
			$deleteUrl = $detailsPage->getLocalURL( [ 'link_id' => $link_id, 'op' => 'delete' ] );
			$categories = '';
			if ( !empty( $linksCategories[ $link_id ] ) ) {
				asort( $linksCategories[ $link_id ] );
				$categories = implode( ', ', $linksCategories[ $link_id ] );
			}
			$deleteMsg = $this->msg( 'kolsherutlinks-list-op-delete' );
			$output->addHTML(
				'<tr>'
				. '<td>' . $link_id . '</td>'
				. '<td><a href="' . $detailsUrl . '">' . $row['url'] . '</a></td>'
				. '<td>' . $row['text'] . '</td>'
				. '<td>' . $row['pagecount'] . '</td>'
				. '<td>' . $categories . '</td>'
				. '<td><a class="kolsherutlinks-require-confirmation" data-confirmation-title="' . $deleteMsg
					. '" href="' . $deleteUrl . '">' . $this->msg( 'kolsherutlinks-list-op-delete' ) . '</a></td>'
				. '</tr>'
			);
		}
		$output->addHTML( '
				</tbody>
			</table>
			<!-- pager -->
			<div class="kolsherutlinks-list-pager tablesorter-pager">
				<form>
					<div class="pager-button first"></div>
					<div class="pager-button prev"></div>
					<span
						class="pagedisplay"
						data-pager-output-filtered
							="{startRow:input} &ndash; {endRow} / {filteredRows} of {totalRows} total rows"
					></span>
					<div class="pager-button next"></div>
					<div class="pager-button last"></div>
					<select class="pagesize">
						<option value="10">10</option>
						<option value="20">20</option>
						<option value="30">30</option>
						<option value="40">40</option>
						<option value="all">All Rows</option>
					</select>
				</form>
			</div>
		' );
	}

}
