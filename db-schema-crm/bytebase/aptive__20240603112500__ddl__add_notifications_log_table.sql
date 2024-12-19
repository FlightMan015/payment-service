CREATE TYPE notifications.methods AS ENUM ('SMS', 'Email');

CREATE TABLE notifications.logs
(
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    type CHARACTER VARYING(64) NOT NULL,
    queue_name CHARACTER VARYING(128) NOT NULL,
    method notifications.methods NOT NULL,
    reference_id BIGINT NOT NULL,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    updated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    deleted_at TIMESTAMP(6) WITH TIME ZONE
);

CREATE INDEX idx_notifications_logs_type ON notifications.logs USING btree (type);
CREATE INDEX idx_notifications_logs_reference_id ON notifications.logs USING btree (reference_id);

CREATE TABLE notifications.logs_email
(
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    log_id BIGINT,
    started_at TIMESTAMP(6) WITH TIME ZONE,
    sent_at TIMESTAMP(6) WITH TIME ZONE,
    skipped_at TIMESTAMP(6) WITH TIME ZONE,
    delayed_at TIMESTAMP(6) WITH TIME ZONE,
    failed_at TIMESTAMP(6) WITH TIME ZONE,
    one_time_delayed_at TIMESTAMP(6) WITH TIME ZONE,
    created_by public.urn,
    updated_by public.urn,
    deleted_by public.urn,
    created_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    updated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NOW() NOT NULL,
    deleted_at TIMESTAMP(6) WITH TIME ZONE,
    CONSTRAINT fk_logs_email_log_id_logs_id FOREIGN KEY (log_id) REFERENCES notifications.logs (id)
);

CREATE INDEX idx_notifications_logs_log_id ON notifications.logs_email USING btree (log_id);
