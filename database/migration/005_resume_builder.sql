-- database/migrations/005_resume_builder.sql
-- Adds resume fields and normalized child tables.

ALTER TABLE candidates
  ADD COLUMN expected_salary DECIMAL(10,2) NULL AFTER phone_number,
  ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER expected_salary,
  ADD COLUMN location VARCHAR(255) NULL AFTER linkedin_url,
  ADD COLUMN notice_period VARCHAR(50) NULL AFTER location;

CREATE TABLE IF NOT EXISTS candidate_experiences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  company VARCHAR(255) NOT NULL,
  job_title VARCHAR(255) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ce_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
  INDEX idx_ce_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidate_skills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0..100
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cs_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
  INDEX idx_cs_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidate_languages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  language VARCHAR(50) NOT NULL,
  spoken_level ENUM('Basic','Intermediate','Fluent','Native') NOT NULL DEFAULT 'Basic',
  written_level ENUM('Basic','Intermediate','Fluent','Native') NOT NULL DEFAULT 'Basic',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cl_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
  INDEX idx_cl_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidate_education (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  qualification ENUM('SPM','Diploma','Degree','Master','Prof Quali') NOT NULL,
  institution VARCHAR(255),
  field VARCHAR(255),
  graduation_year YEAR NULL,
  details TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ced_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
  INDEX idx_ced_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
