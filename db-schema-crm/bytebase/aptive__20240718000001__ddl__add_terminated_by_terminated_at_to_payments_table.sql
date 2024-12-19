ALTER TABLE billing.payments
    ADD COLUMN terminated_by public.urn NULL,
    ADD COLUMN terminated_at timestamp(6) WITH TIME ZONE NULL;

COMMENT ON COLUMN billing.payments.terminated_by IS 'Stores the user who terminated the payment';
COMMENT ON COLUMN billing.payments.terminated_at IS 'Stores the time when the payment was terminated';
