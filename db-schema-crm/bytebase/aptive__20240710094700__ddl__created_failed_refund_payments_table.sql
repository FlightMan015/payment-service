CREATE TABLE billing.failed_refund_payments
(
    id                  uuid                        DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    original_payment_id uuid                                                  NOT NULL,
    refund_payment_id   uuid                                                  NOT NULL,
    account_id          uuid                                                  NOT NULL,
    amount              INTEGER                                               NOT NULL,
    failed_at           TIMESTAMP(6) WITH TIME ZONE                           NOT NULL,
    failure_reason      CHARACTER VARYING(128)                                NOT NULL,
    report_sent_at      TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,

    created_by          public.urn,
    updated_by          public.urn,
    deleted_by          public.urn,
    created_at          TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW()             NOT NULL,
    updated_at          TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW()             NOT NULL,
    deleted_at          TIMESTAMP(6) WITH TIME ZONE,

    CONSTRAINT fk_failed_refund_payments_original_payment_id_payments_id FOREIGN KEY (original_payment_id) REFERENCES billing.payments (id),
    CONSTRAINT fk_failed_refund_payments_refund_payment_id_payments_id FOREIGN KEY (refund_payment_id) REFERENCES billing.payments (id),
    CONSTRAINT fk_failed_refund_payments_account_id_accounts_id FOREIGN KEY (account_id) REFERENCES customer.accounts (id)
);

COMMENT ON COLUMN billing.failed_refund_payments.original_payment_id IS 'The identifier of the original payment that failed to be refunded';
COMMENT ON COLUMN billing.failed_refund_payments.refund_payment_id IS 'The identifier of the refund payment record that failed';
COMMENT ON COLUMN billing.failed_refund_payments.amount IS 'The requested amount to be refunded';
COMMENT ON COLUMN billing.failed_refund_payments.failed_at IS 'The date and time when the refund failed';
COMMENT ON COLUMN billing.failed_refund_payments.failure_reason IS 'The reason why the refund failed (from Gateway)';
COMMENT ON COLUMN billing.failed_refund_payments.report_sent_at IS 'If it was included in a report, the date and time when it was sent';