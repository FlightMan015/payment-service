UPDATE street_smarts.outcomes
SET is_decision_maker =
	CASE WHEN id = 3
		THEN false
		ELSE true
	END
WHERE id IN (3, 16, 18);