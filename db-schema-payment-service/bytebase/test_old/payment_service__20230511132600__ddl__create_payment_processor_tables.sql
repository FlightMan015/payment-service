CREATE TABLE `payment_types`
(
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)     NOT NULL,
    `description` TEXT            NOT NULL,
    `is_hidden`   BOOLEAN         NOT NULL DEFAULT FALSE,
    `is_enabled`  BOOLEAN         NOT NULL DEFAULT TRUE,
    `sort_order`  INT             NOT NULL,
    `created_by`  INT UNSIGNED    DEFAULT NULL,
    `updated_by`  INT UNSIGNED    DEFAULT NULL,
    `created_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
	`updated_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `payment_types_sort_order_unique` (`sort_order`)
) ENGINE = InnoDB;

CREATE TABLE `transaction_types`
(
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)     NOT NULL,
    `description` TEXT            NOT NULL,
    `created_by`  INT UNSIGNED    DEFAULT NULL,
    `updated_by`  INT UNSIGNED    DEFAULT NULL,
    `created_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
	`updated_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`)
) ENGINE = InnoDB;

CREATE TABLE `gateways`
(
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)     NOT NULL,
    `description` TEXT            NOT NULL,
    `is_hidden`   BOOLEAN         NOT NULL DEFAULT FALSE,
    `is_enabled`  BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_by`  INT UNSIGNED    DEFAULT NULL,
    `updated_by`  INT UNSIGNED    DEFAULT NULL,
    `created_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`)
) ENGINE = InnoDB;

CREATE TABLE `payment_statuses`
(
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)     NOT NULL,
    `description` TEXT            NOT NULL,
    `created_by`  INT UNSIGNED    DEFAULT NULL,
    `updated_by`  INT UNSIGNED    DEFAULT NULL,
    `created_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
	`updated_at`  TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`)
) ENGINE = InnoDB;

CREATE TABLE `gateways_payment_types`
(
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gateway_id`      BIGINT UNSIGNED NOT NULL,
    `payment_type_id` BIGINT UNSIGNED NOT NULL,
    `created_by`      INT UNSIGNED    DEFAULT NULL,
    `updated_by`      INT UNSIGNED    DEFAULT NULL,
    `created_at`      TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
	`updated_at`      TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `gateways_payment_types_gateway_id_payment_type_id_unique` (`gateway_id`, `payment_type_id`),
    CONSTRAINT `gateways_payment_types_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`) ON DELETE CASCADE,
    CONSTRAINT `gateways_payment_types_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE `payment_methods`
(
    `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`                   BIGINT UNSIGNED NOT NULL,
    `customer_id`                  BIGINT UNSIGNED NOT NULL,
    `gateway_id`                   BIGINT UNSIGNED NOT NULL,
    `payment_type_id`              BIGINT UNSIGNED NOT NULL,
    `name_on_account_first`        TEXT            NOT NULL,
    `name_on_account_last`         TEXT            NOT NULL,
    `ach_account_number_encrypted` TEXT,
    `ach_routing_number`           TEXT,
    `ach_account_last_four`        TEXT,
    `ach_account_type_id`          TEXT,
    `cc_token`                     TEXT,
    `cc_expiration_month`          INT                      DEFAULT NULL,
    `cc_expiration_year`           INT                      DEFAULT NULL,
    `address_line1`                TEXT            NOT NULL,
    `address_line2`                TEXT,
    `address_line3`                TEXT,
    `city`                         VARCHAR(64)     NOT NULL,
    `province`                     VARCHAR(2)      NOT NULL,
    `postal_code`                  VARCHAR(10)     NOT NULL,
    `country_code`                 VARCHAR(2)      NOT NULL,
    `is_deleted`                   BOOLEAN         NOT NULL DEFAULT FALSE,
    `is_anonymized`                BOOLEAN         NOT NULL DEFAULT FALSE,
    `created_by`                   INT UNSIGNED             DEFAULT NULL,
    `updated_by`                   INT UNSIGNED             DEFAULT NULL,
    `created_at`                   TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at`                   TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    CONSTRAINT `payment_methods_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payment_methods_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE `customer_payments`
(
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`           BIGINT UNSIGNED NOT NULL,
    `customer_id`          BIGINT UNSIGNED NOT NULL,
    `type_id`              BIGINT UNSIGNED NOT NULL,
    `recurring_payment_id` BIGINT UNSIGNED          DEFAULT NULL,
    `status_id`            BIGINT UNSIGNED NOT NULL,
    `method_id`            BIGINT UNSIGNED NOT NULL,
    `gateway_id`           BIGINT UNSIGNED NOT NULL,
    `invoice_id`           BIGINT UNSIGNED          DEFAULT NULL,
    `currency_code`        VARCHAR(3)      NOT NULL,
    `amount`               INT             NOT NULL,
    `processed_at`         TIMESTAMP(6)    NOT NULL,
    `notification_id`      BIGINT UNSIGNED          DEFAULT NULL,
    `notification_sent_at` TIMESTAMP(6)    NOT NULL,
    `reconciliation_id`    BIGINT UNSIGNED          DEFAULT NULL,
    `created_by`           INT UNSIGNED             DEFAULT NULL,
    `updated_by`           INT UNSIGNED             DEFAULT NULL,
    `created_at`           TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at`           TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    CONSTRAINT `customer_payments_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`) ON DELETE CASCADE,
    CONSTRAINT `customer_payments_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,
    CONSTRAINT `customer_payments_recurring_payment_id_foreign` FOREIGN KEY (`recurring_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `customer_payments_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `payment_statuses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `customer_payments_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE `transactions`
(
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`             BIGINT UNSIGNED NOT NULL,
    `customer_payment_id`    BIGINT UNSIGNED NOT NULL,
    `type_id`                BIGINT UNSIGNED NOT NULL,
    `raw_request_log`        TEXT,
    `raw_response_log`       TEXT,
    `gateway_transaction_id` TEXT            NOT NULL,
    `gateway_response_code`  VARCHAR(32)     NOT NULL,
    `is_anonymized`          BOOLEAN         NOT NULL DEFAULT FALSE,
    `created_by`             INT UNSIGNED             DEFAULT NULL,
    `updated_by`             INT UNSIGNED             DEFAULT NULL,
    `created_at`             TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at`             TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    CONSTRAINT `transactions_customer_payment_id_foreign` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `transactions_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `transaction_types` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;