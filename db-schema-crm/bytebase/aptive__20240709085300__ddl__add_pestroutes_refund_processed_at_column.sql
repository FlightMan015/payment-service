ALTER TABLE billing.payments
    ADD COLUMN IF NOT EXISTS pestroutes_refund_processed_at TIMESTAMPTZ(6) DEFAULT NULL;
COMMENT ON COLUMN billing.payments.pestroutes_refund_processed_at IS 'Timestamp when the eligible refund that was created in PestRoutes was processed by Payment Service';