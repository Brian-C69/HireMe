CREATE TABLE IF NOT EXISTS resume_unlocks (
  unlock_id     INT AUTO_INCREMENT PRIMARY KEY,
  viewer_type   ENUM('Employer','Recruiter') NOT NULL,
  viewer_id     INT NOT NULL,
  candidate_id  INT NOT NULL,
  created_at    DATETIME NOT NULL,
  UNIQUE KEY uq_viewer_candidate (viewer_type, viewer_id, candidate_id),
  CONSTRAINT fk_unlock_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;