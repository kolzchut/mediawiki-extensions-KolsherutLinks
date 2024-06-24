<?php

namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use MediaWiki\MediaWikiServices;
use PermissionsError;
use SpecialPage;
use Title;

/**
 * Filterable list view of Kol Sherut rules and all potential assignments.
 *
 * @ingroup SpecialPage
 */
class SpecialKolsherutLinksRules extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'KolsherutLinksRules' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'kolsherutlinks-rules-desc' )->text();
	}

	/**
	 * Special page: Filterable list view of Kol Sherut links rules and possible assignments.
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

		// Provide link back to list page.
		$listPage = SpecialPage::getTitleFor( 'KolsherutLinksList' );
		$output->addHTML(
			'<p class="ksl-list-link"><a href="' . $listPage->getLocalURL() . '">'
			. $this->msg( 'kolsherutlinks-details-back-to-list' )->text() . '</a></p>'
		);

		// We need all rules, assignments, and potential assignments.
		$resPossibleAssignments = KolsherutLinks::getPossibleAssignments();
		$resAssignments = KolsherutLinks::getPageAssignments();
		$resRules = KolsherutLinks::getAllRules();

		// Index rules, prepare summaries.
		$rules = [];
		for ( $row = $resRules->fetchRow(); is_array( $row ); $row = $resRules->fetchRow() ) {
			$rules[ $row['rule_id'] ] = $row;
		}

		// Index active assignments.
		$assignments = [];
		for ( $row = $resAssignments->fetchRow(); is_array( $row ); $row = $resAssignments->fetchRow() ) {
			$assignments[ $row['page_id'] ][ $row['link_id'] ] = $row['link_id'];
		}

		// Build sortable table with dynamic column seaching and pager.
		$output->allowClickjacking();
		$output->addModules( [ 'ext.KolsherutLinks.list' ] );
		$output->addHTML( '
			<table class="mw-datatable kolsherut-links-rules">
				<thead>
					<tr>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-page' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-link' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-is-assigned' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-id' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-content-area' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-categories' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-is-fallback' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-priority' ) . '</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-page' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-link' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-is-assigned' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-id' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-content-area' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-categories' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-is-fallback' ) . '</th>
						<th>' . $this->msg( 'kolsherutlinks-rules-header-rule-priority' ) . '</th>
					</tr>
				</tfoot>
				<tbody>
		' );
		$detailsPage = SpecialPage::getTitleFor( 'KolsherutLinksDetails' );
		for (
			$possibleAssignment = $resPossibleAssignments->fetchRow();
			is_array( $possibleAssignment );
			$possibleAssignment = $resPossibleAssignments->fetchRow()
		) {
			// Collate data about the possible assignment and the link and rule involved.
			$link_id = $possibleAssignment['link_id'];
			$linkDetailsUrl = $detailsPage->getLocalURL( [ 'link_id' => $link_id ] );
			$rule = $rules[ $possibleAssignment['rule_id'] ];

			// Build table row.
			$tableRow = '<tr>';
			// Page name with link (in new tab/window)
			$page = Title::newFromID( $possibleAssignment['page_id'] );
			$tableRow .= '<td><a target="_blank" href="' . $page->getLocalURL() . '">' . $page->getBaseText()
				. '</a></td>';
			// Link URL and admin
			$urlToDisplay = strlen( $rule['link_url'] ) > 45 ?
				( substr( $rule['link_url'], 0, 42 ) . '...' ) : $rule['link_url'];
			$tableRow .= '<td><a href="' . $linkDetailsUrl . '">' . $urlToDisplay . '</a></td>';
			// Currently has a page assignment?
			$tableRow .= '<td>'
				. ( !empty( $assignments[ $possibleAssignment['page_id'] ][ $rule['link_id'] ] ) ? 'X' : '-' )
				. '</td>';
			// Rule ID
			$tableRow .= '<td>' . $possibleAssignment['rule_id'] . '</td>';
			// Content area name with link (in new tab/window)
			if ( !empty( $rule['content_area'] ) ) {
				$category = Category::newFromName( $rule['content_area'] );
				if ( !empty( $category ) ) {
					$tableRow .= '<td><a target="_blank" href="' . $category->getTitle()->getLocalURL() . '">'
					. $category->getTitle()->getBaseText() . '</a></td>';
				} else {
					$tableRow .= '<td>' . $rule['content_area'] . '</td>';
				}
			} else {
				$tableRow .= '<td>-</td>';
			}
			// Category name(s) with link(s) (in new tab/window)
			if ( !empty( $rule['category_1'] ) ) {
				$links = [];
				foreach ( array_filter( [
					$rule['category_1'], $rule['category_2'], $rule['category_3'], $rule['category_4']
				] ) as $categoryName ) {
					$category = Category::newFromName( $categoryName );
					if ( !empty( $category ) ) {
						$links[] = '<a target="_blank" href="' . $category->getTitle()->getLocalURL() . '">'
						. $category->getTitle()->getBaseText() . '</a>';
					} else {
						$links[] = $categoryName;
					}
				}
				$tableRow .= '<td>' . implode( ' + ', $links ) . '</td>';
			} else {
				$tableRow .= '<td>-</td>';
			}
			// Is fallback?
			$tableRow .= '<td>' . ( $rule['fallback'] ? 'X' : '-' ) . '</td>';
			// Priority score
			$tableRow .= "<td>{$rule['priority']}</td>";
			// Close row and output.
			$tableRow .= '</tr>';
			$output->addHTML( $tableRow );
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
