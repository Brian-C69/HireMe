<?php

use App\Core\Database\Migration;

return new class extends Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS job_languages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_posting_id INTEGER NOT NULL,
            language VARCHAR(100) NOT NULL,
            proficiency VARCHAR(50) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_posting_id) REFERENCES job_postings(id)
        )');
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS job_languages');
    }
};
