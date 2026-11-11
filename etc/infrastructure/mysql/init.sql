-- Runs once, when the MySQL volume is created. The app user also owns the test database
-- (Doctrine adds the _test suffix in the test environment).
CREATE DATABASE IF NOT EXISTS `app_test`;
GRANT ALL PRIVILEGES ON `app_test`.* TO 'app'@'%';
