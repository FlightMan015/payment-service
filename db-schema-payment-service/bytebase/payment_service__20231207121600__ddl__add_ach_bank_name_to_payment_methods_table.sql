ALTER TABLE `payment_methods`
    ADD `ach_bank_name` VARCHAR(128) NULL AFTER `ach_account_type_id`;