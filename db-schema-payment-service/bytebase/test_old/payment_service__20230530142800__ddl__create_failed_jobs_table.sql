CREATE TABLE `failed_jobs`
(
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`       CHAR(36)              DEFAULT NULL,
    `connection` TEXT         NOT NULL,
    `queue`      TEXT         NOT NULL,
    `payload`    TEXT         NOT NULL,
    `exception`  TEXT         NOT NULL,
    `failed_at`  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE = InnoDB;