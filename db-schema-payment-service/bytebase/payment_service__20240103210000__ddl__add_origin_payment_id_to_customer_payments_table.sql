ALTER TABLE `customer_payments`
    ADD `original_payment_id` int null COMMENT 'Used for refunding payment (which payment is refunded)' after `invoice_id`;