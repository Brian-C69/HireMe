-- database/migrations/003_login_attempts.sql
-- Tracks login failures (email + IP) and when they occurred.
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt (email, ip_address),
  INDEX idx_last_attempt (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
