<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;
    private static int $retryCount = 0;
    private const MAX_RETRIES = 3;

    public static function connection(): PDO
    {
        // Check if existing connection is still alive
        if (self::$pdo instanceof PDO) {
            try {
                self::$pdo->query('SELECT 1');
                return self::$pdo;
            } catch (PDOException $e) {
                // Connection is dead, reset and retry
                self::$pdo = null;
                self::$retryCount++;
                if (self::$retryCount <= self::MAX_RETRIES) {
                    return self::connection();
                }
                throw new RuntimeException('Database connection lost after ' . self::MAX_RETRIES . ' retry attempts. Please ensure MySQL is running.');
            }
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_PERSISTENT => false,
            ]);
            self::$retryCount = 0; // Reset retry count on successful connection
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1049) {
                http_response_code(503);
                exit('Database is not installed. Run database/schema.sql and database/seed.sql, then reload the application.');
            }

            if (($exception->errorInfo[1] ?? null) === 2002) {
                throw new RuntimeException('Cannot connect to MySQL server. Please ensure MySQL is running in XAMPP Control Panel.');
            }

            throw $exception;
        }

        return self::$pdo;
    }

    /**
     * Reset connection (useful for testing or after long operations)
     */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$retryCount = 0;
    }
}
