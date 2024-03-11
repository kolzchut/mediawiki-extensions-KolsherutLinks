SELECT page_rules.page_id, page_rules.rule_id, page_rules.link_id, page_rules.fallback, page_rules.priority
	FROM kolsherutlinks_rules AS page_rules
	WHERE page_rules.page_id IS NOT NULL
	GROUP BY page_rules.page_id
UNION
SELECT pp.pp_page AS page_id, ca_rules.rule_id, ca_rules.link_id, ca_rules.fallback, ca_rules.priority
	FROM kolsherutlinks_rules AS ca_rules
	INNER JOIN category AS ca_cat ON ca_cat.cat_id=ca_rules.content_area_id
	INNER JOIN page_props AS pp ON pp.pp_value=REPLACE(ca_cat.cat_title, '_', ' ')
	WHERE pp.pp_propname='ArticleContentArea'
UNION
SELECT cl1.cl_from AS page_id, cat_rules.rule_id, cat_rules.link_id, cat_rules.fallback, cat_rules.priority
	FROM kolsherutlinks_rules AS cat_rules
	INNER JOIN category AS cat1 ON cat1.cat_id=cat_rules.category_id_1
	LEFT JOIN category AS cat2 ON cat2.cat_id=cat_rules.category_id_2
	LEFT JOIN category AS cat3 ON cat3.cat_id=cat_rules.category_id_3
	LEFT JOIN category AS cat4 ON cat4.cat_id=cat_rules.category_id_4
	INNER JOIN categorylinks AS cl1 ON cl1.cl_to=cat1.cat_title
	LEFT JOIN categorylinks AS cl2 ON cl2.cl_to=cat2.cat_title
	LEFT JOIN categorylinks AS cl3 ON cl3.cl_to=cat3.cat_title
	LEFT JOIN categorylinks AS cl4 ON cl4.cl_to=cat4.cat_title
	WHERE (cat_rules.category_id_2 IS NULL OR cl2.cl_from=cl1.cl_from)
		AND (cat_rules.category_id_3 IS NULL OR cl3.cl_from=cl1.cl_from)
		AND (cat_rules.category_id_4 IS NULL OR cl4.cl_from=cl1.cl_from)
ORDER BY page_id ASC, fallback ASC, priority DESC;

/* take 4 */
SELECT p.page_id, rules.link_id, rules.fallback, rules.priority, rules.rule_id
FROM (
	SELECT page_rules.page_id FROM kolsherutlinks_rules AS page_rules
		WHERE page_rules.page_id IS NOT NULL GROUP BY page_rules.page_id
	UNION
	SELECT pp.pp_page AS page_id FROM kolsherutlinks_rules AS ca_rules
		INNER JOIN page_props AS pp ON pp.pp_value=ca_rules.content_area_id
		WHERE pp.pp_propname='ArticleContentArea'
		GROUP BY pp.pp_page
	UNION
	SELECT cl.cl_from AS page_id FROM kolsherutlinks_rules AS cat_rules
		INNER JOIN category AS cat ON (
			cat.cat_id=cat_rules.category_id_1
			AND (cat_rules.category_id_2 IS NULL OR cat.cat_id=cat_rules.category_id_2)
			AND (cat_rules.category_id_3 IS NULL OR cat.cat_id=cat_rules.category_id_3)
			AND (cat_rules.category_id_4 IS NULL OR cat.cat_id=cat_rules.category_id_4)
		)
		INNER JOIN categorylinks AS cl ON cl.cl_to=cat.cat_title
	) AS p
LEFT JOIN page_props AS rules_pp ON (
	rules_pp.pp_page=p.page_id AND rules_pp.pp_propname='ArticleContentArea'
)
LEFT JOIN categorylinks AS rules_cl ON rules_cl.cl_from=p.page_id
LEFT JOIN category AS rules_cat ON rules_cat.cat_title=rules_cl.cl_to
LEFT JOIN kolsherutlinks_rules AS rules ON (
	rules.page_id=p.page_id
	OR rules.content_area_id=rules_pp.pp_value
	OR (
		rules.category_id_1=rules_cat.cat_id
		AND (rules.category_id_2 IS NULL OR rules.category_id_2=rules_cat.cat_id)
		AND (rules.category_id_3 IS NULL OR rules.category_id_3=rules_cat.cat_id)
		AND (rules.category_id_4 IS NULL OR rules.category_id_4=rules_cat.cat_id)
	)
)
WHERE rules.rule_id IS NOT NULL
ORDER BY p.page_id ASC, rules.fallback ASC, rules.priority DESC

/* take 3 */
SELECT p.page_id, rules.link_id, rules.fallback, rules.priority
FROM (
	SELECT page_rules.page_id FROM kolsherutlinks_rules AS page_rules
		WHERE page_rules.page_id IS NOT NULL GROUP BY page_rules.page_id
	UNION
	SELECT pp.pp_page AS page_id FROM kolsherutlinks_rules AS ca_rules
		INNER JOIN page_props AS pp ON pp.pp_value=ca_rules.content_area_id
		WHERE pp.pp_propname='ArticleContentArea'
		GROUP BY pp.pp_page
	UNION
	SELECT cl.cl_from AS page_id FROM kolsherutlinks_rules AS cat_rules
		INNER JOIN category AS cat ON (
			cat.cat_id=cat_rules.category_id_1 OR cat.cat_id=cat_rules.category_id_2 OR 
			cat.cat_id=cat_rules.category_id_3 OR cat.cat_id=cat_rules.category_id_4
		)
		INNER JOIN categorylinks AS cl ON cl.cl_to=cat.cat_title
	) AS p
LEFT JOIN page_props AS rules_pp ON (
	rules_pp.pp_page=p.page_id AND rules_pp.pp_propname='ArticleContentArea'
)
LEFT JOIN categorylinks AS rules_cl ON rules_cl.cl_from=p.page_id
LEFT JOIN category AS rules_cat ON rules_cat.cat_title=rules_cl.cl_to
INNER JOIN kolsherutlinks_rules AS page_rules ON page_rules.page_id=p.page_id
INNER JOIN kolsherutlinks_rules AS ca_rules ON ca_rules.content_area_id=rules_pp.pp_value
INNER JOIN kolsherutlinks_rules AS cat_rules ON (
	cat_rules.category_id_1=rules_cat.cat_id
	AND (cat_rules.category_id_2 IS NULL OR cat_rules.category_id_2=rules_cat.cat_id)
	AND (cat_rules.category_id_3 IS NULL OR cat_rules.category_id_3=rules_cat.cat_id)
	AND (cat_rules.category_id_4 IS NULL OR cat_rules.category_id_4=rules_cat.cat_id)
)
ORDER BY p.page_id ASC, rules.fallback ASC, rules.priority DESC



/* take 2 */
SELECT p.page_id, rules.link_id, rules.fallback, rules.priority
FROM (
	SELECT page_rules.page_id FROM kolsherutlinks_rules AS page_rules
		WHERE page_rules.page_id IS NOT NULL GROUP BY page_rules.page_id
	UNION
	SELECT pp.pp_page AS page_id FROM kolsherutlinks_rules AS ca_rules
		INNER JOIN page_props AS pp ON pp.pp_value=ca_rules.content_area_id
		WHERE pp.pp_propname='ArticleContentArea'
		GROUP BY pp.pp_page
	UNION
	SELECT cl.cl_from AS page_id FROM kolsherutlinks_rules AS cat_rules
		INNER JOIN category AS cat ON (
			cat.cat_id=cat_rules.category_id_1 OR cat.cat_id=cat_rules.category_id_2 OR 
			cat.cat_id=cat_rules.category_id_3 OR cat.cat_id=cat_rules.category_id_4
		)
		INNER JOIN categorylinks AS cl ON cl.cl_to=cat.cat_title
	) AS p
LEFT JOIN page_props AS rules_pp ON (
	rules_pp.pp_page=p.page_id AND rules_pp.pp_propname='ArticleContentArea'
)
LEFT JOIN categorylinks AS rules_cl ON rules_cl.cl_from=p.page_id
LEFT JOIN category AS rules_cat ON rules_cat.cat_title=rules_cl.cl_to
LEFT JOIN kolsherutlinks_rules AS rules ON (
	rules.page_id=p.page_id
	OR rules.content_area_id=rules_pp.pp_value
	OR (
		rules.category_id_1=rules_cat.cat_id
		AND (rules.category_id_2 IS NULL OR rules.category_id_2=rules_cat.cat_id)
		AND (rules.category_id_3 IS NULL OR rules.category_id_3=rules_cat.cat_id)
		AND (rules.category_id_4 IS NULL OR rules.category_id_4=rules_cat.cat_id)
	)
)
WHERE rules.rule_id IS NOT NULL
ORDER BY p.page_id ASC, rules.fallback ASC, rules.priority DESC

/* take one */
SELECT p.page_id, rules.link_id, rules.fallback, rules.priority
FROM page AS p
	LEFT JOIN page_props AS rules_pp ON (
		rules_pp.pp_page=p.page_id AND rules_pp.pp_propname='ArticleContentArea'
	)
	LEFT JOIN categorylinks AS rules_cl ON rules_cl.cl_from=p.page_id
	LEFT JOIN category AS rules_cat ON rules_cat.cat_title=rules_cl.cl_to
	INNER JOIN kolsherutlinks_rules AS rules ON (
		rules.page_id=p.page_id
		OR rules.content_area_id=rules_pp.pp_value
		OR rules.category_id_1=rules_cat.cat_id OR rules.category_id_2=rules_cat.cat_id
		OR rules.category_id_3=rules_cat.cat_id OR rules.category_id_4=rules_cat.cat_id
	)
WHERE p.page_id IN (
	SELECT page_rules.page_id FROM kolsherutlinks_rules AS page_rules
		WHERE page_rules.page_id IS NOT NULL GROUP BY page_rules.page_id
	UNION
	SELECT pp.pp_page AS page_id FROM kolsherutlinks_rules AS ca_rules
		INNER JOIN page_props AS pp ON pp.pp_value=ca_rules.content_area_id
		WHERE pp.pp_propname='ArticleContentArea'
		GROUP BY pp.pp_page
	UNION
	SELECT cl.cl_from AS page_id FROM kolsherutlinks_rules AS cat_rules
		INNER JOIN category AS cat ON (
			cat.cat_id=cat_rules.category_id_1 OR cat.cat_id=cat_rules.category_id_2 OR 
			cat.cat_id=cat_rules.category_id_3 OR cat.cat_id=cat_rules.category_id_4
		)
		INNER JOIN categorylinks AS cl ON cl.cl_to=cat.cat_title
)
ORDER BY p.page_id ASC, rules.fallback ASC, rules.priority DESC


/*
SELECT page_rules.page_id FROM kolsherutlinks_rules AS page_rules WHERE page_id IS NOT NULL GROUP BY page_id UNION SELECT pp.pp_page AS page_id FROM kolsherutlinks_rules AS ca_rules INNER JOIN page_props AS pp ON pp.pp_value=ca_rules.content_area_id WHERE pp.pp_propname='ArticleContentArea' GROUP BY pp.pp_page UNION SELECT cl.cl_from AS page_id FROM kolsherutlinks_rules AS cat_rules INNER JOIN category AS cat ON (cat.cat_id=cat_rules.category_id_1 OR cat.cat_id=cat_rules.category_id_2 OR cat.cat_id=cat_rules.category_id_3 OR cat.cat_id=cat_rules.category_id_4) INNER JOIN categorylinks AS cl ON cl.cl_to=cat.cat_title
*/
