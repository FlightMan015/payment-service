CREATE TABLE billing.scheduled_payment_statuses
(
    id          INTEGER                                   NOT NULL PRIMARY KEY,
    name        CHARACTER VARYING(32)                     NOT NULL,
    description CHARACTER VARYING(128),
    created_by  public.urn,
    updated_by  public.urn,
    deleted_by  public.urn,
    created_at  TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    updated_at  TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    deleted_at  TIMESTAMP(6) WITH TIME ZONE
);

CREATE TABLE billing.scheduled_payment_triggers
(
    id          INTEGER                                   NOT NULL PRIMARY KEY,
    name        CHARACTER VARYING(32)                     NOT NULL,
    description CHARACTER VARYING(128),
    created_by  public.urn,
    updated_by  public.urn,
    deleted_by  public.urn,
    created_at  TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    updated_at  TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    deleted_at  TIMESTAMP(6) WITH TIME ZONE
);

CREATE TABLE billing.scheduled_payments
(
    id                uuid                        DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    account_id        uuid                                                  NOT NULL,
    payment_method_id uuid                                                  NOT NULL,
    trigger_id        INTEGER                                               NOT NULL,
    status_id         INTEGER                                               NOT NULL,
    metadata          jsonb                                                 NOT NULL,
    amount            INTEGER                                               NOT NULL,
    payment_id        uuid,
    created_by        public.urn,
    updated_by        public.urn,
    deleted_by        public.urn,
    created_at        TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW()             NOT NULL,
    updated_at        TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW()             NOT NULL,
    deleted_at        TIMESTAMP(6) WITH TIME ZONE,
    CONSTRAINT fk_scheduled_payments_account_id_accounts_id FOREIGN KEY (account_id) REFERENCES customer.accounts (id),
    CONSTRAINT fk_scheduled_payments_payment_method_id_payment_methods_id FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods (id),
    CONSTRAINT fk_scheduled_payments_trigger_id_scheduled_payment_triggers_id FOREIGN KEY (trigger_id) REFERENCES billing.scheduled_payment_triggers (id),
    CONSTRAINT fk_scheduled_payments_status_id_scheduled_payment_statuses_id FOREIGN KEY (status_id) REFERENCES billing.scheduled_payment_statuses (id),
    CONSTRAINT fk_scheduled_payments_payment_id_payments_id FOREIGN KEY (payment_id) REFERENCES billing.payments (id)
);

COMMENT ON COLUMN billing.scheduled_payments.metadata IS 'Metadata for processing the scheduled payment by the trigger (e.g. subscription_id, appointment_id, etc)';