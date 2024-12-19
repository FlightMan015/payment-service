ALTER TABLE payment_methods
    ADD `cc_last_four` INT UNSIGNED DEFAULT NULL AFTER `cc_expiration_year`;