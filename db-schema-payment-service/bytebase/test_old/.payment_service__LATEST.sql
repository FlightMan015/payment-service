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
  `currency_code` VARCHAR(3) NOT NULL,
  `amount` INT NOT NULL,
  `processed_at` TIMESTAMP(6) NOT NULL,
  `notification_id` BIGINT UNSIGNED DEFAULT NULL,
  `notification_sent_at` TIMESTAMP(6) NULL DEFAULT NULL,
  `reconciliation_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `customer_payments_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_recurring_payment_id_foreign` FOREIGN KEY (`recurring_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `payment_statuses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payments_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE
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
  CONSTRAINT `customers_primary_payment_methods_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE
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
  CONSTRAINT `gateways_payment_types_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gateways_payment_types_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `gateways_payment_types_gateway_id_payment_type_id_unique` ON `gateways_payment_types` (`gateway_id`, `payment_type_id`);

CREATE INDEX `gateways_payment_types_payment_type_id_foreign` ON `gateways_payment_types` (`payment_type_id`);

CREATE TABLE `payment_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
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
  `cc_token` TEXT COMMENT 'Equivalant of the pestroutes merchant_id (which is a credit card token)',
  `cc_expiration_month` INT DEFAULT NULL,
  `cc_expiration_year` INT DEFAULT NULL,
  `cc_last_four` INT UNSIGNED DEFAULT NULL,
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
  `is_pestroutes_auto_pay_enabled` TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  CONSTRAINT `payment_methods_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_methods_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE UNIQUE INDEX `unique_ext_reference_id_per_company` ON `payment_methods` (`ext_reference_id`, `company_id`);

CREATE INDEX `payment_methods_gateway_id_foreign` ON `payment_methods` (`gateway_id`);

CREATE INDEX `payment_methods_payment_type_id_foreign` ON `payment_methods` (`payment_type_id`);

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
  CONSTRAINT `transactions_customer_payment_id_foreign` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `transaction_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;

CREATE INDEX `transactions_customer_payment_id_foreign` ON `transactions` (`customer_payment_id`);

CREATE INDEX `transactions_type_id_foreign` ON `transactions` (`type_id`);

