<?php

use App\Core\Database\Migration;

return new class extends Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS resumes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            candidate_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NULL,
            content TEXT NULL,
            is_generated BOOLEAN DEFAULT 0,
            visibility VARCHAR(50) DEFAULT "private",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id)
        )');
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS resumes');
    }
};
