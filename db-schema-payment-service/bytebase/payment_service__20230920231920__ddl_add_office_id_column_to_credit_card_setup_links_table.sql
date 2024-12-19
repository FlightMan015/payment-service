 ALTER TABLE credit_card_setup_links CHANGE customer_id customer_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'This column is deprecated';
 ALTER TABLE `credit_card_setup_links` add `office_id` bigint unsigned null after `id`;
