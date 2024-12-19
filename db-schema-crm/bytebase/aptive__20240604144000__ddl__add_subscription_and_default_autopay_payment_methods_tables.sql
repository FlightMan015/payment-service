CREATE TABLE billing.subscription_autopay_payment_methods
(
    id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    subscription_id   uuid                                      NOT NULL,
    payment_method_id uuid                                      NOT NULL,
    created_by        public.urn,
    updated_by        public.urn,
    deleted_by        public.urn,
    created_at        TIMESTAMP(6) WITH TIME ZONE DEFAULT now() NOT NULL,
    updated_at        TIMESTAMP(6) WITH TIME ZONE DEFAULT now() NOT NULL,
    deleted_at        TIMESTAMP(6) WITH TIME ZONE,
    CONSTRAINT fk_subscription_autopay_payment_methods_subscriptions_id FOREIGN KEY (subscription_id) REFERENCES customer.subscriptions (id),
    CONSTRAINT fk_subscription_autopay_payment_methods_payment_methods_id FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods (id)
);

CREATE UNIQUE INDEX uk_subscription_autopay_payment_methods_subscription_id
    ON billing.subscription_autopay_payment_methods USING btree (subscription_id);

CREATE TABLE billing.default_autopay_payment_methods
(
    id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id        uuid                                      NOT NULL,
    payment_method_id uuid                                      NOT NULL,
    created_by        public.urn,
    updated_by        public.urn,
    deleted_by        public.urn,
    created_at        TIMESTAMP(6) WITH TIME ZONE DEFAULT now() NOT NULL,
    updated_at        TIMESTAMP(6) WITH TIME ZONE DEFAULT now() NOT NULL,
    deleted_at        TIMESTAMP(6) WITH TIME ZONE,
    CONSTRAINT fk_default_autopay_payment_methods_accounts_id FOREIGN KEY (account_id) REFERENCES customer.accounts (id),
    CONSTRAINT fk_default_autopay_payment_methods_payment_methods_id FOREIGN KEY (payment_method_id) REFERENCES billing.payment_methods (id)
);

CREATE UNIQUE INDEX uk_default_autopay_payment_methods_account_id
    ON billing.default_autopay_payment_methods USING btree (account_id);