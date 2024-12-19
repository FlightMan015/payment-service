ALTER TABLE `payment_methods`
    ADD `pestroutes_date_updated` DATETIME DEFAULT NULL COMMENT 'The date that the pestroutes payment profile was updated'
    AFTER `pestroutes_payment_date_created`;