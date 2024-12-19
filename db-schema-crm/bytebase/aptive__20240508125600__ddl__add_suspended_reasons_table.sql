CREATE TABLE billing.suspend_reasons (
    id integer NOT NULL PRIMARY KEY,
    name character varying(32) NOT NULL,
    description character varying(128) NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(6) with time zone
);
