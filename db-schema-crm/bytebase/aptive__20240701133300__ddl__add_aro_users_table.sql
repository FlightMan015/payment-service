CREATE TABLE field_operations.aro_users (
    id INTEGER PRIMARY KEY,
    username character varying(32) NOT NULL,
    password character varying(128) NOT NULL,
    created_by INT,
    updated_by INT,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);
