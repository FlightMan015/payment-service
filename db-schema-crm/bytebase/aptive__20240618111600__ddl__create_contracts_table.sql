CREATE TABLE IF NOT EXISTS customer.contracts
(
    id                         INTEGER                                   NOT NULL PRIMARY KEY,
    document_path              CHARACTER VARYING(255)                    NOT NULL,
    description                CHARACTER VARYING(255),
    state                      CHARACTER VARYING(32)                     NOT NULL,
    pestroutes_customer_id     INTEGER,
    pestroutes_subscription_id INTEGER,
    pestroutes_date_signed     TIMESTAMP WITH TIME ZONE,
    pestroutes_date_added      TIMESTAMP WITH TIME ZONE,
    pestroutes_date_updated    TIMESTAMP WITH TIME ZONE,
    pestroutes_json            jsonb,
    created_by                 public.urn,
    updated_by                 public.urn,
    deleted_by                 public.urn,
    created_at                 TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    updated_at                 TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    deleted_at                 TIMESTAMP(6) WITH TIME ZONE
);