ALTER TABLE
    billing.suspend_reasons
ALTER COLUMN
    id
ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME billing.suspend_reasons_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
),
ADD CONSTRAINT uk_suspend_reasons_name UNIQUE (name);

CREATE INDEX idx_suspend_reasons_created_at ON billing.suspend_reasons USING btree (created_at);

CREATE INDEX idx_suspend_reasons_created_by ON billing.suspend_reasons USING btree (created_by);

CREATE INDEX idx_suspend_reasons_deleted_at ON billing.suspend_reasons USING btree (deleted_at);

CREATE INDEX idx_suspend_reasons_deleted_by ON billing.suspend_reasons USING btree (deleted_by);

CREATE INDEX idx_suspend_reasons_updated_at ON billing.suspend_reasons USING btree (updated_at);

CREATE INDEX idx_suspend_reasons_updated_by ON billing.suspend_reasons USING btree (updated_by);
