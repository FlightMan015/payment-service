DO $$
DECLARE
  batch_size int := 1000;
  min_id bigint;
  max_id bigint;
  curr bigint;
BEGIN
  SELECT max(external_ref_id), min(external_ref_id) INTO max_id, min_id
      FROM customer.subscriptions
      WHERE subscriptions.plan_id IS NULL
      AND subscriptions.external_ref_id IS NOT NULL;

  IF max_id IS NULL OR min_id IS NULL THEN RETURN ;
  END IF;

  FOR curr IN min_id..max_id BY batch_size LOOP
    UPDATE customer.subscriptions
    SET subscriptions.plan_id = service_types.plan_id
    FROM field_operations.service_types
    WHERE subscriptions.pestroutes_service_type_id = service_types.external_ref_id
    AND subscriptions.external_ref_id >= curr AND subscriptions.external_ref_id < curr+batch_size;
    COMMIT;
  END LOOP;
END;
$$;