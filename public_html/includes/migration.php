<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/db.php';

const MIGRATIONS_PATH = __DIR__ . '/../migrations/';

function ensure_schema_migrations_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            checksum VARCHAR(64) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function get_migration_files(): array
{
    if (!is_dir(MIGRATIONS_PATH)) {
        return [];
    }

    $files = array_filter(
        scandir(MIGRATIONS_PATH, SCANDIR_SORT_ASCENDING),
        static fn ($file) => is_string($file) && strlen($file) > 4 && substr($file, -4) === '.sql'
    );
    $resolved = [];

    foreach ($files as $file) {
        $path = MIGRATIONS_PATH . $file;
        if (is_file($path)) {
            $resolved[] = $path;
        }
    }

    sort($resolved, SORT_STRING);
    return $resolved;
}

function get_migration_placeholders(): array
{
    static $placeholders = null;

    if ($placeholders !== null) {
        return $placeholders;
    }

    $passwordHash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $placeholders = [
        'DEFAULT_ADMIN_USERNAME' => DEFAULT_ADMIN_USERNAME,
        'DEFAULT_ADMIN_PASSWORD_HASH' => $passwordHash,
        'DEFAULT_ADMIN_ROLE' => DEFAULT_ADMIN_ROLE,
    ];

    return $placeholders;
}

function prepare_migration_sql(string $sql): string
{
    $placeholders = get_migration_placeholders();

    foreach ($placeholders as $key => $value) {
        $sql = str_replace('{{' . $key . '}}', $value, $sql);
    }

    return $sql;
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inString = false;
    $quoteChar = '';
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($inLineComment) {
            if ($char === "\n" || $char === "\r") {
                $inLineComment = false;
                $buffer .= $char;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if ($inString) {
            $buffer .= $char;
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $buffer .= $sql[$i + 1];
                    $i++;
                }
                continue;
            }

            if ($char === $quoteChar) {
                $inString = false;
            }

            continue;
        }

        if ($char === '-' && $next === '-') {
            $inLineComment = true;
            $i++;
            continue;
        }

        if ($char === '#') {
            $inLineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $inString = true;
            $quoteChar = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

function run_migrations(PDO $pdo, array &$logs = []): void
{
    ensure_schema_migrations_table($pdo);
    $files = get_migration_files();

    if (empty($files)) {
        $logs[] = '[INFO] No migration files found';
    } else {
        foreach ($files as $path) {
            $filename = basename($path);
            $checksum = hash_file('sha256', $path);

            $stmt = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE filename = :filename');
            $stmt->execute([':filename' => $filename]);
            $row = $stmt->fetch();

            if ($row !== false) {
                $logs[] = "[SKIP] {$filename}";
                if ($row['checksum'] !== $checksum) {
                    $logs[] = "[WARN] {$filename} checksum changed";
                }
                continue;
            }

            $logs[] = "[RUN ] {$filename}";
            $sql = prepare_migration_sql(file_get_contents($path));
            $statements = split_sql_statements($sql);

            try {
                $pdo->beginTransaction();
                foreach ($statements as $statement) {
                    $pdo->exec($statement);
                }

                $insert = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum) VALUES (:filename, :checksum)');
                $insert->execute([':filename' => $filename, ':checksum' => $checksum]);
                $pdo->commit();
                $logs[] = 'OK';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw new RuntimeException("Failed to apply {$filename}: " . $e->getMessage(), 0, $e);
            }
        }
    }

    ensure_admin_user($pdo, $logs);
}

function ensure_admin_user(PDO $pdo, array &$logs): void
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => DEFAULT_ADMIN_USERNAME]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (:username, :hash, :role, NOW())');
    $insert->execute([
        ':username' => DEFAULT_ADMIN_USERNAME,
        ':hash' => $hash,
        ':role' => DEFAULT_ADMIN_ROLE,
    ]);

    $logs[] = "[ADMIN] Created default admin '" . DEFAULT_ADMIN_USERNAME . "'";
}

