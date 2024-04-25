<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\KolsherutLinks;

class Hooks implements
	\MediaWiki\Hook\ParserBeforeInternalParseHook,
	\MediaWiki\Hook\ParserFirstCallInitHook,
	\MediaWiki\Permissions\Hook\UserGetAllRightsHook,
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser Parser object being initialised
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onParserFirstCallInit( $parser ): bool {
		return KolsherutLinksPlacement::onParserFirstCallInit( $parser );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserBeforeInternalParse
	 * @param \Parser $parser
	 * @param string &$text Text being parsed
	 * @param \StripState $stripState StripState used
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onParserBeforeInternalParse( $parser, &$text, $stripState ) {
		return KolsherutLinksPlacement::onParserBeforeInternalParse( $parser, $text, $stripState );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGetAllRights
	 * @param string[] &$rights Array of rights, which may be added to
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onUserGetAllRights( &$rights ) {
		// Add user right for Kolsherut links management
		$rights[] = 'manage-kolsherut-links';
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param \DatabaseUpdater $updater DatabaseUpdater subclass
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): bool {
		$updater->addExtensionTable(
			'kolsherutlinks_links',
			__DIR__ . '/../sql/table_kolsherutlinks_links.sql'
		);
		$updater->addExtensionTable(
			'kolsherutlinks_rules',
			__DIR__ . '/../sql/table_kolsherutlinks_rules.sql'
		);
		$updater->addExtensionTable(
			'kolsherutlinks_assignments',
			__DIR__ . '/../sql/table_kolsherutlinks_assignments.sql'
		);
		return true;
	}

}
