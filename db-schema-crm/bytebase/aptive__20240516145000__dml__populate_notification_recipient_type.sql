-- Associating migrated records with optimization_skipped type in notification_recipient_type table

INSERT INTO field_operations.notification_types (type) VALUES ('optimization_skipped');
INSERT INTO field_operations.notification_recipient_type (type_id, notification_recipient_id)
SELECT nt.id, nr.id
FROM field_operations.notification_types nt
JOIN field_operations.notification_recipients nr ON nr.email IS NULL
WHERE nt.type = 'optimization_skipped';
