-- Questions bank
CREATE TABLE IF NOT EXISTS micro_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prompt VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO micro_questions (prompt, active) VALUES
  ('What can you add to this company?', 1),
  ('Will your boss be disappointed when you leave?', 1),
  ('Is money the driving force in your career?', 1),
  ('Do you work to live or live to work?', 1),
  ('Is promotion a driving force in your career?', 1),
  ('Are you happy with your existing employer?', 1),
  ('Will your current boss offer you more money?', 1),
  ('Do you think you are a good "Man-manager"?', 1);

-- Job ↔ selected questions (3 rows per job)
CREATE TABLE IF NOT EXISTS job_micro_questions (
  job_posting_id INT NOT NULL,
  question_id INT NOT NULL,
  PRIMARY KEY (job_posting_id, question_id),
  CONSTRAINT fk_jmq_job FOREIGN KEY (job_posting_id) REFERENCES job_postings(job_posting_id) ON DELETE CASCADE,
  CONSTRAINT fk_jmq_q   FOREIGN KEY (question_id)   REFERENCES micro_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Candidate answers per application
CREATE TABLE IF NOT EXISTS application_answers (
  answer_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  question_id INT NOT NULL,
  answer_text TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_ans_app FOREIGN KEY (application_id) REFERENCES applications(applicant_id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_q   FOREIGN KEY (question_id)    REFERENCES micro_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;