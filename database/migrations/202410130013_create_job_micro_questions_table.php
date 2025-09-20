<?php

use App\Core\Database\Migration;

return new class extends Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS job_micro_questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_posting_id INTEGER NOT NULL,
            micro_question_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_posting_id) REFERENCES job_postings(id),
            FOREIGN KEY (micro_question_id) REFERENCES micro_questions(id)
        )');
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS job_micro_questions');
    }
};
