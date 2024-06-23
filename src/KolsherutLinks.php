<?php

namespace MediaWiki\Extension\KolsherutLinks;

use ExtensionRegistry;
use ManualLogEntry;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use PurgeJobUtils;
use RequestContext;
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
	 * @param int $linkId Link ID
	 * @return array|bool
	 */
	public static function getLinkDetails( $linkId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[ 'links' => 'kolsherutlinks_links' ],
			[ 'links.link_id', 'links.url', 'links.text' ],
			[ 'links.link_id' => $linkId ],
			__METHOD__
		)->fetchRow();
	}

	/**
	 * @param int $pageId Page ID
	 * @return array
	 */
	public static function getLinksByPageId( $pageId ) {
		$links = [];
		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->select(
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
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
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
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[ 'rules' => 'kolsherutlinks_rules' ],
			[
				'rules.link_id', 'rules.fallback', 'rules.page_id', 'rules.content_area_id', 'rules.category_id_1',
				'rules.category_id_2', 'rules.category_id_3', 'rules.category_id_4', 'rules.priority',
			],
			[ 'rules.rule_id' => $ruleId ],
			__METHOD__,
		)->fetchRow();
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function getAllRules() {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[
				'rules' => 'kolsherutlinks_rules',
				'links' => 'kolsherutlinks_links',
				'content_area' => 'category',
				'category1' => 'category',
				'category2' => 'category',
				'category3' => 'category',
				'category4' => 'category',
			],
			[
				'rule_id' => 'rules.rule_id',
				'link_id' => 'rules.link_id',
				'fallback' => 'rules.fallback',
				'page_id' => 'rules.page_id',
				'content_area_id' => 'rules.content_area_id',
				'category_id_1' => 'rules.category_id_1',
				'category_id_2' => 'rules.category_id_2',
				'category_id_3' => 'rules.category_id_3',
				'category_id_4' => 'rules.category_id_4',
				'priority' => 'rules.priority',
				'link_url' => 'links.url',
				'link_text' => 'links.text',
				'content_area_title' => 'content_area.cat_title',
				'cat1_title' => 'category1.cat_title',
				'cat2_title' => 'category2.cat_title',
				'cat3_title' => 'category3.cat_title',
				'cat4_title' => 'category4.cat_title',
			],
			'',
			__METHOD__,
			[
				'ORDER BY' => 'rules.page_id ASC, rules.priority DESC',
			],
			[
				'links' => [ 'LEFT JOIN', 'links.link_id=rules.link_id' ],
				'content_area' => [ 'LEFT JOIN', 'content_area.cat_id=rules.content_area_id' ],
				'category1' => [ 'LEFT JOIN', 'category1.cat_id=rules.category_id_1' ],
				'category2' => [ 'LEFT JOIN', 'category2.cat_id=rules.category_id_2' ],
				'category3' => [ 'LEFT JOIN', 'category3.cat_id=rules.category_id_3' ],
				'category4' => [ 'LEFT JOIN', 'category4.cat_id=rules.category_id_4' ],
			],
		);
	}

	/**
	 * @param int $linkId Link ID
	 * @return \IResultWrapper
	 */
	public static function getLinkCategoryRules( $linkId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[
				'rules' => 'kolsherutlinks_rules',
				'content_area' => 'category',
				'category1' => 'category',
				'category2' => 'category',
				'category3' => 'category',
				'category4' => 'category',
			],
			[
				'rule_id' => 'rules.rule_id',
				'fallback' => 'rules.fallback',
				'priority' => 'rules.priority',
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
			[
				'rules.link_id' => $linkId,
				"rules.content_area_id IS NOT NULL OR rules.category_id_1 IS NOT NULL",
			],
			__METHOD__,
			[
				'ORDER BY' => 'priority DESC, fallback ASC, content_area.cat_title, cat1_title ASC'
					. ', cat2_title ASC, cat3_title ASC, cat4_title ASC'
			],
			[
				'content_area' => [ 'LEFT JOIN', 'content_area.cat_id=rules.content_area_id' ],
				'category1' => [ 'LEFT JOIN', 'category1.cat_id=rules.category_id_1' ],
				'category2' => [ 'LEFT JOIN', 'category2.cat_id=rules.category_id_2' ],
				'category3' => [ 'LEFT JOIN', 'category3.cat_id=rules.category_id_3' ],
				'category4' => [ 'LEFT JOIN', 'category4.cat_id=rules.category_id_4' ],
			],
		);
	}

	/**
	 * @param int $linkId (optional) Link ID
	 * @return \IResultWrapper
	 */
	public static function getPageAssignments( $linkId = false ) {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
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
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
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
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[ 'page_props' => 'page_props' ],
			[ 'page_props.pp_value' ],
			"page_props.pp_propname = 'ArticleContentArea' and pp_value <> ''",
			__METHOD__,
			[ 'GROUP BY' => 'pp_value', 'ORDER BY' => 'pp_value ASC' ],
		);
	}

	/**
	 * @param int|null $linkId (optional) Limit to a single link, otherwise consider all links.
	 * @return \IResultWrapper
	 */
	public static function getPossibleAssignments( $linkId = false ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$whereLinkPageRules = ( $linkId === false ) ? '' : "AND page_rules.link_id={$linkId}";
		$whereLinkCategoryRules = ( $linkId === false ) ? '' : "AND cat_rules.link_id={$linkId}";
		return $dbw->query(
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
					LEFT JOIN category AS ca ON ca.cat_id=cat_rules.content_area_id
					LEFT JOIN category AS cat1 ON cat1.cat_id=cat_rules.category_id_1
					LEFT JOIN category AS cat2 ON cat2.cat_id=cat_rules.category_id_2
					LEFT JOIN category AS cat3 ON cat3.cat_id=cat_rules.category_id_3
					LEFT JOIN category AS cat4 ON cat4.cat_id=cat_rules.category_id_4
					LEFT JOIN page_props AS pp ON (
						ca.cat_id IS NOT NULL AND pp.pp_propname='ArticleContentArea' AND 
						REPLACE(pp.pp_value, ' ', '_')=ca.cat_title
					)
					LEFT JOIN categorylinks AS cl1 ON (cat1.cat_id IS NOT NULL AND cl1.cl_to=cat1.cat_title)
					LEFT JOIN categorylinks AS cl2 ON (cat2.cat_id IS NOT NULL AND cl2.cl_to=cat2.cat_title)
					LEFT JOIN categorylinks AS cl3 ON (cat3.cat_id IS NOT NULL AND cl3.cl_to=cat3.cat_title)
					LEFT JOIN categorylinks AS cl4 ON (cat4.cat_id IS NOT NULL AND cl4.cl_to=cat4.cat_title)
					WHERE (cat_rules.content_area_id IS NOT NULL OR cat_rules.category_id_1 IS NOT NULL)
						AND (cat_rules.content_area_id IS NULL OR pp.pp_page IS NOT NULL)
						AND (cat_rules.category_id_1 IS NULL OR cl1.cl_from IS NOT NULL)
						AND (
							cat_rules.content_area_id IS NULL OR cat_rules.category_id_1 IS NULL OR
							pp.pp_page=cl1.cl_from
						)
						AND (cat_rules.category_id_2 IS NULL OR cl2.cl_from=cl1.cl_from)
						AND (cat_rules.category_id_3 IS NULL OR cl3.cl_from=cl1.cl_from)
						AND (cat_rules.category_id_4 IS NULL OR cl4.cl_from=cl1.cl_from)
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
		// @TODO: sanitize url and text
		if ( !empty( $data['link_id'] ) ) {
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
		// @TODO: sanitize data
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
}
