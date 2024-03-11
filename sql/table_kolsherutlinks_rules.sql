CREATE TABLE IF NOT EXISTS /*_*/kolsherutlinks_rules (
	rule_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	link_id INTEGER UNSIGNED NOT NULL,
	fallback INTEGER NOT NULL DEFAULT 0,
	page_id INTEGER UNSIGNED,
	content_area_id INTEGER UNSIGNED,
	category_id_1 INTEGER UNSIGNED,
	category_id_2 INTEGER UNSIGNED,
	category_id_3 INTEGER UNSIGNED,
	category_id_4 INTEGER UNSIGNED,
	priority INTEGER DEFAULT 0,

	FOREIGN KEY (link_id) REFERENCES /*_*/kolsherutlinks_links(link_id) ON DELETE CASCADE,
	FOREIGN KEY (page_id) REFERENCES /*_*/page(page_id) ON DELETE CASCADE
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/page_id ON /*_*/kolsherutlinks_rules (page_id);
CREATE INDEX /*i*/content_area_id ON /*_*/kolsherutlinks_rules (content_area_id);
CREATE INDEX /*i*/category_id_1 ON /*_*/kolsherutlinks_rules (category_id_1);
CREATE INDEX /*i*/category_id_2 ON /*_*/kolsherutlinks_rules (category_id_2);
CREATE INDEX /*i*/category_id_3 ON /*_*/kolsherutlinks_rules (category_id_3);
CREATE INDEX /*i*/category_id_4 ON /*_*/kolsherutlinks_rules (category_id_4);
