<?php

/**
 * Cron Bootstrap
 * Shared loader for all background/scheduled scripts.
 * Bootstraps autoloading, DB, helpers, timezone, and a
 * simple job-log helper so every cron script stays lean.
 *
 * Usage (at top of every cron script):
 *   require __DIR__ . '/bootstrap.php';
 */

declare(strict_types=1);

define('CRON_ROOT',   dirname(__DIR__));
define('CRON_START',  microtime(true));

/* ── Autoload / helpers ────────────────────────────────────── */
require CRON_ROOT . '/app/helpers/functions.php';

date_default_timezone_set((string) config('timezone', 'Asia/Manila'));

$vendor = CRON_ROOT . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
} else {
    spl_autoload_register(static function (string $class): void {
        $map = [
            'App\\'    => CRON_ROOT . '/app/',
            'Config\\' => CRON_ROOT . '/config/',
        ];
        foreach ($map as $prefix => $base) {
            if (str_starts_with($class, $prefix)) {
                $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });
}

/* ── Minimal session stub (services use current_user()) ────── */
if (!isset($_SESSION)) {
    $_SESSION = [];
}

/* ── CLI output helper ─────────────────────────────────────── */
function cron_log(string $message, string $level = 'INFO'): void
{
    $ts = date('Y-m-d H:i:s');
    $line = "[{$ts}] [{$level}] {$message}" . PHP_EOL;
    echo $line;

    // Also write to logs/cron.log (rotate when > 5 MB)
    $logDir  = CRON_ROOT . '/logs';
    $logFile = $logDir . '/cron.log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    if (is_file($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
        rename($logFile, $logDir . '/cron_' . date('Ymd_His') . '.log');
    }
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/* ── Job-log DB helpers ────────────────────────────────────── */
function job_start(string $jobName): string
{
    $id   = uuid_v4();
    $stmt = \App\Core\Database::connection()->prepare(
        "INSERT INTO job_logs (id, job_name, status, started_at) VALUES (?, ?, 'running', NOW())"
    );
    $stmt->execute([$id, $jobName]);
    return $id;
}

function job_finish(string $jobId, bool $success, string $output = '', string $error = ''): void
{
    \App\Core\Database::connection()->prepare(
        "UPDATE job_logs
         SET status = ?, output = ?, error = ?, finished_at = NOW()
         WHERE id = ?"
    )->execute([$success ? 'success' : 'failed', $output ?: null, $error ?: null, $jobId]);
}

/* ── Lock-file guard (prevents overlapping cron runs) ───────── */
function cron_lock(string $name): bool
{
    $lockDir  = CRON_ROOT . '/logs';
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0775, true);
    }
    $lock = $lockDir . '/' . $name . '.lock';
    $fp   = fopen($lock, 'c');
    if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
        return false; // another instance is running
    }
    // Store handle so lock persists for script lifetime
    $GLOBALS['_cron_lock_fp'][$name] = $fp;
    ftruncate($fp, 0);
    fwrite($fp, (string) getmypid());
    return true;
}
