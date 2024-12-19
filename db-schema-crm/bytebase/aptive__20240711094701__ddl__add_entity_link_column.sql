ALTER TABLE aptive.billing.payments
    ADD COLUMN pestroutes_payment_link JSONB DEFAULT NULL;

ALTER TABLE aptive.billing.payment_methods
    ADD COLUMN pestroutes_payment_method_link JSONB DEFAULT NULL;

ALTER TABLE aptive.customer.accounts
    ADD COLUMN pestroutes_account_link JSONB DEFAULT NULL;
