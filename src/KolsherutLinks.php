<?php

namespace MediaWiki\Extension\KolsherutLinks;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;

/**
 * Data handling functions for Kol-Sherut links and rules
 *
 * @ingroup SpecialPage
 */
class KolsherutLinks {

	/**
	 * @param int $linkId Link ID
	 * @return array|bool
	 */
	public static function getLinkDetails( $linkId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[ 'links' => 'kolsherutlinks_links' ],
			[ 'links.link_id', 'links.url', 'links.text' ],
			"links.link_id={$linkId}",
			__METHOD__
		)->fetchRow();
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
				"rules.link_id={$linkId}",
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
				"rules.link_id={$linkId}",
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
	 * @param int $linkId Link ID
	 * @return \IResultWrapper
	 */
	public static function getPageAssignmentsByLink( $linkId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		return $dbw->select(
			[
				'assignments' => 'kolsherutlinks_assignments',
				'pages' => 'page',
			],
			[ 'assignments.page_id', 'pages.page_title' ],
			"assignments.link_id={$linkId}",
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
	 * @param array $data
	 * @return int|false Link ID
	 */
	public static function saveLinkDetails( $data ) {
		$dbw = wfGetDB( DB_PRIMARY );
		//@TODO: sanitize url and text
		if ( !empty( $data['link_id'] ) ) {
			// Update existing link.
			$linkId = intval( $data['link_id'] );
			$res = $dbw->update(
				'kolsherutlinks_links',
				[
					'url' => $data['url'],
					'text' => $data['text'],
				],
				"link_id={$linkId}",
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
				"links.url='{$data['url']}'",
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
		//@TODO: sanitize data
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
			[ "link_id={$linkId}", "rule_id={$ruleId}" ]
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
			[ "link_id={$linkId}" ]
		);
		return $dbw->delete(
			'kolsherutlinks_links',
			[ "link_id={$linkId}" ]
		);
	}

	/**
	 * @return \IResultWrapper
	 */
	public static function reassignPagesLinks() {
		$dbw = wfGetDB( DB_PRIMARY );
		// Clear the assignments table. We'll rebuild it.
		$dbw->delete( 'kolsherutlinks_assignments', [ '1=1' ] );
		// Query all pages matching current rules.
		$res = $dbw->query(
			"SELECT page_rules.page_id, page_rules.rule_id, page_rules.link_id, page_rules.fallback, page_rules.priority
					FROM kolsherutlinks_rules AS page_rules
					WHERE page_rules.page_id IS NOT NULL
					GROUP BY page_rules.page_id
				UNION
				SELECT IFNULL(cl1.cl_from, pp.pp_page) AS page_id, cat_rules.rule_id, cat_rules.link_id, 
						cat_rules.fallback, cat_rules.priority
					FROM kolsherutlinks_rules AS cat_rules
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
				ORDER BY page_id ASC, fallback ASC, priority DESC;"
		);
		// Iterate over matching rules to select page link assignments.
		$assignments = [];
		$page_id = 0;
		for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
			if ( $row['page_id'] != $page_id ) {
				// Starting now on rules for a new page.
				$page_id = $row['page_id'];
				$link_ids_assigned = [];
				$fallback_assigned = false;
			}
			// Maximum of two assignments per page, or only one if it's a fallback.
			if ( count( $link_ids_assigned ) === ( $fallback_assigned ? 1 : 2 ) ) {
				continue;
			}
			// Don't assign a fallback if a non-fallback rule matched.
			if ( $row['fallback'] && !empty( $link_ids_assigned ) ) {
				continue;
			}
			// Don't assign the same link twice to the same page.
			if ( in_array( $row['link_id'], $link_ids_assigned ) ) {
				continue;
			}
			// Assign this rule's link to the page.
			$assignments[] = [
				'page_id' => $page_id,
				'link_id' => $row['link_id'],
			];
			$link_ids_assigned[] = $row['link_id'];
			$fallback_assigned = $row['fallback'];
		}
		// Insert the assignments.
		return $dbw->insert( 'kolsherutlinks_assignments', $assignments, __METHOD__ );
	}
}
