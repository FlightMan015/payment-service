CREATE TYPE field_operations.notification_channels AS ENUM ('email', 'sms');

ALTER TABLE field_operations.notification_recipient_type
    ADD COLUMN notification_channel notification_channels NOT NULL DEFAULT 'email';
