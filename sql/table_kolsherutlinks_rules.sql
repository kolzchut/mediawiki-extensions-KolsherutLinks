CREATE TABLE IF NOT EXISTS /*_*/kolsherutlinks_rules (
	rule_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	link_id INTEGER UNSIGNED NOT NULL,
	fallback INTEGER NOT NULL DEFAULT 0,
	page_id INTEGER UNSIGNED,
	content_area varbinary(255),
	category_1 varbinary(255),
	category_2 varbinary(255),
	category_3 varbinary(255),
	category_4 varbinary(255),
	priority INTEGER DEFAULT 0,

	FOREIGN KEY (link_id) REFERENCES /*_*/kolsherutlinks_links(link_id),
	FOREIGN KEY (page_id) REFERENCES /*_*/page(page_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/page_id ON /*_*/kolsherutlinks_rules (page_id);
CREATE INDEX /*i*/content_area ON /*_*/kolsherutlinks_rules (content_area);
CREATE INDEX /*i*/category_1 ON /*_*/kolsherutlinks_rules (category_1);
CREATE INDEX /*i*/category_2 ON /*_*/kolsherutlinks_rules (category_2);
CREATE INDEX /*i*/category_3 ON /*_*/kolsherutlinks_rules (category_3);
CREATE INDEX /*i*/category_4 ON /*_*/kolsherutlinks_rules (category_4);
