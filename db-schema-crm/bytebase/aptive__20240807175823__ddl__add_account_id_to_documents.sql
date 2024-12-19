ALTER TABLE customer.documents
    ADD COLUMN account_id uuid NOT NULL,
    ADD CONSTRAINT documents_accounts_id_fk FOREIGN KEY (account_id) REFERENCES customer.accounts(id);
