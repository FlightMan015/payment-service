ALTER TABLE `payment_methods`
    ADD `office_id` SMALLINT UNSIGNED NULL COMMENT 'PestRoutes office id' AFTER `customer_id`;