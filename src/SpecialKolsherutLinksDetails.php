<?php

namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use ExtensionRegistry;
use HTMLForm;
use MWException;
use SpecialPage;
use TemplateParser;
use Title;
use WikiPage;

/**
 * Details view and create/edit form for a Kol Sherut link and its display rules.
 *
 * @ingroup SpecialPage
 */
class SpecialKolsherutLinksDetails extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'KolsherutLinksDetails', 'manage-kolsherut-links' );
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

		// Treat numeric subpage parameter as link_id.
		if ( !empty( $par ) && is_numeric( $par ) ) {
			$queryParams['link_id'] = $par;
		}

		// Process and build page by operation.
		$op = !empty( $postValues['wpkslOp'] ) ? $postValues['wpkslOp'] :
			( !empty( $queryParams['op'] ) ? $queryParams['op'] : 'display' );
		$linkId = !empty( $postValues['wpkslLinkId'] ) ? $postValues['wpkslLinkId'] :
			( !empty( $queryParams['link_id'] ) ? $queryParams['link_id'] : null );
		switch ( $op ) {
			case 'display':
				if ( empty( $linkId ) ) {
					// Can't display link without an ID. Redirect to list view.
					$listPage = SpecialPage::getTitleFor( 'KolsherutLinksList' );
					$output->redirect( $listPage->getLocalURL(), '303' );
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
				$htmlForm = HTMLForm::factory( 'ooui', $this->getLinkEditForm( $op, $linkId ), $this->getContext() );
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
				$htmlForm = HTMLForm::factory( 'ooui', $this->getAddPageForm( $linkId ), $this->getContext() );
				$htmlForm->setId( 'kolsherutLinksAddPageForm' )
					->setFormIdentifier( 'kolsherutLinksAddPageForm' )
					->setSubmitName( "ksl-submit" )
					->setSubmitTextMsg( 'kolsherutlinks-details-rule-submit' )
					->setSubmitCallback( [ $this, 'handlePageRuleSave' ] )
					->show();
				break;
			case 'add_category':
				$output->setPageTitle( $this->msg( 'kolsherutlinks-details-title-add-category' ) );
				$htmlForm = HTMLForm::factory( 'ooui', $this->getAddCategoryForm( $linkId ), $this->getContext() );
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

		// Prepare page body template.
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		$templateData = [];

		// Basic link details
		$link = KolsherutLinks::getLinkDetails( $linkId );
		$templateData['link'] = [
			'urlHeader' => $this->msg( 'kolsherutlinks-details-label-url' )->text(),
			'url' => $link['url'],
			'textHeader' => $this->msg( 'kolsherutlinks-details-label-text' )->text(),
			'text' => $link['text'],
		];

		// Link details edit button.
		$templateData['editLink'] = [
			'url' => $output->getTitle()->getLocalURL( [ 'op' => 'edit', 'link_id' => $linkId ] ),
			'label' => $this->msg( 'kolsherutlinks-details-op-edit' )->text(),
		];

		// Page rules
		$templateData['pageRulesHeader'] = $this->msg( 'kolsherutlinks-details-label-page-rules' )->text();
		$templateData['pageRules'] = [
			'header' => [
				'page' => $this->msg( 'kolsherutlinks-details-rule-header-page' )->text(),
			],
			'rows' => [],
		];
		$deleteMsg = $this->msg( 'kolsherutlinks-list-op-delete' )->text();
		$res = KolsherutLinks::getLinkRules( $linkId );
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$page = Title::newFromID( $row['page_id'] );
			$templateData['pageRules']['rows'][] = [
				// Page title with link (in new tab/window)
				'pageUrl' => $page->getLocalURL(),
				'pageTitle' => $page->getTitleValue()->getText(),
				// Link to delete this rule
				'deleteUrl' => $output->getTitle()->getLocalURL( [
					'op' => 'delete_rule',
					'rule_id' => $row['rule_id'],
					'link_id' => $linkId,
				] ),
				'deleteMsg' => $deleteMsg,
			];
		}

		// Add page rule button
		$templateData['addPageRule'] = [
			'url' => $output->getTitle()->getLocalURL( [ 'op' => 'add_page', 'link_id' => $linkId ] ),
			'label' => $this->msg( 'kolsherutlinks-details-op-add-page' )->text(),
		];

		// Category rules
		$templateData['categoryRulesHeader'] = $this->msg( 'kolsherutlinks-details-label-category-rules' )->text();
		$templateData['categoryRules'] = [
			'header' => [
				'contentArea' => $this->msg( 'kolsherutlinks-details-rule-header-content-area' )->text(),
				'categories' => $this->msg( 'kolsherutlinks-details-rule-header-categories' )->text(),
				'fallback' => $this->msg( 'kolsherutlinks-details-rule-header-fallback' )->text(),
				'priority' => $this->msg( 'kolsherutlinks-details-rule-header-priority' )->text(),
			],
			'rows' => [],
		];
		$output->allowClickjacking();
		$output->addModules( [ 'ext.KolsherutLinks.list', 'ext.KolsherutLinks.confirmation' ] );
		$res = KolsherutLinks::getAllLinks();
		$deleteMsg = $this->msg( 'kolsherutlinks-list-op-delete' )->text();
		$res = KolsherutLinks::getLinkCategoryRules( $linkId );
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			// Content area name with link (in new tab/window)
			if ( !empty( $row['content_area'] ) ) {
				$category = Category::newFromName( $row['content_area'] );
				if ( !empty( $category ) ) {
					$contentArea = $category->getTitle()->getBaseText();
					$contentAreaUrl = $category->getTitle()->getLocalURL();
				} else {
					$contentArea = $row['content_area'];
					$contentAreaUrl = false;
				}
			} else {
				$contentArea = '-';
				$contentAreaUrl = false;
			}
			// Category name(s) with link(s) (in new tab/window)
			if ( !empty( $row['category_1'] ) ) {
				$links = [];
				foreach ( array_filter( [
					$row['category_1'], $row['category_2'], $row['category_3'], $row['category_4']
				] ) as $categoryName ) {
					$category = Category::newFromName( $categoryName );
					if ( !empty( $category ) ) {
						$links[] = '<a target="_blank" href="' . $category->getTitle()->getLocalURL() . '">'
						. $category->getTitle()->getBaseText() . '</a>';
					} else {
						$links[] = $categoryName;
					}
				}
				$categories = implode( ' + ', $links ) . '</td>';
			} else {
				$categories = '-';
			}
			$templateData['categoryRules']['rows'][] = [
				'contentArea' => $contentArea,
				'contentAreaUrl' => $contentAreaUrl,
				'categories' => $categories,
				// Is fallback?
				'isFallback' => $row['fallback'] ? 'X' : '',
				// Priority score
				'priority' => $row['priority'],
				// Link to delete this rule
				'deleteMsg' => $deleteMsg,
				'deleteUrl' => $output->getTitle()->getLocalURL( [
					'op' => 'delete_rule',
					'rule_id' => $row['rule_id'],
					'link_id' => $linkId,
				] ),
			];
		}

		// Add category/ies rule button
		$templateData['addCategoryRule'] = [
			'url' => $output->getTitle()->getLocalURL( [ 'op' => 'add_category', 'link_id' => $linkId ] ),
			'label' => $this->msg( 'kolsherutlinks-details-op-add-category' )->text(),
		];

		// Link assignments
		$templateData['assignmentsHeader'] = $this->msg( 'kolsherutlinks-details-title-assignments' )->text();
		$templateData['assignments']['rows'] = [];
		$res = KolsherutLinks::getPageAssignments( $linkId );
		$assignedPageIds = [];
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$title = WikiPage::newFromID( $row['page_id'] )->getTitle();
			$assignedPageIds[ $row['page_id'] ] = $row['page_id'];
			$templateData['assignments']['rows'][] = [
				'url' => $title->getLocalURL(),
				'title' => $title->getBaseText(),
			];
		}
		if ( empty( $templateData['assignments']['rows'] ) ) {
			$templateData['assignments']['emptyMsg'] = $this->msg( 'kolsherutlinks-details-assignments-empty' )->text();
		}

		// Link non-assignments
		$templateData['nonAssignmentsHeader'] = $this->msg( 'kolsherutlinks-details-title-nonassignments' )->text();
		$templateData['nonAssignments']['rows'] = [];
		$res = KolsherutLinks::getPossibleAssignments( $linkId );
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			if ( !empty( $assignedPageIds[ $row['page_id'] ] ) ) {
				continue;
			}
			$title = WikiPage::newFromID( $row['page_id'] )->getTitle();
			$templateData['nonAssignments']['rows'][] = [
				'url' => $title->getLocalURL(),
				'title' => $title->getBaseText(),
			];
		}

		// Delete link button
		$templateData['deleteLinkHeader'] = $this->msg( 'kolsherutlinks-details-title-delete' )->text();
		$templateData['deleteLink'] = [
			'url' => $output->getTitle()->getLocalURL( [ 'op' => 'delete', 'link_id' => $linkId ] ),
			'label' => $this->msg( 'kolsherutlinks-details-op-link-delete' )->text(),
		];

		// Log link
		$templateData['logHeader'] = $this->msg( 'kolsherutlinks-details-title-log' )->text();
		$templateData['logLink'] = [
			'url' => SpecialPage::getTitleFor( 'Log' )->getLocalURL( [
				'type' => 'kolsherutlinks',
				'page' => SpecialPage::getTitleFor( 'KolsherutLinksDetails' )->getBaseTitle() . '/' . $linkId
			] ),
			'text' => $this->msg( 'kolsherutlinks-details-log-link' )->text(),
		];

		// Provide link back to list page.
		$listPage = SpecialPage::getTitleFor( 'KolsherutLinksList' );
		$templateData['listLink'] = [
			'url' => $listPage->getLocalURL(),
			'text' => $this->msg( 'kolsherutlinks-details-back-to-list' )->text(),
		];

		// Process template and output.
		$output->addHTML(
			$templateParser->processTemplate( 'kolsherut-links-details', $templateData )
		);
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

		// Logging.
		$details = KolsherutLinks::getLinkDetails( $linkId );
		$logAction = !empty( $postData['kslLinkId'] ) ? 'linkEdit' : 'linkCreate';
		$target = SpecialPage::getTitleFor( 'KolsherutLinksDetails', $linkId );
		if ( $logAction == 'linkCreate' ) {
			// If one day we want to log edits, this `if` can be removed, or an `else` can be added.
			KolsherutLinks::logEntry( $logAction, $target, $details );
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
		$title = Title::newFromText( $postData['kslPageTitle'] );
		if ( !$title->exists() ) {
			// Title not found.
			return 'kolsherutlinks-details-error-page-not-found';
		}

		// Don't allow page rule on excluded ArticleType
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ArticleType' ) ) {
			$articleType = \MediaWiki\Extension\ArticleType\ArticleType::getArticleType( $title );
			if ( !empty( $articleType ) && in_array( $articleType, KolsherutLinks::getExcludedArticleTypes() ) ) {
				return [ [ 'kolsherutlinks-details-error-excluded-article-type', $articleType ] ];
			}
		}

		// Insert new rule
		$rule = [
			'link_id' => $linkId,
			'page_id' => $title->getArticleID(),
			'priority' => $this->calculatePriority( [ 'page' => 1 ] ),
		];
		$res = KolsherutLinks::insertRule( $rule );
		if ( !$res ) {
			// Insert failed for some reason.
			return 'kolsherutlinks-details-error-rule-failed-save';
		}

		// Logging.
		$details = KolsherutLinks::getLinkDetails( $linkId );
		$target = SpecialPage::getTitleFor( 'KolsherutLinksDetails', $linkId );
		KolsherutLinks::logEntry( 'pageRule', $target, $details, $rule );

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
			if ( !KolsherutLinks::isValidContentArea( $postData[ 'kslContentAreaName' ] ) ) {
				// No. Stop processing and re-prompt for a valid name.
				return [ [ 'kolsherutlinks-details-error-invalid-content-area', $postData[ 'kslContentAreaName' ] ] ];
			}
		}

		// Collect and verify category names
		$categories = [];
		foreach ( [ 'kslCategory1Name', 'kslCategory2Name', 'kslCategory3Name', 'kslCategory4Name' ]  as $key ) {
			if ( !empty( $postData[ $key ] ) ) {
				$category = Category::newFromName( $postData[ $key ] );
				// Valid category name?
				if ( !$category || empty( $category->getID() ) ) {
					// No. Stop processing and re-prompt for a valid name.
					return [ [ 'kolsherutlinks-details-error-category-not-found', $postData[ $key ] ] ];
				}
				$categories[] = $category->getName();
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
				'content_area' => !empty( $postData['kslContentAreaName'] ),
			] ),
		];
		if ( !empty( $postData['kslContentAreaName'] ) ) {
			$values['content_area'] = $postData['kslContentAreaName'];
		}
		for ( $i = 0; $i < count( $categories ); $i++ ) {
			$values[ 'category_' . ( $i + 1 ) ] = $categories[ $i ];
		}
		$res = KolsherutLinks::insertRule( $values );
		if ( !$res ) {
			// Insert failed for some reason.
			return 'kolsherutlinks-details-error-rule-failed-save';
		}

		// Logging.
		$details = KolsherutLinks::getLinkDetails( $linkId );
		$target = SpecialPage::getTitleFor( 'KolsherutLinksDetails', $linkId );
		KolsherutLinks::logEntry( 'categoryRule', $target, $details, $values );

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
		$rule = KolsherutLinks::getRule( $ruleId );
		$res = KolsherutLinks::deleteRule( $ruleId, $linkId );
		KolsherutLinks::reassignPagesLinks();

		// Logging.
		$details = KolsherutLinks::getLinkDetails( $linkId );
		$logAction = !empty( $rule['page_id'] ) ? 'pageRuleDelete' : 'categoryRuleDelete';
		$target = SpecialPage::getTitleFor( 'KolsherutLinksDetails', $linkId );
		KolsherutLinks::logEntry( $logAction, $target, $details, $rule );

		// Redirect to link details.
		$output = $this->getOutput();
		$detailsUrl = $output->getTitle()->getLocalURL( [ 'link_id' => $linkId ] );
		$output->redirect( $detailsUrl, '303' );
	}

	/**
	 * Process rule deletion. Redirects to link details display.
	 * @param int $linkId
	 */
	private function handleLinkDelete( $linkId ) {
		$link = KolsherutLinks::getLinkDetails( $linkId );
		$res = KolsherutLinks::deleteLink( $linkId );
		KolsherutLinks::reassignPagesLinks();

		// Logging.
		$output = $this->getOutput();
		$target = SpecialPage::getTitleFor( 'KolsherutLinksDetails', $linkId );
		KolsherutLinks::logEntry( 'linkDelete', $target, $link );

		// Redirect to links list.
		$listPage = SpecialPage::getTitleFor( 'KolsherutLinksList' );
		$output->redirect( $listPage->getLocalURL(), '303' );
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
			$category = Category::newFromName( $row['cat_title'] );
			$categories[ $category->getName() ] = $category->getTitle()->getBaseText();
		}
		return $categories;
	}

	/**
	 * Utility to query and return all content area category IDs and names.
	 * Includes only those that are actually in use (i.e., tagged to articles)
	 * not necessarily all that would be considered "valid".
	 *
	 * Differs from ArticleContentArea::getValidContentAreas() in two ways:
	 * 1) Returns values even if $wgArticleContentAreaCategoryName is null
	 * 2) Returns only the values in active use as content areas.
	 *
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
			$category = Category::newFromName( $row['pp_value'] );
			$contentAreas[ $category->getName() ] = $category->getTitle()->getBaseText();
		}
		return $contentAreas;
	}
}
