<?php

namespace MediaWiki\Extension\KolsherutLinks;

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use Parser;
use TemplateParser;

/**
 * Link placement functions for Kol-Sherut links
 */
class KolsherutLinksPlacement {

	/**
	 * @param \Parser $parser
	 * @param string &$text Text being parsed
	 * @param \StripState $stripState StripState used
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onParserBeforeInternalParse( $parser, &$text, $stripState ) {
		if ( $parser->getTitle()->getNamespace() != NS_MAIN ) {
			// Ignore non-article.
			return true;
		}
		// We're only interested in the main body of the page, so the parser should have a revision ID.
		if ( !empty( $parser->getRevisionId() ) ) {
			// Are any links assigned to this page?
			$pageId = $parser->getTitle()->getArticleID();
			$links = KolsherutLinks::getLinksByPageId( $pageId );
			if ( empty( $links ) ) {
				return true;
			}
			// Check for {{#kolsherut_links:}} tag. If it's missing we'll insert it.
			if ( strpos( $text, '{{#kolsherut_links' ) !== false ) {
				return true;
			}
			// Default to bottom if placement position can't be determined from configuration.
			$position = 'bottom';
			// Determine placement position by ArticleType.
			$config = MediaWikiServices::getInstance()->getMainConfig()->get( 'KolsherutLinksPlacement' );
			if ( ExtensionRegistry::getInstance()->isLoaded( 'ArticleType' ) ) {
				$articleType = \MediaWiki\Extension\ArticleType\ArticleType::getArticleType( $parser->getTitle() );
				$position = empty( $config[$articleType] ) ?
					( empty( $config['default'] ) ? 'bottom' : $config['default'] )
					: $config[$articleType];
			} elseif ( !empty( $config['default'] ) ) {
				$position = $config['default'];
			}
			// Place the links tag.
			switch ( $position ) {
				case 'top':
					$text = "{{#kolsherut_links:}}\n" . $text;
					break;
				case 'bottom':
					$text = $text . "\n{{#kolsherut_links:}}";
					break;
				default:
					// Assume $position is a regular expression.
					// Note I have lazily not supported the use of (...) subexpressions. Life is too short.
					$search = '/(' . str_replace( '$1', ')(', $position ) . ')/';
					$replace = "\\1{{#kolsherut_links:}}\\2";
					$text = preg_replace( $search, $replace, $text, 1 );
			}
		}
		return true;
	}

	/**
	 * @param \Parser $parser Parser object being initialised
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onParserFirstCallInit( $parser ): bool {
		$parser->setFunctionHook( 'kolsherut_links', [ __CLASS__, 'renderLinksBlock' ] );
		return true;
	}

	/**
	 * Parser hook handler for {{#kolsherut_links:}}
	 *
	 * @param \Parser &$parser : Parser instance available to render wikitext into html, or parser methods.
	 * @return string|array HTML output with action flags
	 */
	public static function renderLinksBlock( Parser &$parser ) {
		$pageId = $parser->getTitle()->getArticleID();
		$links = KolsherutLinks::getLinksByPageId( $pageId );
		if ( empty( $links ) ) {
			return '';
		}

		// Assemble data to feed to template.
		$links_data = [];
		foreach ( $links as $link ) {
			$url = self::renderUrl( $link );
			// Parse any {{url}} in the link text from the DB.
			$text = str_replace( '{{{url}}}', $url, $link['text'] );
			$links_data[] = [
				'text' => $text,
				'url' => $url,
			];
		}

		$templateData = [
			'title' => wfMessage( 'kolsherutlinks-links-section-title' )->text(),
			'link' => $links_data
		];

		// Parse template and return output.
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		$return = $templateParser->processTemplate( 'kolsherut-links-block', $templateData );
		return [ $return, 'isHTML' => true ];
	}

	/**
	 * @param array $link Link data
	 * @return string $url
	 */
	private static function renderUrl( $link ) {
		// Assemble UTM parameters
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$server = $config->get( 'Server' );
		$server = preg_replace( '|^http(s?)://|', '', $server );
		$languageCode = $config->get( 'LanguageCode' );
		$source = $server . '/' . $languageCode;
		$params = [
			'utm_source' => $source,
			'utm_medium' => 'website',
			'utm_campaign' => $link['link_id'],
		];

		// Encode and return complete URL
		return $link['url'] . '?' . http_build_query( $params );
	}

}
