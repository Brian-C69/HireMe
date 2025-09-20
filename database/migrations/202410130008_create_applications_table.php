<?php

use App\Core\Database\Migration;

return new class extends Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_posting_id INTEGER NOT NULL,
            candidate_id INTEGER NOT NULL,
            cover_letter TEXT NULL,
            status VARCHAR(50) DEFAULT "submitted",
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_posting_id) REFERENCES job_postings(id),
            FOREIGN KEY (candidate_id) REFERENCES candidates(id)
        )');
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS applications');
    }
};
