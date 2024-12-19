CREATE TABLE `account_updater_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `requested_by` INT UNSIGNED DEFAULT NULL,
  `requested_at` TIMESTAMP(6) NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `account_updater_attempts_uuid_unique` ON `account_updater_attempts` (`uuid`);

CREATE TABLE `account_updater_attempts_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` BIGINT UNSIGNED NOT NULL,
  `method_id` BIGINT UNSIGNED NOT NULL,
  `sequence_number` INT UNSIGNED NOT NULL,
  `original_token` TEXT NOT NULL,
  `original_expiration_month` INT NOT NULL,
  `original_expiration_year` INT NOT NULL,
  `updated_token` TEXT,
  `updated_expiration_month` INT DEFAULT NULL,
  `updated_expiration_year` INT DEFAULT NULL,
  `status` TEXT COMMENT 'TokenEx updating result status. Reference: https://docs.tokenex.com/docs/au-response-messages',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `account_updater_attempts_methods_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `account_updater_attempts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `account_updater_attempts_methods_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `account_updater_attempts_unique_attempt_sequence_number` ON `account_updater_attempts_methods` (`attempt_id`, `sequence_number`);

CREATE INDEX `account_updater_attempts_methods_method_id_foreign` ON `account_updater_attempts_methods` (`method_id`);

CREATE TABLE `credit_card_setup_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `office_id` BIGINT UNSIGNED DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'This column is deprecated',
  `gateway_id` BIGINT UNSIGNED NOT NULL,
  `callback_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Customer billing name',
  `address1` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'First line of customer billing address',
  `address2` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Second line of customer billing address',
  `city` VARCHAR(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` VARCHAR(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` VARCHAR(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` VARCHAR(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_request` TEXT COLLATE utf8mb4_unicode_ci,
  `gateway_response` TEXT COLLATE utf8mb4_unicode_ci,
  `ext_reference_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Gateway response ID (transaction setup ID)',
  `generated_urls` JSON DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `credit_card_setup_links_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_UNICODE_CI;

CREATE INDEX `credit_card_setup_links_gateway_id_foreign` ON `credit_card_setup_links` (`gateway_id`);

CREATE TABLE `customer_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `type_id` BIGINT UNSIGNED NOT NULL,
  `recurring_payment_id` BIGINT UNSIGNED DEFAULT NULL,
  `status_id` BIGINT UNSIGNED NOT NULL,
  `method_id` BIGINT UNSIGNED NOT NULL,
  `gateway_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED DEFAULT NULL,
  `original_payment_id` INT DEFAULT NULL COMMENT 'Used for refunding payment (which payment is refunded)',
  `currency_code` VARCHAR(3) NOT NULL,
  `amount` INT NOT NULL,
  `processed_at` TIMESTAMP(6) NOT NULL,
  `notification_id` BIGINT UNSIGNED DEFAULT NULL,
  `notification_sent_at` TIMESTAMP(6) NULL DEFAULT NULL,
  `reconciliation_id` BIGINT UNSIGNED DEFAULT NULL,
  `ext_reference_id` INT UNSIGNED DEFAULT NULL COMMENT 'The Pestroutes payment identifier',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `customer_payments_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways`(`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods`(`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_recurring_payment_id_foreign` FOREIGN KEY (`recurring_payment_id`) REFERENCES `customer_payments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `payment_statuses`(`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `payment_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE INDEX `customer_payments_gateway_id_foreign` ON `customer_payments` (`gateway_id`);

CREATE INDEX `customer_payments_method_id_foreign` ON `customer_payments` (`method_id`);

CREATE INDEX `customer_payments_recurring_payment_id_foreign` ON `customer_payments` (`recurring_payment_id`);

CREATE INDEX `customer_payments_status_id_foreign` ON `customer_payments` (`status_id`);

CREATE INDEX `customer_payments_type_id_foreign` ON `customer_payments` (`type_id`);

CREATE TABLE `customers_primary_payment_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT 'relates to customer_id column in payment_methods table, the customer id from PestRoutes',
  `method_id` BIGINT UNSIGNED NOT NULL COMMENT 'relates to id column in payment_methods table',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `customers_primary_payment_methods_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI COMMENT='Table for defining primary customer payment method';

CREATE UNIQUE INDEX `customers_primary_payment_methods_customer_id_unique` ON `customers_primary_payment_methods` (`customer_id`);

CREATE INDEX `customers_primary_payment_methods_method_id_foreign` ON `customers_primary_payment_methods` (`method_id`);

CREATE TABLE `failed_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) DEFAULT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` TEXT NOT NULL,
  `exception` TEXT NOT NULL,
  `failed_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `failed_jobs_uuid_unique` ON `failed_jobs` (`uuid`);

CREATE TABLE `gateways` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `description` TEXT NOT NULL,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT '0',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE TABLE `gateways_payment_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT UNSIGNED NOT NULL,
  `payment_type_id` BIGINT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `gateways_payment_types_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways`(`id`) ON DELETE CASCADE,
  CONSTRAINT `gateways_payment_types_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `gateways_payment_types_gateway_id_payment_type_id_unique` ON `gateways_payment_types` (`gateway_id`, `payment_type_id`);

CREATE INDEX `gateways_payment_types_payment_type_id_foreign` ON `gateways_payment_types` (`payment_type_id`);

CREATE TABLE `origin_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) DEFAULT NULL,
  `domain` VARCHAR(50) DEFAULT NULL,
  `ip_address` VARCHAR(40) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `origin_services_domain_ip_address_unique` ON `origin_services` (`domain`, `ip_address`);

CREATE TABLE `payment_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `office_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'PestRoutes office id',
  `gateway_id` BIGINT UNSIGNED NOT NULL,
  `payment_type_id` BIGINT UNSIGNED NOT NULL,
  `pestroutes_status_id` TINYINT NOT NULL COMMENT '-1 = soft deleted, 0 = empty, 1 = valid, 2 = invalid, 3 = expired, 4 = last transaction failed',
  `name_on_account_first` TEXT NOT NULL,
  `name_on_account_last` TEXT NOT NULL,
  `description` TEXT,
  `ach_account_number_encrypted` TEXT,
  `ach_routing_number` TEXT,
  `ach_account_last_four` TEXT,
  `ach_account_type_id` TEXT,
  `ach_bank_name` VARCHAR(128) DEFAULT NULL,
  `cc_token` TEXT COMMENT 'Equivalant of the pestroutes merchant_id (which is a credit card token)',
  `cc_expiration_month` INT DEFAULT NULL,
  `cc_expiration_year` INT DEFAULT NULL,
  `cc_last_four` VARCHAR(4) DEFAULT NULL,
  `address_line1` TEXT NOT NULL,
  `address_line2` TEXT,
  `address_line3` TEXT,
  `email` VARCHAR(256) NOT NULL,
  `city` VARCHAR(64) NOT NULL,
  `province` VARCHAR(2) NOT NULL,
  `postal_code` VARCHAR(10) NOT NULL,
  `country_code` VARCHAR(2) NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT '0',
  `is_anonymized` TINYINT(1) NOT NULL DEFAULT '0',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `ext_reference_id` INT UNSIGNED DEFAULT NULL COMMENT 'The pestroutes payment_profile_id',
  `pestroutes_payment_hold_date` DATETIME DEFAULT NULL COMMENT 'The pestroutes payment hold date for the payment profile',
  `pestroutes_payment_date_created` DATETIME DEFAULT NULL COMMENT 'The date that the pestroutes payment profile was created',
  `pestroutes_date_updated` DATETIME DEFAULT NULL COMMENT 'The date that the pestroutes payment profile was updated',
  `is_pestroutes_auto_pay_enabled` TINYINT(1) NOT NULL DEFAULT '0',
  `updated_by_account_updater_at` TIMESTAMP(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `payment_methods_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways`(`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_methods_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `unique_ext_reference_id_per_company` ON `payment_methods` (`ext_reference_id`, `company_id`);

CREATE INDEX `payment_methods_gateway_id_foreign` ON `payment_methods` (`gateway_id`);

CREATE INDEX `payment_methods_payment_type_id_foreign` ON `payment_methods` (`payment_type_id`);

CREATE INDEX `idx_customer_id` ON `payment_methods` (`customer_id`);

CREATE TABLE `payment_statuses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `description` TEXT NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE TABLE `payment_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `description` TEXT NOT NULL,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT '0',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT '1',
  `sort_order` INT NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `payment_types_sort_order_unique` ON `payment_types` (`sort_order`);

CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `ticket_id` INT UNSIGNED DEFAULT NULL,
  `amount` DOUBLE(8,2) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT '0',
  `request_origin` VARCHAR(255) DEFAULT NULL,
  `service_response` TEXT,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE TABLE `payments_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `applied_at` TIMESTAMP(6) NOT NULL,
  `applied_amount` INT NOT NULL,
  `pestroutes_invoice_date` TIMESTAMP(6) NULL DEFAULT NULL COMMENT 'Temporarily field. Will be deprecated in the future',
  `pestroutes_invoice_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Temporarily field. Will be deprecated in the future',
  `pestroutes_invoice_total` INT DEFAULT NULL COMMENT 'Temporarily field. Will be deprecated in the future',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `payments_invoices_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `customer_payments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE INDEX `payments_invoices_payment_id_foreign` ON `payments_invoices` (`payment_id`);

CREATE TABLE `transaction_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `description` TEXT NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE TABLE `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_payment_id` BIGINT UNSIGNED NOT NULL,
  `type_id` BIGINT UNSIGNED NOT NULL,
  `raw_request_log` TEXT,
  `raw_response_log` TEXT,
  `gateway_transaction_id` TEXT NOT NULL,
  `gateway_response_code` VARCHAR(32) NOT NULL,
  `is_anonymized` TINYINT(1) NOT NULL DEFAULT '0',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `transactions_customer_payment_id_foreign` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `transaction_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE INDEX `transactions_customer_payment_id_foreign` ON `transactions` (`customer_payment_id`);

CREATE INDEX `transactions_type_id_foreign` ON `transactions` (`type_id`);

