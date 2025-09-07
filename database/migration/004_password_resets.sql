CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  user_type ENUM('Candidate','Employer','Recruiter') NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,        -- sha256(token)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  INDEX idx_pr_email (email),
  INDEX idx_pr_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;