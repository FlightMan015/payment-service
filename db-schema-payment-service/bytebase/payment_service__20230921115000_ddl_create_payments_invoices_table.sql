CREATE TABLE `payments_invoices`
(
    `id`                       BIGINT UNSIGNED                                                          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payment_id`               BIGINT UNSIGNED                                                          NOT NULL,
    `applied_at`               TIMESTAMP(6)                                                             NOT NULL,
    `applied_amount`           INT                                                                      NOT NULL,
    `pestroutes_invoice_date`  TIMESTAMP(6)                                                             NULL COMMENT 'Temporarily field. Will be deprecated in the future',
    `pestroutes_invoice_id`    BIGINT UNSIGNED                                                          NULL COMMENT 'Temporarily field. Will be deprecated in the future',
    `pestroutes_invoice_total` INT                                                                      NULL COMMENT 'Temporarily field. Will be deprecated in the future',
    `created_by`               INT UNSIGNED                                                             NULL,
    `updated_by`               INT UNSIGNED                                                             NULL,
    `created_at`               TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6)                                NOT NULL,
    `updated_at`               TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) NOT NULL,

    CONSTRAINT `payments_invoices_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=UTF8MB4 DEFAULT COLLATE=UTF8MB4_0900_AI_CI;
