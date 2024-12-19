CREATE TYPE user_types AS ENUM ('sales_rep', 'office_manager');

CREATE TABLE notifications.headshot_paths (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    user_type user_types NOT NULL,
    original_path VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    created_at timestamp(6) DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    updated_at timestamp(6) DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at timestamp(6)
);
