ALTER TABLE billing.payments
    ADD COLUMN IF NOT EXISTS pestroutes_created_by_crm boolean DEFAULT true NOT NULL;
