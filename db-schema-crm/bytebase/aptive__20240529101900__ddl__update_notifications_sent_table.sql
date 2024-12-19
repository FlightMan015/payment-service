CREATE TYPE notification_statuses AS ENUM ('delayed', 'sent', 'skipped');

ALTER TABLE notifications.notifications_sent ADD CONSTRAINT pk_notifications_sent_id PRIMARY KEY (id);
ALTER TABLE notifications.notifications_sent ADD status notification_statuses NULL;
ALTER TABLE notifications.notifications_sent ADD attempt int2 NULL;
ALTER TABLE notifications.notifications_sent ALTER COLUMN notification_datetime DROP NOT NULL;
ALTER TABLE notifications.notifications_sent ALTER COLUMN notification_datetime DROP DEFAULT;