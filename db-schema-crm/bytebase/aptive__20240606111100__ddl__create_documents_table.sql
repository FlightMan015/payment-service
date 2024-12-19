create table if not exists customer.documents
(
    id integer not null primary key,
    area_id integer,
    document_path text,
    description text,
    visible_to_customer boolean,
    visible_to_tech boolean,
    pestroutes_customer_id integer,
    pestroutes_appointment_id integer,
    pestroutes_added_by integer,
    pestroutes_prefix varchar(100),
    pestroutes_date_added timestamp with time zone,
    pestroutes_date_updated timestamp with time zone,
    pestroutes_json jsonb
)