ALTER TABLE billing.payments
    ADD COLUMN is_scheduled_payment bool NOT NULL DEFAULT FALSE;
COMMENT ON COLUMN billing.payments.is_scheduled_payment IS 'Flag to determine if the payment was a scheduled payment';