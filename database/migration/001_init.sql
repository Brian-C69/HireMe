-- database/migrations/001_init.sql
-- HireMe initial schema (MySQL/MariaDB)
-- Engine/charset for proper Unicode + FK support
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================================
-- 1) Candidates (Job Seekers)
-- ============================================================================
CREATE TABLE IF NOT EXISTS candidates (
  candidate_id           INT AUTO_INCREMENT PRIMARY KEY,
  full_name              VARCHAR(255) NOT NULL,
  email                  VARCHAR(255) NOT NULL UNIQUE,
  password_hash          VARCHAR(255) NOT NULL,                  -- for auth
  phone_number           VARCHAR(20),
  date_of_birth          DATE,
  address                VARCHAR(500),
  city                   VARCHAR(100),
  state                  VARCHAR(100),
  postal_code            VARCHAR(20),
  country                VARCHAR(100),
  profile_picture_url    VARCHAR(500),
  resume_url             VARCHAR(500),
  verified_status        BOOLEAN NOT NULL DEFAULT FALSE,
  verification_date      DATETIME DEFAULT NULL,
  verification_doc_type  VARCHAR(50)  DEFAULT NULL,
  verification_doc_url   VARCHAR(500) DEFAULT NULL,
  premium_badge          BOOLEAN NOT NULL DEFAULT FALSE,
  premium_badge_date     DATETIME DEFAULT NULL,
  skills                 TEXT,
  experience_years       INT,
  education_level        VARCHAR(100),
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_candidates_email (email),
  INDEX idx_candidates_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2) Employers
-- ============================================================================
CREATE TABLE IF NOT EXISTS employers (
  employer_id           INT AUTO_INCREMENT PRIMARY KEY,
  company_name          VARCHAR(255) NOT NULL,
  email                 VARCHAR(255) NOT NULL UNIQUE,
  password_hash         VARCHAR(255) NOT NULL,
  industry              VARCHAR(255),
  location              VARCHAR(255),
  contact_person_name   VARCHAR(255),
  contact_number        VARCHAR(50),
  company_logo          TEXT,
  company_description   TEXT,
  credits_balance       INT NOT NULL DEFAULT 0,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employers_email (email),
  INDEX idx_employers_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3) Recruiters
-- ============================================================================
CREATE TABLE IF NOT EXISTS recruiters (
  recruiter_id     INT AUTO_INCREMENT PRIMARY KEY,
  full_name        VARCHAR(255) NOT NULL,
  email            VARCHAR(255) NOT NULL UNIQUE,
  password_hash    VARCHAR(255) NOT NULL,
  agency_name      VARCHAR(255),
  contact_number   VARCHAR(50),
  location         VARCHAR(255),
  credits_balance  INT NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_recruiters_email (email),
  INDEX idx_recruiters_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4) Job Postings
-- ============================================================================
CREATE TABLE IF NOT EXISTS job_postings (
  job_posting_id       INT AUTO_INCREMENT PRIMARY KEY,
  company_id           INT NOT NULL,               -- FK -> employers.employer_id
  recruiter_id         INT DEFAULT NULL,           -- FK -> recruiters.recruiter_id (nullable)
  job_title            VARCHAR(255) NOT NULL,
  job_description      TEXT NOT NULL,
  job_requirements     TEXT,
  job_location         VARCHAR(255),
  employment_type      VARCHAR(50),
  salary_range_min     DECIMAL(10,2) DEFAULT NULL,
  salary_range_max     DECIMAL(10,2) DEFAULT NULL,
  application_deadline DATE,
  date_posted          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status               VARCHAR(50) NOT NULL DEFAULT 'Open',
  number_of_positions  INT NOT NULL DEFAULT 1,
  required_experience  VARCHAR(100),
  education_level      VARCHAR(100),
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_job_company   FOREIGN KEY (company_id)  REFERENCES employers(employer_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_job_recruiter FOREIGN KEY (recruiter_id) REFERENCES recruiters(recruiter_id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_job_postings_company (company_id),
  INDEX idx_job_postings_recruiter (recruiter_id),
  INDEX idx_job_postings_status (status),
  FULLTEXT INDEX ft_job_postings_search (job_title, job_description, job_requirements) -- optional fulltext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5) Resumes
-- ============================================================================
CREATE TABLE IF NOT EXISTS resumes (
  resume_id            INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id         INT NOT NULL,               -- FK -> candidates.candidate_id
  resume_url           TEXT NOT NULL,
  generated_by_system  BOOLEAN NOT NULL DEFAULT FALSE,
  summary              TEXT,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_resume_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_resumes_candidate (candidate_id),
  INDEX idx_resumes_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6) Applications (Applicants)
-- ============================================================================
CREATE TABLE IF NOT EXISTS applications (
  applicant_id       INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id       INT NOT NULL,                 -- FK -> candidates.candidate_id
  job_posting_id     INT NOT NULL,                 -- FK -> job_postings.job_posting_id
  application_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  application_status VARCHAR(50) NOT NULL DEFAULT 'Applied',
  resume_url         VARCHAR(500),
  cover_letter       TEXT,
  notes              TEXT,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_application_candidate FOREIGN KEY (candidate_id)   REFERENCES candidates(candidate_id)     ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_application_job      FOREIGN KEY (job_posting_id) REFERENCES job_postings(job_posting_id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_applications_candidate (candidate_id),
  INDEX idx_applications_job (job_posting_id),
  INDEX idx_applications_status (application_status),
  INDEX idx_applications_date (application_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7) Payments
-- ============================================================================
CREATE TABLE IF NOT EXISTS payments (
  payment_id         INT AUTO_INCREMENT PRIMARY KEY,
  user_type          ENUM('Candidate','Employer','Recruiter') NOT NULL,
  user_id            INT NOT NULL,
  amount             DECIMAL(10,2) NOT NULL,
  purpose            ENUM('Premium Badge','Resume Credits','Subscription') NOT NULL,
  payment_method     VARCHAR(100) NOT NULL, -- e.g., FPX, Credit Card, eWallet
  transaction_status ENUM('Success','Failed','Pending') NOT NULL,
  transaction_id     VARCHAR(255),          -- external gateway reference
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payments_user (user_type, user_id),
  INDEX idx_payments_created_at (created_at),
  INDEX idx_payments_status (transaction_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8) Billing
-- ============================================================================
CREATE TABLE IF NOT EXISTS billing (
  billing_id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  user_type         VARCHAR(50) NOT NULL,  -- 'Candidate' | 'Employer' | 'Recruiter'
  transaction_type  VARCHAR(100) NOT NULL, -- e.g., Premium Badge Purchase, Credit Purchase
  amount            DECIMAL(10,2) NOT NULL,
  payment_method    VARCHAR(50) NOT NULL,
  transaction_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status            VARCHAR(50) NOT NULL DEFAULT 'Pending',
  reference_number  VARCHAR(255),
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_billing_user (user_type, user_id),
  INDEX idx_billing_date (transaction_date),
  INDEX idx_billing_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9) Admins
-- ============================================================================
CREATE TABLE IF NOT EXISTS admins (
  admin_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(100) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('SuperAdmin','Support','Verifier','Finance') NOT NULL DEFAULT 'Support',
  permissions    JSON NULL,                         -- MariaDB <10.2: change to TEXT
  profile_photo  TEXT,
  last_login_at  DATETIME,
  status         ENUM('Active','Suspended','Deleted') NOT NULL DEFAULT 'Active',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admins_email (email),
  INDEX idx_admins_role (role),
  INDEX idx_admins_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10) Contact Form (public, non-account)
-- ============================================================================
CREATE TABLE IF NOT EXISTS contact_form (
  contact_id        INT AUTO_INCREMENT PRIMARY KEY,
  contact_firstname VARCHAR(255) NOT NULL,
  contact_lastname  VARCHAR(255) NOT NULL,
  contact_email     VARCHAR(255) NOT NULL,
  contact_message   TEXT NOT NULL,
  contact_ip_address VARCHAR(255) NOT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_email (contact_email),
  INDEX idx_contact_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
