<?php

declare(strict_types=1);

use PDO;

$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
$db = $config['db'];

$pdo = new PDO($db['dsn'], $db['user'] ?? null, $db['pass'] ?? null, $db['options'] ?? []);
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dir = $root . '/database/migrations';
$files = glob($dir . '/*.sql');
sort($files); // apply in order: 001_..., 002_...

$applied = [];
$stmt = $pdo->query("SELECT name FROM migrations");
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) $applied[$name] = true;

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "SKIP  $name (already applied)\n";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "WARN  cannot read $name\n";
        continue;
    }

    try {
        $pdo->beginTransaction();

        // naive splitter by ';' not inside delimiters. For simple schema SQL it’s fine.
        $chunks = array_filter(array_map('trim', preg_split('/;\\s*\\n/', $sql)));
        foreach ($chunks as $q) {
            if ($q === '' || str_starts_with(ltrim($q), '--')) continue;
            $pdo->exec($q);
        }

        $ins = $pdo->prepare("INSERT INTO migrations (name) VALUES (:n)");
        $ins->execute([':n' => $name]);
        $pdo->commit();
        echo "APPLY $name\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "FAIL  $name: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' ? true : strpos($haystack, $needle) === 0;
    }
}
