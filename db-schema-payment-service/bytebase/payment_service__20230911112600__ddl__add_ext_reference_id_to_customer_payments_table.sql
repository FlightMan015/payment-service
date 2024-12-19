ALTER TABLE `customer_payments`
    ADD `ext_reference_id` INT UNSIGNED NULL COMMENT 'The Pestroutes payment identifier' AFTER `reconciliation_id`;