ALTER TABLE `payment_methods`
    ADD `email`       VARCHAR(256) NOT NULL AFTER `address_line3`,
    ADD `description` TEXT         NULL AFTER `name_on_account_last`