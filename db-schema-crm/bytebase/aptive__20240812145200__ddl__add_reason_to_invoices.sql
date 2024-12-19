CREATE TYPE billing.invoice_reasons AS ENUM (
    'monthly_billing',
    'scheduled_service',
    'initial_service',
    'cancellation_idr',
    'add_on_service_initial'
);

ALTER TABLE billing.invoices
    ADD COLUMN reason billing.invoice_reasons DEFAULT NULL;
