ALTER TABLE notifications.headshot_paths ALTER COLUMN meta TYPE jsonb USING meta::jsonb::jsonb;
