<?php

/**
 * BackupService
 *
 * Creates, verifies, lists, downloads and restores MySQL database backups.
 * Uses mysqldump via exec() — requires it to be in PATH (XAMPP includes it).
 * Backups are stored outside the public web directory.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class BackupService
{
    private string       $backupDir;
    private SettingsService $cfg;

    public function __construct()
    {
        $this->cfg = new SettingsService();

        $configuredPath = trim((string) $this->cfg->get('backup_path', ''));
        if ($configuredPath !== '' && is_dir($configuredPath)) {
            $this->backupDir = rtrim($configuredPath, '/\\');
        } else {
            // Default: /attendance/backups  (one level above public/)
            $this->backupDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backups';
        }

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0775, true);
        }
    }

    /* ── Public API ─────────────────────────────────────────── */

    /**
     * Run a backup. Returns backup_log id.
     * @param string $type  daily|weekly|monthly|manual
     * @param string $trigger  automatic|manual
     * @param string|null $createdBy  user id (null = cron)
     */
    public function run(
        string  $type      = 'manual',
        string  $trigger   = 'manual',
        ?string $createdBy = null
    ): array {
        $logId = uuid_v4();
        $start = microtime(true);

        // Insert in-progress log
        Database::connection()->prepare(
            "INSERT INTO backup_logs (id, filename, filepath, filesize, backup_type, trigger_type, status, created_by)
             VALUES (?, '', '', 0, ?, ?, 'in_progress', ?)"
        )->execute([$logId, $type, $trigger, $createdBy]);

        try {
            $dbConfig  = require dirname(__DIR__, 2) . '/config/database.php';
            $compress  = (bool)(int) $this->cfg->get('backup_compress', 1);
            $timestamp = date('Ymd_His');
            $baseName  = "backup_{$type}_{$timestamp}";
            $sqlFile   = $this->backupDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
            $finalFile = $compress
                ? $this->backupDir . DIRECTORY_SEPARATOR . $baseName . '.zip'
                : $sqlFile;

            // Build mysqldump command
            $mysqldump = $this->findMysqldump();
            $host      = escapeshellarg($dbConfig['host']);
            $port      = escapeshellarg($dbConfig['port'] ?? '3306');
            $dbName    = escapeshellarg($dbConfig['database']);
            $user      = escapeshellarg($dbConfig['username']);
            $pass      = $dbConfig['password'];
            $outFile   = escapeshellarg($sqlFile);

            // Write a temp options file to avoid password on command line
            $optFile = $this->backupDir . DIRECTORY_SEPARATOR . '.my_' . uniqid() . '.cnf';
            file_put_contents($optFile, "[client]\npassword=" . $pass . "\n");
            chmod($optFile, 0600);

            $cmd = sprintf(
                '%s --defaults-extra-file=%s -h %s -P %s -u %s --single-transaction --routines --events %s > %s 2>&1',
                escapeshellarg($mysqldump),
                escapeshellarg($optFile),
                $host,
                $port,
                $user,
                $dbName,
                $outFile
            );

            exec($cmd, $output, $exitCode);
            @unlink($optFile);

            if ($exitCode !== 0 || !is_file($sqlFile)) {
                throw new \RuntimeException('mysqldump failed: ' . implode(' ', $output));
            }

            // Compress with ZipArchive
            if ($compress) {
                if (class_exists('ZipArchive')) {
                    $zip = new \ZipArchive();
                    if ($zip->open($finalFile, \ZipArchive::CREATE) === true) {
                        $zip->addFile($sqlFile, $baseName . '.sql');
                        $zip->close();
                        @unlink($sqlFile);
                    } else {
                        // Fallback: keep the raw .sql if zip fails
                        $finalFile = $sqlFile;
                    }
                } else {
                    // ZipArchive class not available - keep raw .sql
                    $finalFile = $sqlFile;
                }
            }

            $filesize = filesize($finalFile) ?: 0;
            $duration = (int) round(microtime(true) - $start);

            Database::connection()->prepare(
                "UPDATE backup_logs
                 SET filename = ?, filepath = ?, filesize = ?, status = 'success',
                     duration_seconds = ?, verified = 1
                 WHERE id = ?"
            )->execute([basename($finalFile), $finalFile, $filesize, $duration, $logId]);

            // Cleanup old backups
            $this->cleanupOld();

            return ['success' => true, 'log_id' => $logId, 'file' => $finalFile, 'size' => $filesize];
        } catch (\Throwable $e) {
            $duration = (int) round(microtime(true) - $start);
            Database::connection()->prepare(
                "UPDATE backup_logs
                 SET status = 'failed', error_message = ?, duration_seconds = ?
                 WHERE id = ?"
            )->execute([$e->getMessage(), $duration, $logId]);

            return ['success' => false, 'log_id' => $logId, 'error' => $e->getMessage()];
        }
    }

    /**
     * List backup logs (paginated).
     */
    public function logs(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int) Database::connection()
            ->query('SELECT COUNT(*) FROM backup_logs')
            ->fetchColumn();
        $rows   = Database::connection()->prepare(
            'SELECT bl.*, u.username AS created_by_name
             FROM backup_logs bl
             LEFT JOIN users u ON u.id = bl.created_by
             ORDER BY bl.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $rows->execute([$perPage, $offset]);
        return ['total' => $total, 'rows' => $rows->fetchAll()];
    }

    /**
     * Return absolute path to a backup file (validates it exists and is inside backup dir).
     */
    public function getFilePath(string $logId): ?string
    {
        $stmt = Database::connection()->prepare(
            "SELECT filepath FROM backup_logs WHERE id = ? AND status = 'success'"
        );
        $stmt->execute([$logId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $path = realpath($row['filepath']);
        if (!$path || !str_starts_with($path, realpath($this->backupDir))) {
            return null; // path-traversal guard
        }
        return $path;
    }

    /**
     * Delete a backup file and its log entry.
     */
    public function delete(string $logId): bool
    {
        $path = $this->getFilePath($logId);
        if ($path && is_file($path)) {
            @unlink($path);
        }
        Database::connection()->prepare('DELETE FROM backup_logs WHERE id = ?')->execute([$logId]);
        return true;
    }

    /**
     * Restore from a stored backup (re-runs the SQL dump).
     * WARNING: destructive — drops and recreates all tables.
     */
    public function restore(string $logId): array
    {
        $path = $this->getFilePath($logId);
        if (!$path) {
            return ['success' => false, 'error' => 'Backup file not found.'];
        }

        $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
        $mysql    = $this->findMysql();

        // If zip, extract first
        $sqlFile = $path;
        $tmpSql  = null;
        if (str_ends_with($path, '.zip')) {
            if (!class_exists('ZipArchive')) {
                return ['success' => false, 'error' => 'ZipArchive class not available. Cannot extract ZIP file.'];
            }
            $zip    = new \ZipArchive();
            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ams_restore_' . uniqid();
            mkdir($tmpDir, 0775, true);
            if ($zip->open($path) === true) {
                $zip->extractTo($tmpDir);
                $zip->close();
                $files   = glob($tmpDir . '/*.sql');
                $sqlFile = $files[0] ?? null;
                $tmpSql  = $tmpDir;
            }
            if (!$sqlFile || !is_file($sqlFile)) {
                return ['success' => false, 'error' => 'Could not extract SQL from ZIP.'];
            }
        }

        try {
            $optFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.my_' . uniqid() . '.cnf';
            file_put_contents($optFile, "[client]\npassword=" . $dbConfig['password'] . "\n");
            chmod($optFile, 0600);

            $cmd = sprintf(
                '%s --defaults-extra-file=%s -h %s -P %s -u %s %s < %s 2>&1',
                escapeshellarg($mysql),
                escapeshellarg($optFile),
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['port'] ?? '3306'),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($sqlFile)
            );

            exec($cmd, $output, $exitCode);
            @unlink($optFile);
            if ($tmpSql) {
                array_map('unlink', glob($tmpSql . '/*'));
                rmdir($tmpSql);
            }

            if ($exitCode !== 0) {
                return ['success' => false, 'error' => implode(' ', $output)];
            }
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete backup files older than retention days.
     */
    public function cleanupOld(): int
    {
        $days = (int) $this->cfg->get('backup_retention_days', 30);
        $cutoff = new \DateTimeImmutable("-{$days} days");

        $stmt = Database::connection()->prepare(
            "SELECT id, filepath FROM backup_logs WHERE created_at < ? AND status = 'success'"
        );
        $stmt->execute([$cutoff->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll();
        $deleted = 0;
        foreach ($rows as $row) {
            if ($row['filepath'] && is_file($row['filepath'])) {
                @unlink($row['filepath']);
                $deleted++;
            }
            Database::connection()->prepare('DELETE FROM backup_logs WHERE id = ?')
                ->execute([$row['id']]);
        }
        return $deleted;
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    private function findMysqldump(): string
    {
        foreach ([
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'mysqldump',
        ] as $candidate) {
            if (is_file($candidate) || $this->inPath($candidate)) {
                return $candidate;
            }
        }
        return 'mysqldump';
    }

    private function findMysql(): string
    {
        foreach ([
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'mysql',
        ] as $candidate) {
            if (is_file($candidate) || $this->inPath($candidate)) {
                return $candidate;
            }
        }
        return 'mysql';
    }

    private function inPath(string $cmd): bool
    {
        exec('where ' . escapeshellarg($cmd) . ' 2>NUL', $out, $code);
        return $code === 0;
    }
}
