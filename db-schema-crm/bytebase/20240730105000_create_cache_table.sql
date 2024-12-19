CREATE TABLE IF NOT EXISTS notifications.cache
(
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(256) NOT NULL,
    status CHAR(1) NOT NULL DEFAULT 'A',
    content TEXT NULL DEFAULT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    updated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    deleted_at TIMESTAMP(6) WITH TIME ZONE
);

CREATE UNIQUE INDEX uk_notifications_cache_name ON notifications.cache USING btree (name);
