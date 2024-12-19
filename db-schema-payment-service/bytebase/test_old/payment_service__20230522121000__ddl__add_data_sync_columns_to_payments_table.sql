ALTER TABLE payment_methods
    ADD `ext_reference_id`             INT UNSIGNED DEFAULT NULL COMMENT 'The pestroutes payment_profile_id',
    ADD `pestroutes_payment_hold_date` DATETIME     DEFAULT NULL COMMENT 'The pestroutes payment hold date for the payment profile',
    ADD `pestroutes_date_created`      DATETIME     DEFAULT NULL COMMENT 'The date that the pestroutes payment profile was created',
    MODIFY `cc_token` TEXT COMMENT 'Equivalant of the pestroutes merchant_id (which is a credit card token)',
    ADD CONSTRAINT unique_ext_reference_id_per_company UNIQUE (ext_reference_id, company_id);