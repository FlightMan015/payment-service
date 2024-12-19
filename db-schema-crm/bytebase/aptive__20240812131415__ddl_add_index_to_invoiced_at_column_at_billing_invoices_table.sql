CREATE INDEX IF NOT EXISTS idx_invoices_invoiced_at 
ON billing.invoices 
USING btree (invoiced_at);