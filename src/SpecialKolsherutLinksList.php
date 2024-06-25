<?php

namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use Sanitizer;
use SpecialPage;
use TemplateParser;

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
		parent::__construct( 'KolsherutLinksList', 'manage-kolsherut-links' );
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

		// Prepare page body template.
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		$templateData = [];

		// Provide link to add new link.
		$detailsPage = SpecialPage::getTitleFor( 'KolsherutLinksDetails' );
		$templateData['addLink'] = [
			'url' => $detailsPage->getLocalURL( [ 'op' => 'create' ] ),
			'label' => $this->msg( 'kolsherutlinks-list-op-add' )->text(),
		];

		// Build sortable table with dynamic column seaching and pager.
		$templateData['header'] = [
			'url' => $this->msg( 'kolsherutlinks-list-header-url' )->text(),
			'text' => $this->msg( 'kolsherutlinks-list-header-text' )->text(),
			'pageCount' => $this->msg( 'kolsherutlinks-list-header-pagecount' )->text(),
			'categories' => $this->msg( 'kolsherutlinks-list-header-categories' )->text(),
		];
		$templateData['rows'] = [];
		$output->allowClickjacking();
		$output->addModules( [ 'ext.KolsherutLinks.list', 'ext.KolsherutLinks.confirmation' ] );
		$res = KolsherutLinks::getAllLinks();
		$deleteMsg = $this->msg( 'kolsherutlinks-list-op-delete' )->text();
		$deleteText = $this->msg( 'kolsherutlinks-list-op-delete' )->text();
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			// Category links list.
			$linkId = $row['link_id'];
			$categories = '';
			if ( !empty( $linksCategories[ $linkId ] ) ) {
				asort( $linksCategories[ $linkId ] );
				$categories = implode( ', ', $linksCategories[ $linkId ] );
			}
			// Build table row.
			$templateData['rows'][] = [
				'linkId' => $linkId,
				'detailsUrl' => $detailsPage->getLocalURL( [ 'link_id' => $linkId ] ),
				'detailsUrlToDisplay' =>
					strlen( $row['url'] ) > 45 ? ( substr( $row['url'], 0, 42 ) . '...' ) : $row['url'],
				'text' => Sanitizer::decodeCharReferences( $row['text'] ),
				'pageCount' => $row['pagecount'],
				'categories' => $categories,
				'deleteMsg' => $deleteMsg,
				'deleteUrl' => $detailsPage->getLocalURL( [ 'link_id' => $linkId, 'op' => 'delete' ] ),
				'deleteText' => $deleteText,
			];
		}

		// Provide link back to all rules page.
		$rulesPage = SpecialPage::getTitleFor( 'KolsherutLinksRules' );
		$templateData['rulesLink'] = [
			'url' => $rulesPage->getLocalURL(),
			'text' => $this->msg( 'kolsherutlinks-details-all-rules-link' )->text(),
		];

		// Process template and output.
		$output->addHTML(
			$templateParser->processTemplate( 'kolsherut-links-list', $templateData )
		);
	}

}
