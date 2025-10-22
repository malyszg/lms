-- Create test database for PHPUnit tests
-- This ensures tests use lms_db_test, never lms_db (production)

CREATE DATABASE IF NOT EXISTS lms_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant all privileges to lms_user on test database
GRANT ALL PRIVILEGES ON lms_db_test.* TO 'lms_user'@'%';
FLUSH PRIVILEGES;

-- Log successful creation
SELECT 'Test database lms_db_test created successfully' AS message;
SELECT 'Production database: lms_db' AS info;
SELECT 'Test database: lms_db_test' AS info;













