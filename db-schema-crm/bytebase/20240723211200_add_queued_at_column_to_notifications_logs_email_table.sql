ALTER TABLE notifications.logs_email
    DROP COLUMN IF EXISTS queued_at;

ALTER TABLE notifications.logs_email
    ADD COLUMN queued_at TIMESTAMP(6) WITH TIME ZONE NULL;

COMMENT ON COLUMN notifications.logs_email.queued_at IS 'Queued At';
