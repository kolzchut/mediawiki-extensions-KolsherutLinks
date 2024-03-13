<?php

namespace MediaWiki\Extension\KolsherutLinks;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;

/**
 * Details view and create/edit form for a Kol Sherut link and its display rules.
 *
 * @ingroup SpecialPage
 */
class SpecialKolsherutLinksDetails extends \SpecialPage {
  private \Psr\Log\LoggerInterface $logger;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'KolsherutLinksDetails' );
		$this->logger = LoggerFactory::getInstance( 'KolsherutLinks' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'kolsherutlinks-details-desc' )->text();
	}

	/**
	 * Special page: Details view and create/edit form for a Kol Sherut link and its display rules.
	 * @param string|null $par Parameters passed to the page
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$request = $this->getRequest();
		$postValues = $request->getPostValues();
		$queryParams = $request->getQueryValues();
		$output = $this->getOutput();

		// Process and build page by operation.
		$op = !empty( $postValues['wpkslOp'] ) ? $postValues['wpkslOp'] :
			( !empty( $queryParams['op'] ) ? $queryParams['op'] : 'display' );
		$linkId = !empty( $postValues['wpkslLinkId'] ) ? $postValues['wpkslLinkId'] :
			( !empty( $queryParams['link_id'] ) ? $queryParams['link_id'] : null );
		switch ( $op ) {
			case 'display':
				if ( empty( $linkId ) ) {
					// Can't display link without an ID. Redirect to list view.
					$detailsPage = \SpecialPage::getTitleFor( 'KolsherutLinksList' );
					$output->redirect( $detailsPage->getLocalURL(), '303' );
				} else {
					$this->showDetailsPage( $linkId );
					$output->addModules( 'ext.KolsherutLinks.confirmation' );
				}
				break;
			case 'create':
			case 'edit':
				$output->setPageTitle( $this->msg(
					$op == 'edit' ? 'kolsherutlinks-details-title-edit' : 'kolsherutlinks-details-title-create'
				) );
				$htmlForm = \HTMLForm::factory( 'ooui', $this->getLinkEditForm( $op, $linkId ), $this->getContext() );
				$htmlForm->setId( 'kolsherutLinksLinkForm' )
					->setFormIdentifier( 'kolsherutLinksLinkForm' )
					->setSubmitName( "ksl-submit" )
					->setSubmitTextMsg( 'kolsherutlinks-details-link-submit' )
					->setSubmitCallback( [ $this, 'handleLinkSave' ] )
					->show();
				break;
			case 'delete':
				$this->handleLinkDelete( $queryParams['link_id'] );
				break;
			case 'add_page':
				$output->setPageTitle( $this->msg( 'kolsherutlinks-details-title-add-page' ) );
				$htmlForm = \HTMLForm::factory( 'ooui', $this->getAddPageForm( $linkId ), $this->getContext() );
				$htmlForm->setId( 'kolsherutLinksAddPageForm' )
					->setFormIdentifier( 'kolsherutLinksAddPageForm' )
					->setSubmitName( "ksl-submit" )
					->setSubmitTextMsg( 'kolsherutlinks-details-rule-submit' )
					->setSubmitCallback( [ $this, 'handlePageRuleSave' ] )
					->show();
				break;
			case 'add_category':
				$output->setPageTitle( $this->msg( 'kolsherutlinks-details-title-add-category' ) );
				$htmlForm = \HTMLForm::factory( 'ooui', $this->getAddCategoryForm( $linkId ), $this->getContext() );
				$htmlForm->setId( 'kolsherutLinksAddCategoryForm' )
					->setFormIdentifier( 'kolsherutLinksAddCategoryForm' )
					->setSubmitName( "ksl-submit" )
					->setSubmitTextMsg( 'kolsherutlinks-details-rule-submit' )
					->setSubmitCallback( [ $this, 'handleCategoryRuleSave' ] )
					->show();
				$output->addJsConfigVars( [
					'kslAllContentAreas' => array_values( $this->getAllContentAreas() ),
					'kslAllCategories' => array_values( $this->getAllCategories() ),
				] );
				$output->addModules( 'ext.KolsherutLinks.catRule' );
				break;
			case 'delete_rule':
				$this->handleRuleDelete( $queryParams['link_id'], $queryParams['rule_id'] );
				break;
		}
	}

	/**
	 * Display the link+rules details page.
	 * @param int $linkId
	 */
	public function showDetailsPage( $linkId ) {
		$output = $this->getOutput();
		$output->addModules( 'ext.KolsherutLinks.details' );

		// Link back to list page
		$detailsPage = \SpecialPage::getTitleFor( 'KolsherutLinksList' );
		$output->addHTML(
			'<p class="ksl-details-list-link"><a href="' . $detailsPage->getLocalURL() . '">'
			. $this->msg( 'kolsherutlinks-details-back-to-list' )->text() . '</a></p>'
		);

		// Basic link details
		$link = KolsherutLinks::getLinkDetails( $linkId );
		$output->addHTML( "<h3>" . $this->msg( 'kolsherutlinks-details-label-url' ) . "</h3>" );
		$output->addHTML(
			"<p class=\"ksl-details-item\"><a href=\"{$link['url']}\" target=\"_blank\">{$link['url']}</a></p>"
		);
		$output->addHTML( "<h3>" . $this->msg( 'kolsherutlinks-details-label-text' ) . "</h3>" );
		$output->addHTML( '<p class="ksl-details-item">' . $link['text'] . '</p>' );

		// Edit button.
		$editUrl = $output->getTitle()->getLocalURL( [ 'op' => 'edit', 'link_id' => $linkId ] );
		$output->addHTML( '
			<div class="ksl-details-edit">
				<a class="btn btn-primary" href="' . $editUrl . '">'
				. $this->msg( 'kolsherutlinks-details-op-edit' )
				. '</a>
			</div>
		' );

		// Page rules
		$output->addHTML( '<h2>' . $this->msg( 'kolsherutlinks-details-label-page-rules' ) . '</h2>' );
		$tableBody = '';
		$deleteMsg = $this->msg( 'kolsherutlinks-list-op-delete' );
		$res = KolsherutLinks::getLinkRules( $linkId );
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$page = \Title::newFromID( $row['page_id'] );
			$tableBody .= '<tr>';
			// Page title with link (in new tab/window)
			$pageUrl = $page->getLocalURL();
			$pageTitle = $page->getTitleValue()->getText();
			$tableBody .= "<td><a target=\"_blank\" href=\"{$pageUrl}\">{$pageTitle}</a></td>";
			// Link to delete this rule
			$deleteRuleUrl = $output->getTitle()->getLocalURL( [
				'op' => 'delete_rule',
				'rule_id' => $row['rule_id'],
				'link_id' => $linkId,
			] );
			$tableBody .= '<td><a class="kolsherutlinks-require-confirmation" data-confirmation-title="' . $deleteMsg
			  . '" href="' . $deleteRuleUrl . '">' . $deleteMsg . '</a></td>';
			$tableBody .= "</tr>";
		}
		if ( !empty( $tableBody ) ) {
			$output->addHTML( '
				<table class="mw-datatable kolsherut-page-rules">
					<thead>
						<tr>
							<th>' . $this->msg( 'kolsherutlinks-details-rule-header-page' ) . '</th>
							<th></th>
						</tr>
					</thead>
					<tbody>' . $tableBody . '</tbody>
				</table>
			' );
		}

		// Add page rule button
		$addPageUrl = $output->getTitle()->getLocalURL( [ 'op' => 'add_page', 'link_id' => $linkId ] );
		$output->addHTML( '
			<div class="ksl-details-add-page">
				<a class="btn btn-primary" href="' . $addPageUrl . '">'
				. $this->msg( 'kolsherutlinks-details-op-add-page' )
				. '</a>
			</div>
		' );

		// Category rules
		$output->addHTML( '<h2>' . $this->msg( 'kolsherutlinks-details-label-category-rules' ) . '</h2>' );
		$res = KolsherutLinks::getLinkCategoryRules( $linkId );
		$tableBody = '';
		$deleteMsg = $this->msg( 'kolsherutlinks-list-op-delete' );
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$tableBody .= '<tr>';
			// Content area name with link (in new tab/window)
			if ( !empty( $row['content_area_title'] ) ) {
				$caTitle = \Title::makeTitle( NS_CATEGORY, $row['content_area_title'] );
				$tableBody .= '<td><a target="_blank" href="' . $caTitle->getLocalURL() . '">'
					. $caTitle->getBaseText() . '</a></td>';
			} else {
				$tableBody .= '<td></td>';
			}
			// Category name(s) with link(s) (in new tab/window)
			if ( !empty( $row['cat1_title'] ) ) {
				$links = [];
				foreach ( array_filter( [
					$row['cat1_title'], $row['cat2_title'], $row['cat3_title'], $row['cat4_title']
				] ) as $categoryName ) {
					$catTitle = \Title::makeTitle( NS_CATEGORY, $categoryName );
					$links[] = '<a target="_blank" href="' . $catTitle->getLocalURL() . '">' . $catTitle->getBaseText()
						. '</a>';
				}
				$tableBody .= '<td>' . implode( ' + ', $links ) . '</td>';
			} else {
				$tableBody .= '<td></td>';
			}
			// Is fallback?
			$tableBody .= '<td>' . ( $row['fallback'] ? 'X' : '' ) . '</td>';
			// Priority score
			$tableBody .= "<td>{$row['priority']}</td>";
			// Link to delete this rule
			$deleteRuleUrl = $output->getTitle()->getLocalURL( [
				'op' => 'delete_rule',
				'rule_id' => $row['rule_id'],
				'link_id' => $linkId,
			] );
			$tableBody .= '<td><a class="kolsherutlinks-require-confirmation" data-confirmation-title="' . $deleteMsg
			  . '" href="' . $deleteRuleUrl . '">' . $deleteMsg . '</a></td>';
			$tableBody .= "</tr>";
		}
		if ( !empty( $tableBody ) ) {
			$output->addHTML( '
				<table class="mw-datatable kolsherut-category-rules">
					<thead>
						<tr>
							<th>' . $this->msg( 'kolsherutlinks-details-rule-header-content-area' ) . '</th>
							<th>' . $this->msg( 'kolsherutlinks-details-rule-header-categories' ) . '</th>
							<th>' . $this->msg( 'kolsherutlinks-details-rule-header-fallback' ) . '</th>
							<th>' . $this->msg( 'kolsherutlinks-details-rule-header-priority' ) . '</th>
							<th></th>
						</tr>
					</thead>
					<tbody>' . $tableBody . '</tbody>
				</table>
			' );
		}

		// Add category/ies rule button
		$addCategoryUrl = $output->getTitle()->getLocalURL( [ 'op' => 'add_category', 'link_id' => $linkId ] );
		$output->addHTML( '
			<div class="ksl-details-add-category">
				<a class="btn btn-primary" href="' . $addCategoryUrl . '">'
				. $this->msg( 'kolsherutlinks-details-op-add-category' )
				. '</a>
			</div>
		' );

		// Link assignments
		$output->addHTML( "<h2>" . $this->msg( 'kolsherutlinks-details-title-assignments' ) . "</h2>" );
		$res = KolsherutLinks::getPageAssignmentsByLink( $linkId );
		$listBody = '';
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$title = \WikiPage::newFromID( $row['page_id'] )->getTitle();
			$listBody .= '<li><a href="' . $title->getLocalURL() . '">' . $title->getBaseText() . '</a></li>';
		}
		if ( !empty( $listBody ) ) {
			$output->addHTML( '<ul class="ksl-link-assignments">' . $listBody . '</ul>' );
		} else {
			$output->addHTML( '<p class="ksl-link-assignments-empty">'
				. $this->msg( 'kolsherutlinks-details-assignments-empty' )->text() . '</p>' );
		}

		// Delete link button
		$output->addHTML( "<h2>" . $this->msg( 'kolsherutlinks-details-title-delete' ) . "</h2>" );
		$deleteLinkUrl = $output->getTitle()->getLocalURL( [ 'op' => 'delete', 'link_id' => $linkId ] );
		$deleteMsg = $this->msg( 'kolsherutlinks-details-op-link-delete' );
		$output->addHTML( '
			<div class="ksl-details-edit">
				<a class="btn btn-primary kolsherutlinks-require-confirmation" data-confirmation-title="' . $deleteMsg
					. '" href="' . $deleteLinkUrl . '">' . $deleteMsg
				. '</a>
			</div>
		' );
	}

	/**
	 * Define link create/edit form structure
	 * @param array $op
	 * @param int|null $linkId Link ID (when editing)
	 * @return array
	 */
	private function getLinkEditForm( $op, $linkId ) {
		$form = [
			'kslLinkUrl' => [
				'type' => 'text',
				'cssclass' => 'ksl-link-url',
				'label-message' => 'kolsherutlinks-details-label-url',
				'required' => true,
			],
			'kslLinkText' => [
				'type' => 'textarea',
				'cssclass' => 'ksl-link-text',
				'label-message' => 'kolsherutlinks-details-label-text',
				'required' => true,
				'default' => '',
				'rows' => 3,
			],
			'kslLinkId' => [
				'type' => 'hidden',
				'default' => $linkId,
			],
			'kslOp' => [
				'type' => 'hidden',
				'default' => $op,
			],
		];
		if ( !empty( $linkId ) ) {
			$link = KolsherutLinks::getLinkDetails( $linkId );
			if ( empty( $link ) ) {
				// Error: couldn't query the link we're trying to edit.
				throw new MWException( $this->msg( 'kolsherutlinks-details-error-not-found', $linkId ) );
			}
			$form['kslLinkUrl']['default'] = $link['url'];
			$form['kslLinkText']['default'] = $link['text'];
		}
		return $form;
	}

	/**
	 * Handle link details form submission (create or edit)
	 * @param array $postData Form submission data
	 * @return string|bool Return true on success, error message on failure
	 */
	public function handleLinkSave( $postData ) {
		$output = $this->getOutput();

		// @TODO: check URL format?

		// Save link to DB.
		$data = [
			'url' => $postData['kslLinkUrl'],
			'text' => $postData['kslLinkText'],
		];
		if ( !empty( $postData['kslLinkId'] ) ) {
			$data['link_id'] = $postData['kslLinkId'];
		}
		$linkId = KolsherutLinks::saveLinkDetails( $data );
		if ( $linkId === false ) {
			// Save failed for some reason.
			return 'kolsherutlinks-details-error-failed-save';
		}

		// Redirect to display link details.
		$displayUrl = $output->getTitle()->getLocalURL( [ 'link_id' => $linkId ] );
		$output->redirect( $displayUrl, '303' );
		return true;
	}

	/**
	 * Define page rule form structure
	 * @param int $linkId
	 * @return array
	 */
	private function getAddPageForm( $linkId ) {
		$link = KolsherutLinks::getLinkDetails( $linkId );
		if ( empty( $link ) ) {
			// Error: couldn't query the link we're trying to add this rule to.
			throw new MWException( $this->msg( 'kolsherutlinks-details-error-not-found', $linkId ) );
		}
		$form = [
			'kslLinkUrl' => [
				'type' => 'info',
				'label-message' => 'kolsherutlinks-details-label-page-link-url',
				'default' => "<a target=\"_blank\" href=\"{$link['url']}\">{$link['url']}</a>",
				'raw' => true,
			],
			'kslPageTitle' => [
				'type' => 'title',
				'cssclass' => 'ksl-page-title',
				'label-message' => 'kolsherutlinks-details-label-page',
				'required' => true,
			],
			'kslLinkId' => [
				'type' => 'hidden',
				'default' => $linkId,
			],
			'kslOp' => [
				'type' => 'hidden',
				'default' => 'add_page',
			],
		];
		return $form;
	}

	/**
	 * Handle add page rule form submission
	 * @param array $postData Form submission data
	 * @return string|bool Return true on success, error message on failure
	 */
	public function handlePageRuleSave( $postData ) {
		$output = $this->getOutput();
		$linkId = $postData['kslLinkId'];

		// Get page ID from title
		$title = \Title::newFromText( $postData['kslPageTitle'] );
		if ( !$title->exists() ) {
			// Title not found.
			return 'kolsherutlinks-details-error-page-not-found';
		}

		// Insert new rule
		$res = KolsherutLinks::insertRule( [
			'link_id' => $linkId,
			'page_id' => $title->getArticleID(),
			'priority' => $this->calculatePriority( [ 'page' => 1 ] ),
		] );
		if ( !$res ) {
			// Insert failed for some reason.
			return 'kolsherutlinks-details-error-rule-failed-save';
		}

		// Reassign links to pages
		KolsherutLinks::reassignPagesLinks();

		// Redirect to display link details.
		$displayUrl = $output->getTitle()->getLocalURL( [ 'link_id' => $linkId ] );
		$output->redirect( $displayUrl, '303' );
		return true;
	}

	/**
	 * Define category/ies rule form structure
	 * @param int $linkId
	 * @return array
	 */
	private function getAddCategoryForm( $linkId ) {
		$link = KolsherutLinks::getLinkDetails( $linkId );
		if ( empty( $link ) ) {
			// Error: couldn't query the link we're trying to add this rule to.
			throw new MWException( $this->msg( 'kolsherutlinks-details-error-not-found', $linkId ) );
		}
		$form = [
			'kslLinkUrl' => [
				'type' => 'info',
				'label-message' => 'kolsherutlinks-details-label-page-link-url',
				'default' => "<a target=\"_blank\" href=\"{$link['url']}\">{$link['url']}</a>",
				'raw' => true,
			],
			'kslContentAreaName' => [
				'type' => 'text',
				'cssclass' => 'ksl-content-area-name',
				'label-message' => 'kolsherutlinks-details-label-content-area',
			],
			'kslCategory1Name' => [
				'type' => 'text',
				'cssclass' => 'ksl-category-name',
				'label-message' => 'kolsherutlinks-details-label-category',
			],
			'kslCategory2Name' => [
				'type' => 'text',
				'cssclass' => 'ksl-category-name ksl-category-optional',
				'label-message' => 'kolsherutlinks-details-label-category',
			],
			'kslCategory3Name' => [
				'type' => 'text',
				'cssclass' => 'ksl-category-name ksl-category-optional',
				'label-message' => 'kolsherutlinks-details-label-category',
			],
			'kslCategory4Name' => [
				'type' => 'text',
				'cssclass' => 'ksl-category-name ksl-category-optional',
				'label-message' => 'kolsherutlinks-details-label-category',
			],
			'kslFallback' => [
				'type' => 'check',
				'label-message' => 'kolsherutlinks-details-label-fallback',
			],
			'kslLinkId' => [
				'type' => 'hidden',
				'default' => $linkId,
			],
			'kslOp' => [
				'type' => 'hidden',
				'default' => 'add_category',
			],
		];
		return $form;
	}

	/**
	 * Handle add category/ies rule form submission
	 * @param array $postData Form submission data
	 * @return string|bool Return true on success, error message on failure
	 */
	public function handleCategoryRuleSave( $postData ) {
		$output = $this->getOutput();
		$linkId = $postData['kslLinkId'];

		// Require at least one category or content area
		if ( empty( $postData['kslContentAreaName'] ) && empty( $postData['kslCategory1Name'] ) ) {
			// Stop processing and re-prompt for at least one or the other.
			return 'kolsherutlinks-details-error-category-not-entered';
		}

		// Verify content area name
		if ( !empty( $postData['kslContentAreaName'] ) ) {
			$contentArea = \Category::newFromName( $postData[ 'kslContentAreaName' ] );
			if ( empty( $contentArea->getID() ) ) {
				// No. Stop processing and re-prompt for a valid name.
				return [ [ 'kolsherutlinks-details-error-category-not-found', $postData[ 'kslContentAreaName' ] ] ];
			}
		}

		// Collect and verify category names
		$categories = [];
		foreach ( [ 'kslCategory1Name', 'kslCategory2Name', 'kslCategory3Name', 'kslCategory4Name' ]  as $key ) {
			if ( !empty( $postData[ $key ] ) ) {
				$category = \Category::newFromName( $postData[ $key ] );
				// Valid category name?
				if ( empty( $category->getID() ) ) {
					// No. Stop processing and re-prompt for a valid name.
					return [ [ 'kolsherutlinks-details-error-category-not-found', $postData[ $key ] ] ];
				}
				$categories[] = $category->getID();
			}
		}

		// Remove any duplicate categories
		$categories = array_values( array_unique( $categories ) );

		// Insert new rule
		$values = [
			'link_id' => $linkId,
			'fallback' => ( $postData[ 'kslFallback' ] ? 1 : 0 ),
			'priority' => $this->calculatePriority( [
				'category' => count( $categories ),
				'content_area' => !empty( $contentArea ),
			] ),
		];
		if ( !empty( $contentArea ) ) {
			$values['content_area_id'] = $contentArea->getID();
		}
		for ( $i = 0; $i < count( $categories ); $i++ ) {
			$values[ 'category_id_' . ( $i + 1 ) ] = $categories[ $i ];
		}
		$res = KolsherutLinks::insertRule( $values );
		if ( !$res ) {
			// Insert failed for some reason.
			return 'kolsherutlinks-details-error-rule-failed-save';
		}

		// Reassign links to pages
		KolsherutLinks::reassignPagesLinks();

		// Redirect to display link details.
		$displayUrl = $output->getTitle()->getLocalURL( [ 'link_id' => $linkId ] );
		$output->redirect( $displayUrl, '303' );
		return true;
	}

	/**
	 * Process rule deletion. Redirects to link details display.
	 * @param int $linkId
	 * @param int $ruleId
	 */
	private function handleRuleDelete( $linkId, $ruleId ) {
		$res = KolsherutLinks::deleteRule( $ruleId, $linkId );
		KolsherutLinks::reassignPagesLinks();
		$output = $this->getOutput();
		$detailsUrl = $output->getTitle()->getLocalURL( [ 'link_id' => $linkId ] );
		$output->redirect( $detailsUrl, '303' );
	}

	/**
	 * Process rule deletion. Redirects to link details display.
	 * @param int $linkId
	 */
	private function handleLinkDelete( $linkId ) {
		$res = KolsherutLinks::deleteLink( $linkId );
		KolsherutLinks::reassignPagesLinks();
		$output = $this->getOutput();
		$detailsPage = \SpecialPage::getTitleFor( 'KolsherutLinksList' );
		$output->redirect( $detailsPage->getLocalURL(), '303' );
	}

	/**
	 * Calculate a rule's "priority" score based on its attributes.
	 * @param array $attributes
	 * @return int
	 */
	private function calculatePriority( $attributes ) {
		if ( !empty( $attributes['page'] ) ) {
			// Page rules automatically get a score of 999.
			return 999;
		}
		// Category/content area score
		$score = $attributes['content_area'] ? 3 : 0;
		if ( !empty( $attributes['category'] ) ) {
			// Each category gets two points.
			$score += 2 * $attributes['category'];
		}
		return $score;
	}

	/**
	 * Utility to query and return all category IDs and names.
	 * @return array
	 */
	private function getAllCategories() {
		static $categories;
		// Do this expensive thing only once.
		if ( isset( $categories ) ) {
			return $categories;
		}
		$categories = [];
		$res = KolsherutLinks::getAllCategories();
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$catTitle = \Title::makeTitle( NS_CATEGORY, $row['cat_title'] );
			$categories[ $row['cat_id'] ] = $catTitle->getBaseText();
		}
		return $categories;
	}

	/**
	 * Utility to query and return all content area category IDs and names.
	 * Includes only those that are actually in use (i.e., tagged to articles)
	 * not necessarily all that would be considered "valid".
	 * @return array
	 */
	private function getAllContentAreas() {
		static $contentAreas;
		// Do this expensive thing only once.
		if ( isset( $contentAreas ) ) {
			return $contentAreas;
		}
		$contentAreas = [];
		$res = KolsherutLinks::getAllContentAreas();
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$category = \Category::newFromName( $row['pp_value'] );
			$contentAreas[ $category->getID() ] = $category->getTitle()->getBaseText();
		}
		return $contentAreas;
	}
}
