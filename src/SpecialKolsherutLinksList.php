<?php

namespace MediaWiki\Extension\KolsherutLinks;

use MediaWiki\MediaWikiServices;
use PermissionsError;
use SpecialPage;
use Title;

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

		// Query all rules to pull their categories.
		$dbw = wfGetDB( DB_PRIMARY );
		$links_categories = [];
		$res = $dbw->select(
			[
				'rules' => 'kolsherutlinks_rules',
				'content_area' => 'category',
				'category1' => 'category',
				'category2' => 'category',
				'category3' => 'category',
				'category4' => 'category',
		  ],
			[
				'link_id' => 'rules.link_id',
				'content_area_id' => 'rules.content_area_id',
				'category_id_1' => 'rules.category_id_1',
				'category_id_2' => 'rules.category_id_2',
				'category_id_3' => 'rules.category_id_3',
				'category_id_4' => 'rules.category_id_4',
				'content_area_title' => 'content_area.cat_title',
				'cat1_title' => 'category1.cat_title',
				'cat2_title' => 'category2.cat_title',
				'cat3_title' => 'category3.cat_title',
				'cat4_title' => 'category4.cat_title',
			],
			"",
			__METHOD__,
			[
				'ORDER BY' => 'rules.link_id ASC',
			],
			[
				'content_area' => [ 'LEFT JOIN', 'content_area.cat_id=rules.content_area_id' ],
				'category1' => [ 'LEFT JOIN', 'category1.cat_id=rules.category_id_1' ],
				'category2' => [ 'LEFT JOIN', 'category2.cat_id=rules.category_id_2' ],
				'category3' => [ 'LEFT JOIN', 'category3.cat_id=rules.category_id_3' ],
				'category4' => [ 'LEFT JOIN', 'category4.cat_id=rules.category_id_4' ],
			],
		);
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			foreach ( [
				'content_area_id' => 'content_area_title',
				'category_id_1' => 'cat1_title',
				'category_id_2' => 'cat2_title',
				'category_id_3' => 'cat3_title',
				'category_id_4' => 'cat4_title',
			] as $idField => $nameField ) {
				if ( !empty( $row[$idField] ) ) {
					$catTitle = Title::makeTitle( NS_CATEGORY, $row[$nameField] );
					$links_categories[ $row['link_id'] ][ $row[$idField] ] = $catTitle->getBaseText();
				}
			}
		}

		// Query all existing links to Kol Sherut.
		$res = $dbw->select(
			[
				'links' => 'kolsherutlinks_links',
				'pages' => 'kolsherutlinks_assignments',
		  ],
			[ 'links.link_id', 'links.url', 'links.text', 'COUNT(pages.link_id) pagecount' ],
			"",
			__METHOD__,
			[
				'GROUP BY' => 'links.link_id',
				'ORDER BY' => 'pagecount DESC, url ASC',
			],
			[
				'pages' => [ 'LEFT JOIN', 'pages.link_id=links.link_id' ],
			],
		);

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
			<table class="mw-datatable kolsherut-links">
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
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$link_id = $row['link_id'];
			$detailsUrl = $detailsPage->getLocalURL( [ 'link_id' => $link_id ] );
			$deleteUrl = $detailsPage->getLocalURL( [ 'link_id' => $link_id, 'op' => 'delete' ] );
			$categories = '';
			if ( !empty( $links_categories[ $link_id ] ) ) {
				asort( $links_categories[ $link_id ] );
				$categories = implode( ', ', $links_categories[ $link_id ] );
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
