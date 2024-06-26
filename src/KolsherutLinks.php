<?php

namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use ExtensionRegistry;
use ManualLogEntry;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use PurgeJobUtils;
use RequestContext;
use Sanitizer;
use Title;

/**
 * Data handling and logging functions for Kol-Sherut links and rules
 */
class KolsherutLinks {

	/** @var LoggerInterface */
	private static LoggerInterface $logger;

	/**
	 * Utility to maintain static logger
	 * @return LoggerInterface
	 */
	public static function getLogger() {
		if ( empty( self::$logger ) ) {
			self::$logger = LoggerFactory::getInstance( 'KolsherutLinks' );
		}
		return self::$logger;
	}

	/**
	 * Create special log entry
	 * @param string $type
	 * @param \MediaWiki\Linker\LinkTarget $target
	 * @param array $link Details for the affected link.
	 * @param array $params Optional additional parameters to pass to formatter.
	 */
	public static function logEntry( $type, $target, $link, $params = [] ) {
		$user = RequestContext::getMain()->getUser();
		$logEntry = new ManualLogEntry( 'kolsherutlinks', $type );
//		$logEntry->setComment( $text );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $target );
		$logEntry->setParameters( array_merge( $link, $params ) );
		$logid = $logEntry->insert();
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function getAllLinks() {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
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
	}

	/**
	 * @param int $linkId Link ID
	 * @return array|bool
	 */
	public static function getLinkDetails( $linkId ) {
		$dbr = wfGetDB( DB_REPLICA );
		$link = $dbr->select(
			[ 'links' => 'kolsherutlinks_links' ],
			[ 'links.link_id', 'links.url', 'links.text' ],
			[ 'links.link_id' => $linkId ],
			__METHOD__
		)->fetchRow();
		if ( !empty( $link['text'] ) ) {
			$link['text'] = Sanitizer::decodeCharReferences( $link['text'] );
		}
		return $link;
	}

	/**
	 * @param int $pageId Page ID
	 * @return array
	 */
	public static function getLinksByPageId( $pageId ) {
		$links = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[
				'assignments' => 'kolsherutlinks_assignments',
				'links' => 'kolsherutlinks_links',
			],
			[ 'links.link_id', 'links.url', 'links.text' ],
			[ 'assignments.page_id' => $pageId ],
			__METHOD__,
			[],
			[
				'links' => [ 'INNER JOIN', 'links.link_id=assignments.link_id' ],
			],
		);
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$links[ $row['link_id'] ] = $row;
		}
		return $links;
	}

	/**
	 * @param int $linkId Link ID
	 * @return \IResultWrapper
	 */
	public static function getLinkRules( $linkId ) {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[
				'rules' => 'kolsherutlinks_rules',
				'page' => 'page',
			],
			[ 'rules.rule_id', 'rules.page_id', 'page.page_title' ],
			[
				'rules.link_id' => $linkId,
				"rules.page_id IS NOT NULL",
			],
			__METHOD__,
			[ 'ORDER BY' => 'page_title ASC' ],
			[
				'page' => [ 'LEFT JOIN', 'page.page_id=rules.page_id' ],
			],
		);
	}

	/**
	 * @param int $ruleId Link ID
	 * @return array|bool
	 */
	public static function getRule( $ruleId ) {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[ 'rules' => 'kolsherutlinks_rules' ],
			[
				'rules.link_id', 'rules.fallback', 'rules.page_id', 'rules.content_area', 'rules.category_1',
				'rules.category_2', 'rules.category_3', 'rules.category_4', 'rules.priority',
			],
			[ 'rules.rule_id' => $ruleId ],
			__METHOD__,
		)->fetchRow();
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function getAllRules() {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[
				'rules' => 'kolsherutlinks_rules',
				'links' => 'kolsherutlinks_links',
			],
			[
				'rule_id' => 'rules.rule_id',
				'link_id' => 'rules.link_id',
				'fallback' => 'rules.fallback',
				'page_id' => 'rules.page_id',
				'content_area' => 'rules.content_area',
				'category_1' => 'rules.category_1',
				'category_2' => 'rules.category_2',
				'category_3' => 'rules.category_3',
				'category_4' => 'rules.category_4',
				'priority' => 'rules.priority',
				'link_url' => 'links.url',
				'link_text' => 'links.text',
			],
			'',
			__METHOD__,
			[
				'ORDER BY' => 'rules.page_id ASC, rules.priority DESC',
			],
			[
				'links' => [ 'LEFT JOIN', 'links.link_id=rules.link_id' ],
			],
		);
	}

	/**
	 * @param int $linkId Link ID
	 * @return \IResultWrapper
	 */
	public static function getLinkCategoryRules( $linkId ) {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[ 'rules' => 'kolsherutlinks_rules' ],
			[
				'rule_id' => 'rules.rule_id',
				'fallback' => 'rules.fallback',
				'priority' => 'rules.priority',
				'content_area' => 'rules.content_area',
				'category_1' => 'rules.category_1',
				'category_2' => 'rules.category_2',
				'category_3' => 'rules.category_3',
				'category_4' => 'rules.category_4',
			],
			[
				'rules.link_id' => $linkId,
				"rules.content_area IS NOT NULL OR rules.category_1 IS NOT NULL",
			],
			__METHOD__,
			[
				'ORDER BY' => 'priority DESC, fallback ASC, content_area, category_1 ASC'
					. ', category_2 ASC, category_3 ASC, category_4 ASC'
			],
			[],
		);
	}

	/**
	 * @param int $linkId (optional) Link ID
	 * @return \IResultWrapper
	 */
	public static function getPageAssignments( $linkId = false ) {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[
				'assignments' => 'kolsherutlinks_assignments',
				'pages' => 'page',
			],
			[ 'assignments.page_id', 'pages.page_title', 'assignments.link_id' ],
			$linkId ? [ 'assignments.link_id' => $linkId ] : "1=1",
			__METHOD__,
			[ 'ORDER BY' => 'pages.page_title ASC' ],
			[ 'pages' => [ 'INNER JOIN', 'pages.page_id=assignments.page_id' ] ]
		);
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function getAllCategories() {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[ 'category' => 'category' ],
			[ 'category.cat_id', 'category.cat_title' ],
			"category.cat_pages > 0",
			__METHOD__,
			[ 'ORDER BY' => 'cat_title ASC' ],
		);
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function getAllContentAreas() {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			[ 'page_props' => 'page_props' ],
			[ 'page_props.pp_value' ],
			"page_props.pp_propname = 'ArticleContentArea' and pp_value <> ''",
			__METHOD__,
			[ 'GROUP BY' => 'pp_value', 'ORDER BY' => 'pp_value ASC' ],
		);
	}

	/**
	 * Query all possible assignments based on current rules,
	 * optionally limited to a single link's rules.
	 * Single giant query optimized for performance, because
	 * we have to do this after every change to the rules.
	 *
	 * @param int|null $linkId (optional) Limit to a single link, otherwise consider all links.
	 * @return \IResultWrapper
	 */
	public static function getPossibleAssignments( $linkId = false ) {
		$dbr = wfGetDB( DB_REPLICA );
		$contentAreaPropName = ExtensionRegistry::getInstance()->isLoaded( 'ArticleContentArea' ) ?
			'ArticleContentArea' : \MediaWiki\Extension\ArticleContentArea\ArticleContentArea::$DATA_VAR;
		$whereLinkPageRules = ( $linkId === false ) ? '' : "AND page_rules.link_id={$linkId}";
		$whereLinkCategoryRules = ( $linkId === false ) ? '' : "AND cat_rules.link_id={$linkId}";
		return $dbr->query(
			"SELECT page_rules.page_id, page_rules.rule_id, page_rules.link_id, page_rules.fallback,
						page_rules.priority, page_links.url
					FROM kolsherutlinks_rules AS page_rules
					INNER JOIN kolsherutlinks_links AS page_links ON page_links.link_id=page_rules.link_id
					WHERE page_rules.page_id IS NOT NULL {$whereLinkPageRules}
					GROUP BY page_rules.page_id
				UNION
				SELECT IFNULL(cl1.cl_from, pp.pp_page) AS page_id, cat_rules.rule_id, cat_rules.link_id, 
						cat_rules.fallback, cat_rules.priority, cat_links.url
					FROM kolsherutlinks_rules AS cat_rules
					INNER JOIN kolsherutlinks_links AS cat_links ON cat_links.link_id=cat_rules.link_id
					LEFT JOIN page_props AS pp ON (
						cat_rules.content_area IS NOT NULL
							AND pp.pp_propname='{$contentAreaPropName}'
							AND REPLACE(pp.pp_value, ' ', '_')=cat_rules.content_area
					)
					LEFT JOIN categorylinks AS cl1 ON (cl1.cl_to=cat_rules.category_1)
					LEFT JOIN categorylinks AS cl2 ON (cl2.cl_to=cat_rules.category_2)
					LEFT JOIN categorylinks AS cl3 ON (cl3.cl_to=cat_rules.category_3)
					LEFT JOIN categorylinks AS cl4 ON (cl4.cl_to=cat_rules.category_4)
					WHERE (cat_rules.content_area IS NOT NULL OR cat_rules.category_1 IS NOT NULL)
						AND (cat_rules.content_area IS NULL OR pp.pp_page IS NOT NULL)
						AND (cat_rules.category_1 IS NULL OR cl1.cl_from IS NOT NULL)
						AND (
							cat_rules.content_area IS NULL OR cat_rules.category_1 IS NULL OR
							pp.pp_page=cl1.cl_from
						)
						AND (cat_rules.category_2 IS NULL OR cl2.cl_from=cl1.cl_from)
						AND (cat_rules.category_3 IS NULL OR cl3.cl_from=cl1.cl_from)
						AND (cat_rules.category_4 IS NULL OR cl4.cl_from=cl1.cl_from)
						{$whereLinkCategoryRules}
				ORDER BY page_id ASC, fallback ASC, priority DESC;"
		);
	}

	/**
	 * @param array $data
	 * @return int|false Link ID
	 */
	public static function saveLinkDetails( $data ) {
		$dbw = wfGetDB( DB_PRIMARY );
		if ( !empty( $data['link_id'] ) ) {
			// Sanitize input.
			$data['url'] = Sanitizer::cleanUrl( $data['url'] );
			$data['text'] = Sanitizer::escapeHtmlAllowEntities( $data['text'] );
			// Update existing link.
			$linkId = intval( $data['link_id'] );
			$res = $dbw->update(
				'kolsherutlinks_links',
				[
					'url' => $data['url'],
					'text' => $data['text'],
				],
				[ 'link_id' => $linkId ],
				__METHOD__
			);
			if ( !$res ) {
				// Update failure.
				return false;
			}
		} else {
			// Insert new link.
			$res = $dbw->insert(
				'kolsherutlinks_links',
				$data,
				__METHOD__
			);
			if ( !$res ) {
				// Insert failure.
				return false;
			}
			// Get the new link's ID.
			$row = $dbw->select(
				[ 'links' => 'kolsherutlinks_links' ],
				[ 'links.link_id' ],
				[ 'links.url' => $data['url'] ],
				__METHOD__,
				[
					'ORDER BY' => 'link_id DESC',
					'LIMIT' => '1',
				],
				)->fetchRow();
			$linkId = $row['link_id'];
		}
		return $linkId;
	}

	/**
	 * @param array $data
	 * @return \IResultWrapper
	 */
	public static function insertRule( $data ) {
		$dbw = wfGetDB( DB_PRIMARY );
		// Sanitize data.
		foreach ( [ 'link_id', 'fallback', 'page_id', 'priority' ] as $intField ) {
			if ( !empty( $data[$intField] ) ) {
				$data[$intField] = intval( $data[$intField] );
			}
		}
		foreach ( [ 'content_area', 'category_1', 'category_2', 'category_3', 'category_4' ] as $catField ) {
			if ( !empty( $data[$catField] ) ) {
				$category = Category::newFromName( $data[$catField] );
				$data[$catField] = !empty( $category ) ? $category->getName() : '';
			}
		}
		// Insert data.
		return $dbw->insert(
			'kolsherutlinks_rules',
			$data,
			__METHOD__
		);
	}

	/**
	 * @param int $ruleId Rule ID
	 * @param int $linkId Link ID
	 * @return \IResultWrapper
	 */
	public static function deleteRule( $ruleId, $linkId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->delete(
			'kolsherutlinks_rules',
			[
				'link_id' => $linkId,
				'rule_id' => $ruleId,
			]
		);
	}

	/**
	 * @param int $linkId Link ID
	 * @return \IResultWrapper
	 */
	public static function deleteLink( $linkId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete(
			'kolsherutlinks_rules',
			[ 'link_id' => $linkId ]
		);
		return $dbw->delete(
			'kolsherutlinks_links',
			[ 'link_id' => $linkId ]
		);
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function reassignPagesLinks() {
		$dbw = wfGetDB( DB_PRIMARY );
		// Gather all page IDs currently with links assigned. We will clear their file caches at the end.
		$pageIdsAffacted = [];
		$res = $dbw->select(
			[ 'assignments' => 'kolsherutlinks_assignments' ],
			[ 'assignments.page_id' ],
			'',
			__METHOD__,
		);
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			$pageIdsAffacted[ $row['page_id'] ] = $row['page_id'];
		}
		// Clear the assignments table. We'll rebuild it.
		$dbw->delete( 'kolsherutlinks_assignments', [ '1=1' ] );
		// Query all pages matching current rules.
		$res = self::getPossibleAssignments();
		// Iterate over matching rules to select page link assignments.
		$isArticleTypeExtensionLoaded = ExtensionRegistry::getInstance()->isLoaded( 'ArticleType' );
		$excludedArticleTypes = self::getExcludedArticleTypes();
		$assignments = [];
		$pageId = 0;
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			if ( $row['page_id'] != $pageId ) {
				// Starting now on rules for a new page.
				$pageId = $row['page_id'];
				$linkUrlsAssigned = [];
				$fallbackAssigned = false;
			}
			// Maximum of two assignments per page, or only one if it's a fallback.
			if ( count( $linkUrlsAssigned ) === ( $fallbackAssigned ? 1 : 2 ) ) {
				continue;
			}
			// Don't assign a fallback if a non-fallback rule matched.
			if ( $row['fallback'] && !empty( $linkUrlsAssigned ) ) {
				continue;
			}
			// Don't assign the same link twice to the same page.
			if ( in_array( $row['url'], $linkUrlsAssigned ) ) {
				continue;
			}
			// Don't assign any links to an excluded ArticleType
			if ( $isArticleTypeExtensionLoaded ) {
				$articleType = \MediaWiki\Extension\ArticleType\ArticleType::getArticleType(
					Title::newFromID( $row['page_id'] )
				);
				if ( !empty( $articleType ) && in_array( $articleType, $excludedArticleTypes ) ) {
					continue;
				}
			}
			// Assign this rule's link to the page.
			$assignments[] = [
				'page_id' => $pageId,
				'link_id' => $row['link_id'],
			];
			$linkUrlsAssigned[] = $row['url'];
			$fallbackAssigned = $row['fallback'];
			if ( !isset( $pageIdsAffacted[$pageId] ) ) {
				$pageIdsAffacted[ $pageId ] = $pageId;
			}
		}
		// Insert the assignments.
		$res = $dbw->insert( 'kolsherutlinks_assignments', $assignments, __METHOD__ );
		// Clear cached versions of all impacted pages.
		if ( !empty( $pageIdsAffacted ) ) {
			$pageTitles = $dbw->selectFieldValues(
				'page',
				'page_title',
				[ 'page_id' => array_values( $pageIdsAffacted ) ],
				__METHOD__,
			);
			PurgeJobUtils::invalidatePages( $dbw, NS_MAIN, $pageTitles );
		}
		return $res;
	}

	/**
	 * @return array $excludedArticleTypes
	 */
	public static function getExcludedArticleTypes() {
		static $excludedArticleTypes;
		if ( !isset( $excludedArticleTypes ) ) {
			$config = MediaWikiServices::getInstance()->getMainConfig()->get( 'KolsherutLinksExcludedArticleTypes' );
			$excludedArticleTypes = !empty( $config ) ? $config : [];
		}
		return $excludedArticleTypes;
	}

	/**
	 * Wrapper around functionality in the ArticleContentArea extension.
	 * This version is more strict and will only accept a valid category name,
	 * even if no $wgArticleContentAreaCategoryName is set.
	 *
	 * @param string|array $contentAreaName
	 * @return bool
	 */
	public static function isValidContentArea( $contentAreaName ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ArticleContentArea' ) ) {
			$configuredValidContentAreas =
				\MediaWiki\Extension\ArticleContentArea\ArticleContentArea::getValidContentAreas();
			if ( !empty( $configuredValidContentAreas ) ) {
				// Rely on existing configuration of valid content areas.
				return \MediaWiki\Extension\ArticleContentArea\ArticleContentArea::isValidContentArea(
					$contentAreaName
				);
			}
		}
		// Fall back to simply requiring a valid category name.
		$contentArea = Category::newFromName( $contentAreaName );
		return ( !empty( $contentArea ) && !empty( $contentArea->getID() ) );
	}
}
