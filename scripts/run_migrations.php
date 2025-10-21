<?php
declare(strict_types=1);

/**
 * scripts/run_migrations.php
 * - Applies .sql files in /db/migrations once (tracked in schema_migrations)
 * - Uses DB creds from App/Configuration/.env.local.php ('standard' profile)
 */

$profile = getenv('DB_PROFILE') ?: 'standard';
$envPath = __DIR__ . '/../App/Configuration/.env.local.php';
$dir     = __DIR__ . '/../db/migrations';

if (!is_file($envPath)) {
    throw new RuntimeException("Env file not found: {$envPath}");
}
$cfg = require $envPath;
if (!isset($cfg['databases'][$profile])) {
    throw new RuntimeException("DB profile not found: {$profile}");
}
$db = $cfg['databases'][$profile];

$host      = (string)($db['DB_HOST']      ?? '127.0.0.1');
$port      = (string)($db['DB_PORT']      ?? '3306');
$name      = (string)($db['DB_DATABASE']  ?? 'test');
$user      = (string)($db['DB_USERNAME']  ?? 'root');
$pass      = (string)($db['DB_PASSWORD']  ?? '');
$charset   = (string)($db['DB_CHARSET']   ?? 'utf8mb4');
$collation = (string)($db['DB_COLLATION'] ?? 'utf8mb4_unicode_ci');

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
$pdo->exec(
  "CREATE TABLE IF NOT EXISTS schema_migrations (
     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     filename VARCHAR(255) NOT NULL,
     applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     UNIQUE KEY uk_schema_migrations_filename (filename)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
     COLLATE=utf8mb4_unicode_ci"
);

$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $base = basename($file);

    $stmt = $pdo->prepare(
        "SELECT 1 FROM schema_migrations
         WHERE filename = :f LIMIT 1"
    );
    $stmt->execute([':f' => $base]);
    if ($stmt->fetch()) {
        echo "-- Skipping {$base} (already applied)\n";
        continue;
    }

    echo "==> Applying {$base}\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Failed to read {$file}");
    }

    try {
        // MySQL DDL autocommits; still try to be neat for multi-statement SQL
        $pdo->exec($sql);

        $ins = $pdo->prepare(
            "INSERT INTO schema_migrations (filename) VALUES (:f)"
        );
        $ins->execute([':f' => $base]);
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR in {$base}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "All migrations up to date.\n";
