CREATE ROLE paperpulse_test LOGIN PASSWORD 'paperpulse_test';
CREATE DATABASE paperpulse_test OWNER paperpulse_test;
CREATE DATABASE paperpulse_browser_test OWNER paperpulse_test;
REVOKE CONNECT ON DATABASE paperpulse FROM PUBLIC;
GRANT CONNECT ON DATABASE paperpulse TO paperpulse;
