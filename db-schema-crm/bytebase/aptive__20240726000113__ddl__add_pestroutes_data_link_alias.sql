ALTER TABLE customer.accounts
    ADD COLUMN pestroutes_data_link_alias VARCHAR(50) DEFAULT NULL;
CREATE UNIQUE INDEX customer_accounts_data_link_alias_unique ON customer.accounts (pestroutes_data_link_alias) WHERE deleted_at IS NULL;

ALTER TABLE billing.payments
    ADD COLUMN pestroutes_data_link_alias VARCHAR(50) DEFAULT NULL;
CREATE UNIQUE INDEX billing_payments_data_link_alias_unique ON billing.payments (pestroutes_data_link_alias) WHERE deleted_at IS NULL;

ALTER TABLE billing.payment_methods
    ADD COLUMN pestroutes_data_link_alias VARCHAR(50) DEFAULT NULL;
CREATE UNIQUE INDEX billing_payment_methods_data_link_alias_unique ON billing.payment_methods (pestroutes_data_link_alias) WHERE deleted_at IS NULL;
