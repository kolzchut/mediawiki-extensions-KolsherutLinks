<?php

namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use Html;
use SpecialPage;
use TemplateParser;
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
		parent::__construct( 'KolsherutLinksRules', 'manage-kolsherut-links' );
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

		// Prepare page body template.
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		$templateData = [];

		// Build sortable table with dynamic column seaching and pager.
		$templateData['header'] = [
			'page' => $this->msg( 'kolsherutlinks-rules-header-page' )->text(),
			'link' => $this->msg( 'kolsherutlinks-rules-header-rule-link' )->text(),
			'isAssigned' => $this->msg( 'kolsherutlinks-rules-header-rule-is-assigned' )->text(),
			'ruleId' => $this->msg( 'kolsherutlinks-rules-header-rule-id' )->text(),
			'contentArea' => $this->msg( 'kolsherutlinks-rules-header-rule-content-area' )->text(),
			'categories' => $this->msg( 'kolsherutlinks-rules-header-rule-categories' )->text(),
			'isFallback' => $this->msg( 'kolsherutlinks-rules-header-rule-is-fallback' )->text(),
			'priority' => $this->msg( 'kolsherutlinks-rules-header-rule-priority' )->text(),
		];
		$templateData['pager'] = [
			'narrative' => $this->msg( 'kolsherutlinks-table-pager-narrative' )->text(),
			'allRows' => $this->msg( 'kolsherutlinks-table-pager-all-rows' )->text(),
		];
		$templateData['rows'] = [];
		$output->allowClickjacking();
		$output->addModules( [ 'ext.KolsherutLinks.list' ] );
		$detailsPage = SpecialPage::getTitleFor( 'KolsherutLinksDetails' );
		for (
			$possibleAssignment = $resPossibleAssignments->fetchRow();
			is_array( $possibleAssignment );
			$possibleAssignment = $resPossibleAssignments->fetchRow()
		) {
			// Collate data about the possible assignment and the link and rule involved.
			$link_id = $possibleAssignment['link_id'];
			$rule = $rules[ $possibleAssignment['rule_id'] ];

			// Build table row.
			$row = [];
			// Page name with link (in new tab/window)
			$page = Title::newFromID( $possibleAssignment['page_id'] );
			$row['pageUrl'] = $page->getLocalURL();
			$row['pageTitle'] = $page->getBaseText();
			// Link URL and admin
			$row['linkDetailsUrl'] = $detailsPage->getLocalURL( [ 'link_id' => $link_id ] );
			$row['linkUrlToDisplay'] = strlen( $rule['link_url'] ) > 45 ?
				( substr( $rule['link_url'], 0, 42 ) . '...' ) : $rule['link_url'];
			// Currently has a page assignment?
			$row['isAssigned'] = !empty( $assignments[ $possibleAssignment['page_id'] ][ $rule['link_id'] ] ) ?
				'X' : '-';
			// Rule ID
			$row['ruleId'] = $possibleAssignment['rule_id'];
			// Content area name with link (in new tab/window)
			if ( !empty( $rule['content_area'] ) ) {
				$category = Category::newFromName( $rule['content_area'] );
				$row['contentArea'] = !empty( $category ) ? $category->getTitle()->getBaseText() :
					$rule['content_area'];
				$row['contentAreaUrl'] = !empty( $category ) ? $category->getTitle()->getLocalURL() : false;
			} else {
				$row['contentArea'] = '-';
			}
			// Category name(s) with link(s) (in new tab/window)
			if ( !empty( $rule['category_1'] ) ) {
				$links = [];
				foreach ( array_filter( [
					$rule['category_1'], $rule['category_2'], $rule['category_3'], $rule['category_4']
				] ) as $categoryName ) {
					$category = Category::newFromName( $categoryName );
					if ( !empty( $category ) ) {
						$links[] = Html::openElement( 'a', [
							'target' => '_blank',
							'href' => $category->getTitle()->getLocalURL()
						] )
						. $category->getTitle()->getBaseText()
						. Html::closeElement( 'a' );
					} else {
						$links[] = $categoryName;
					}
				}
				$row['categories'] = implode( ' + ', $links );
			} else {
				$row['categories'] = '-';
			}
			// Is fallback?
			$row['isFallback'] = $rule['fallback'] ? 'X' : '-';
			// Priority score
			$row['priority'] = $rule['priority'];
			// Close row and output.
			$templateData['rows'][] = $row;
		}

		// Provide link back to list page.
		$listPage = SpecialPage::getTitleFor( 'KolsherutLinksList' );
		$templateData['listLink'] = [
			'url' => $listPage->getLocalURL(),
			'text' => $this->msg( 'kolsherutlinks-details-back-to-list' )->text(),
		];

		// Process template and output.
		$output->addHTML(
			$templateParser->processTemplate( 'kolsherut-links-rules', $templateData )
		);
	}

}
