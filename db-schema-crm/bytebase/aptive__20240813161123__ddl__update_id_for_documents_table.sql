-- Update customer.documents
ALTER TABLE customer.documents RENAME COLUMN id TO external_ref_id;

ALTER TABLE customer.documents DROP CONSTRAINT documents_pkey;

ALTER TABLE customer.documents ADD COLUMN id UUID DEFAULT gen_random_uuid() NOT NULL;

ALTER TABLE customer.documents ADD CONSTRAINT documents_pkey PRIMARY KEY (id);

ALTER TABLE customer.documents ADD CONSTRAINT documents_external_ref_id_key UNIQUE (external_ref_id);

-- Update audit.customer__documents
DROP INDEX IF EXISTS audit.idx_customer__documents_table_id;

ALTER TABLE audit.customer__documents ALTER COLUMN table_id TYPE UUID USING md5(table_id::text)::uuid;

CREATE INDEX idx_customer__documents_table_id ON audit.customer__documents USING btree (table_id);
