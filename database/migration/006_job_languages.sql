-- database/migrations/006_job_languages.sql
ALTER TABLE job_postings
  ADD COLUMN job_languages VARCHAR(255) NULL AFTER job_location;
