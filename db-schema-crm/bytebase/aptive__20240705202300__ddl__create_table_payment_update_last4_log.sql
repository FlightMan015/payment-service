CREATE TABLE aptive.billing.payment_update_last4_log
(
    id             BIGINT GENERATED BY DEFAULT AS IDENTITY,
    batch_start_id INT                                                   NOT NULL,
    error_message  TEXT                                                  NOT NULL,
    step           VARCHAR(50)                                           NOT NULL,
    created_at     TIMESTAMP(6) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id)
);

ALTER TABLE billing.payment_update_last4_log
DISABLE TRIGGER payment_update_last4_log_audit_record_trigger;

CREATE TABLE billing.new_payments_with_last_four (
    id UUID NOT NULL,
    external_ref_id INT NOT NULL,
    payment_method_id UUID,
    pestroutes_customer_id INT,
    original_payment_id UUID,
    root_payment_id UUID,
    last_four varchar(4),
    PRIMARY KEY (id)
);

ALTER TABLE billing.new_payments_with_last_four
DISABLE TRIGGER new_payments_with_last_four_audit_record_trigger;

CREATE TABLE billing.distinct_payment_methods (
    id UUID NOT NULL,
    external_ref_id INT NOT NULL,
    pestroutes_customer_id INT,
    last_four varchar(4),
    PRIMARY KEY (id)
);

ALTER TABLE billing.distinct_payment_methods
DISABLE TRIGGER distinct_payment_methods_audit_record_trigger;
