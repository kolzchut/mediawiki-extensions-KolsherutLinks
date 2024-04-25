# Kolsherut Links

## Purpose

This extension provides a management interface for automatically embedding
external links and accompanying text into wiki articles according to ranked
rules specifying categories, combinations of categories, main content areas,
or page titles.

## Installation

1. Download the extension
2. Run `composer update` in MediaWiki's installation directory.
3. Add `wfLoadExtension( 'KolsherutLinks' );` to `LocalSettings.php` or your custom PHP config file.
4. Run `php update.php` in MediaWiki's `maintenance` directory.

## Configuration

Optionally add `$wgKolsherutLinksPlacement` to `LocalSettings.php` to map article types
to placement locations for embedded links. Accepted values are 'top', 'bottom', or a
regular expression containing `$1` which will be replaced with the embedded link(s)
within the context of the regular expression's first match in the wikitext. Sub-match
expressions in parentheses cannot be used in these regular expressions.

```php
$wgKolsherutLinksPlacement = [
	'Article Type 1' => 'top',
	'Article Type 2' => 'bottom',
	'Article Type 3' => 'What to match before the link\\s+$1\\s+What to match after.',
	'default' => 'top', /* catch-all for remaining article types */
];
```

Note that article type differentiation requires the WRArticleType extension, otherwise
the 'default' placement will be used for all articles that don't contain the
`{{#kolsherut_links:}}` tag.

Optionally add `$wgKolsherutLinksExcludedArticleTypes` to `LocalSettings.php` to specify
article types whose pages will not receive embedded links even if they otherwise match
defined Kolsherut Links rules.

```php
$wgKolsherutLinksExcludedArticleTypes = [
	'Excluded Article Type 1', 'Excluded Article Type 2'
];
```

## Explicit Placement of Links

For any article where the location of embedded links needs to differ from the placement
determined by default or article type, the `{{#kolsherut_links:}}` tag can be inserted
anywhere in the article's wikitext. This overrides all other placement configuration.

## Administrative Interface

The page `Special:KolsherutLinksList` is used for viewing, adding, and removing links.
Clicking a link in the list, or pressing the button to add a new one, will lead to the
link details interface where the link's URL and accompanying text can by edited, as
well as all page- and category-based rules governing on which pages the link can be
embedded.

## Template

Each embedment of one or two links will be output as HTML based on the template
`kolsherut-links-block.mustache`.

Note that in lieu of placing the `{{{url}}}` tag in this template, it is alternately
possible to place the same tag directly in the 'Text' for each link (which is permitted
to include markup). This is more cumbersome and should only be done if the location of
the `<a>` tag relative to the link's text needs to vary from one link to another.
