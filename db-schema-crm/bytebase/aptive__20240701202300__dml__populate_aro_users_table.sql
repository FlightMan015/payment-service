-- Populate aro_users table for authentication

INSERT INTO field_operations.aro_users (id, username, password, created_at, updated_at)
VALUES
(1, 'aro_admin', '$2a$10$tJqQWWDBeIGkxNCmf0HkjeJi79rnza5Cka2Wf8Lat0qre.HZd8O46', NOW(), NOW()),
(2, 'aro_user', '$2a$10$SRkAKqGjYVlts4XMM4tS6uWFJsdlYSe5MfQBKIhuos52OmdCnRArW', NOW(), NOW());
