ALTER TABLE aptive.billing.payments
    RENAME COLUMN pestroutes_payment_link TO pestroutes_metadata;

ALTER TABLE aptive.billing.payment_methods
    RENAME COLUMN pestroutes_payment_method_link TO pestroutes_metadata;

ALTER TABLE aptive.customer.accounts
    RENAME COLUMN pestroutes_account_link TO pestroutes_metadata;
