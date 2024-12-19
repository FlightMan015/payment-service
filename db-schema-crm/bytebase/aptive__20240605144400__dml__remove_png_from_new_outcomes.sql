UPDATE street_smarts.outcomes
SET icon_filename =
	CASE WHEN id = 31
		THEN 'mapMarkerDarkPurple'::character varying
		ELSE 'mapMarkerRed'::character varying
	END
WHERE id >= 28 AND id <= 32;