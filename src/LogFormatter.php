<?php
// declare( strict_types = 1 );
namespace MediaWiki\Extension\KolsherutLinks;

use Category;
use LogFormatter as CoreLogFormatter;
use Message;
use Title;

class LogFormatter extends CoreLogFormatter {

	/**
	 * @return array
	 */
	public function getMessageParameters(): array {
		$params = parent::getMessageParameters();
		$type = $this->entry->getFullType();
		$data = $this->entry->getParameters();

		// Prepare link to admin page for the affected Kol-Sherut link.
		$target = $this->entry->getTarget();
		$targetLink = $this->getLinkRenderer()->makeLink(
			$target,
			$this->msg( 'kolsherutlinks-log-link-name', $data['link_id'] ),
			[],
			[ 'link_id' => $data['link_id'] ]
		);
		$params[2] = Message::rawParam( $targetLink );

		// Prepare custom message params according to type of action taken.
		$params[3] = $data['url'];
		switch ( $type ) {
			case 'kolsherutlinks/linkCreate':
			case 'kolsherutlinks/linkEdit':
			case 'kolsherutlinks/linkDelete':
				// No additional params needed.
				break;
			case 'kolsherutlinks/pageRule':
			case 'kolsherutlinks/pageRuleDelete':
				$title = Title::newFromID( $data['page_id'] );
				$params[4] = $title->getBaseText();
				break;
			case 'kolsherutlinks/categoryRule':
			case 'kolsherutlinks/categoryRuleDelete':
				$categoryNames = implode(
					', ',
					array_map( fn( $categoryId ) => Category::newFromID( $categoryId )->getTitle()->getBaseText(),
						array_filter( [
							$data['category_id_1'] ?? 0, $data['category_id_2'] ?? 0, $data['category_id_3'] ?? 0,
							$data['category_id_4'] ?? 0
						] )
					)
				);
				if ( !empty( $data['content_area_id'] ) ) {
					if ( !empty( $categoryNames ) ) {
						$categoryNames .= ', ';
					}
					$categoryNames .= $this->msg( 'kolsherutlinks-details-rule-header-content-area' ) .
						" '" . Category::newFromID( $data['content_area_id'] )->getTitle()->getBaseText() . "'";
				}
				$params[4] = $categoryNames;
				break;
		}

		$this->parsedParameters = $params;
		return $params;
	}

}
