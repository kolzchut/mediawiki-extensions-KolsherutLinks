CREATE TABLE IF NOT EXISTS /*_*/kolsherutlinks_links (
	link_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	url BLOB NOT NULL,
	text TEXT NOT NULL
) /*$wgDBTableOptions*/;
