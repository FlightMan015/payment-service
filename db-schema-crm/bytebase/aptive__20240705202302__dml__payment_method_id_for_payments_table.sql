DO $$
DECLARE
  batch_size int := 1000;
  min_id bigint;
  max_id bigint;
  curr bigint;
BEGIN
    SELECT max(external_ref_id), min(external_ref_id) INTO max_id, min_id
    FROM billing.payments
    WHERE notes ~ 'lastFour:\d{4}';

    IF max_id IS NULL OR min_id IS NULL THEN RETURN;
    END IF;

    FOR curr IN min_id..max_id BY batch_size LOOP
        BEGIN
            UPDATE billing.payments
            SET pestroutes_json = jsonb_set(pestroutes_json, '{lastFour}', to_jsonb(substring(notes FROM 'lastFour:(\d{4})')), true)
            WHERE notes ~ 'lastFour:\d{4}' AND payments.external_ref_id >= curr AND payments.external_ref_id < curr+batch_size;
        EXCEPTION WHEN OTHERS THEN ROLLBACK;
            INSERT INTO billing.payment_update_last4_log (batch_start_id, step, error_message) VALUES (curr, 'setting pestroutes_json->>lastFour', SQLERRM);
            -- Raise notice to log the error and continue with the next batch
            RAISE NOTICE 'Error processing batch starting with id %: %', curr, SQLERRM;
        END;
    END LOOP;
END;
$$;

WITH payment_methods_without_duplicates AS (
    SELECT payment_methods.id,
       payment_methods.external_ref_id,
       payment_methods.last_four,
       payment_methods.pestroutes_customer_id
    FROM (
        SELECT payment_methods.id,
               payment_methods.external_ref_id,
               payment_methods.last_four,
               payment_methods.pestroutes_customer_id,
               count(1) over (partition by payment_methods.last_four, payment_methods.pestroutes_customer_id) as duplicates_count
        FROM billing.payment_methods
    ) AS payment_methods
    WHERE duplicates_count = 1
)
INSERT INTO billing.distinct_payment_methods
(id, external_ref_id, pestroutes_customer_id, last_four)
SELECT id, external_ref_id, pestroutes_customer_id, last_four
FROM payment_methods_without_duplicates;

WITH RECURSIVE payments_with_last_four AS (
    SELECT
        payments.id,
        payments.external_ref_id,
        payments.payment_method_id,
        payments.pestroutes_customer_id,
        payments.original_payment_id,
        payments.original_payment_id AS root_payment_id,
        payments.pestroutes_json->>'lastFour' AS last_four
    FROM billing.payments
    WHERE (payments.pestroutes_json->>'lastFour' IS NOT NULL AND payments.pestroutes_json->>'lastFour' <> '') OR payments.pestroutes_customer_id IS NULL
    UNION ALL
    SELECT
        payments.id,
        payments.external_ref_id,
        payments.payment_method_id,
        payments.pestroutes_customer_id,
        payments.original_payment_id,
        parent_payments.root_payment_id,
        COALESCE(parent_payments.last_four, CASE WHEN payments.pestroutes_json->>'lastFour' <> '' THEN payments.pestroutes_json->>'lastFour' END) AS last_four
    FROM billing.payments
             INNER JOIN payments_with_last_four AS parent_payments ON parent_payments.id = payments.original_payment_id
)
INSERT INTO billing.new_payments_with_last_four
(id, external_ref_id, payment_method_id, pestroutes_customer_id, original_payment_id, root_payment_id, last_four)
SELECT id, external_ref_id, payment_method_id, pestroutes_customer_id, original_payment_id, root_payment_id, last_four
FROM payments_with_last_four;

CREATE INDEX last_four_new_payments_idx ON billing.new_payments_with_last_four (last_four);
CREATE INDEX last_four_distinct_payment_methods_idx ON billing.distinct_payment_methods (last_four);

DO $$
DECLARE
  batch_size int := 1000;
  min_id bigint;
  max_id bigint;
  curr bigint;
BEGIN
    SELECT max(external_ref_id), min(external_ref_id) INTO max_id, min_id
    FROM billing.payments
    WHERE payments.payment_method_id IS NULL;

    IF max_id IS NULL OR min_id IS NULL THEN RETURN;
    END IF;

    FOR curr IN min_id..max_id BY batch_size LOOP
        BEGIN
        UPDATE billing.payments
        SET payment_method_id = updating_source.payment_method_id
        FROM (
            SELECT new_payments_with_last_four.id, distinct_payment_methods.id AS payment_method_id
            FROM billing.new_payments_with_last_four
            INNER JOIN billing.distinct_payment_methods
                ON new_payments_with_last_four.last_four IS NOT NULL
                    AND new_payments_with_last_four.last_four = distinct_payment_methods.last_four
                    AND new_payments_with_last_four.pestroutes_customer_id = distinct_payment_methods.pestroutes_customer_id
            WHERE new_payments_with_last_four.payment_method_id IS NULL
              AND new_payments_with_last_four.external_ref_id >= curr
              AND new_payments_with_last_four.external_ref_id < curr + batch_size
            ORDER BY new_payments_with_last_four.external_ref_id
        ) AS updating_source
        WHERE updating_source.id = payments.id;
        EXCEPTION WHEN OTHERS THEN ROLLBACK;
            INSERT INTO billing.payment_update_last4_log (batch_start_id, step, error_message) VALUES (curr, 'setting payment method id', SQLERRM);
            -- Raise notice to log the error and continue with the next batch
            RAISE NOTICE 'Error processing batch starting with id %: %', curr, SQLERRM;
        END;
    END LOOP;
END;
$$;

DROP INDEX billing.last_four_new_payments_idx;
DROP INDEX billing.last_four_distinct_payment_methods_idx;
