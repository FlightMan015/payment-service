CREATE TABLE `account_updater_attempts`
(
    `id`           BIGINT UNSIGNED                                                          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid`         CHAR(36)                                                                 NOT NULL,
    `requested_by` INT UNSIGNED                                                             NULL,
    `requested_at` TIMESTAMP(6)                                                             NOT NULL,
    `created_by`   INT UNSIGNED                                                             NULL,
    `updated_by`   INT UNSIGNED                                                             NULL,
    `created_at`   TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6)                                NOT NULL,
    `updated_at`   TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) NOT NULL,

    UNIQUE `account_updater_attempts_uuid_unique` (`uuid`)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = UTF8MB4
  DEFAULT COLLATE = UTF8MB4_0900_AI_CI;

CREATE TABLE `account_updater_attempts_methods`
(
    `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `attempt_id`                BIGINT UNSIGNED NOT NULL,
    `method_id`                 BIGINT UNSIGNED NOT NULL,
    `sequence_number`           INT UNSIGNED    NOT NULL,
    `original_token`            TEXT            NOT NULL,
    `original_expiration_month` INT             NOT NULL,
    `original_expiration_year`  INT             NOT NULL,
    `updated_token`             TEXT            NULL,
    `updated_expiration_month`  INT             NULL,
    `updated_expiration_year`   INT             NULL,
    `status`                    TEXT            NULL COMMENT 'TokenEx updating result status. Reference: https://docs.tokenex.com/docs/au-response-messages',
    `created_at`                TIMESTAMP       NULL,
    `updated_at`                TIMESTAMP       NULL,

    CONSTRAINT `account_updater_attempts_methods_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `account_updater_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `account_updater_attempts_methods_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,

    UNIQUE `account_updater_attempts_unique_attempt_sequence_number` (`attempt_id`, `sequence_number`)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = UTF8MB4
  DEFAULT COLLATE = UTF8MB4_0900_AI_CI;

ALTER TABLE `payment_methods`
    ADD `updated_by_account_updater_at` TIMESTAMP(6) NULL;