CREATE TABLE `customers_primary_payment_methods`
(
    `id`          BIGINT UNSIGNED                                                          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED                                                          NOT NULL COMMENT 'relates to customer_id column in payment_methods table, the customer id from PestRoutes',
    `method_id`   BIGINT UNSIGNED                                                          NOT NULL COMMENT 'relates to id column in payment_methods table',
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `updated_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6)                                NOT NULL,
    `updated_at`  TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) NOT NULL,

    CONSTRAINT `customers_primary_payment_methods_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,
    UNIQUE `customers_primary_payment_methods_customer_id_unique` (`customer_id`)
) ENGINE = InnoDB COMMENT 'Table for defining primary customer payment method';