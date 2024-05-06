CREATE TABLE IF NOT EXISTS /*_*/kolsherutlinks_links (
	link_id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	url BLOB NOT NULL,
	text TEXT NOT NULL
) /*$wgDBTableOptions*/;
