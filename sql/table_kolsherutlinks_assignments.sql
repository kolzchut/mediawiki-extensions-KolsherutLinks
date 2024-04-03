CREATE TABLE IF NOT EXISTS /*_*/kolsherutlinks_assignments (
	page_id INTEGER UNSIGNED NOT NULL,
	link_id INTEGER UNSIGNED NOT NULL,
	PRIMARY KEY (page_id, link_id),
	FOREIGN KEY (page_id) REFERENCES /*_*/page(page_id),
	FOREIGN KEY (link_id) REFERENCES /*_*/kolsherutlinks_links(link_id)
) /*$wgDBTableOptions*/;
