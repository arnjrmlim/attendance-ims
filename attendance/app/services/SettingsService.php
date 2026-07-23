<?php

/**
 * SettingsService
 *
 * Reads and writes key/value settings from the `settings` table.
 * Values are cached in a static array for the request lifetime.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SettingsService
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /* ── Read ────────────────────────────────────────────────── */

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadCache();
        return self::$cache[$key] ?? $default;
    }

    /** Return all settings, optionally filtered by group. */
    public function all(?string $group = null): array
    {
        $this->loadCache();
        if ($group === null) {
            return self::$cache;
        }
        // Need group info — query directly
        $stmt = Database::connection()->prepare(
            'SELECT `key`, `value`, `type`, `group`, `description` FROM settings WHERE `group` = ? ORDER BY `key`'
        );
        $stmt->execute([$group]);
        return $stmt->fetchAll();
    }

    /** Return all settings keyed by group for the settings admin page. */
    public function allGrouped(): array
    {
        $stmt = Database::connection()->query(
            'SELECT `key`, `value`, `type`, `group`, `description` FROM settings ORDER BY `group`, `key`'
        );
        $rows    = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group']][] = $row;
        }
        return $grouped;
    }

    /* ── Write ───────────────────────────────────────────────── */

    /** Set a single setting value. */
    public function set(string $key, mixed $value): void
    {
        Database::connection()->prepare(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()"
        )->execute([$key, (string) $value]);

        self::$cache[$key] = (string) $value;
    }

    /** Batch-save an associative array of key => value. */
    public function saveMany(array $data): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            foreach ($data as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $this->set($key, $value);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Save SMTP password encrypted. */
    public function saveSmtpPassword(string $plain): void
    {
        $this->set('smtp_password', EmailService::encryptPassword($plain));
    }

    /* ── Cache ───────────────────────────────────────────────── */

    private function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }
        try {
            $stmt = Database::connection()->query('SELECT `key`, `value` FROM settings');
            self::$cache = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        } catch (\Throwable) {
            self::$cache = [];
        }
    }

    /** Flush the in-memory cache (call after saveMany). */
    public function flushCache(): void
    {
        self::$cache = null;
    }

    /* ── Company Helpers ────────────────────────────────────────── */

    /** Get company name with fallback. */
    public function getCompanyName(): string
    {
        return (string) $this->get('company_name', 'My Company');
    }

    /** Get company abbreviation with fallback. */
    public function getCompanyAbbreviation(): string
    {
        return (string) $this->get('company_abbreviation', 'IMS');
    }

    /** Get company logo path with fallback. */
    public function getCompanyLogo(): string
    {
        $logo = (string) $this->get('company_logo', '');
        if ($logo !== '' && !str_starts_with($logo, 'http')) {
            // If it's a relative path from uploads, prepend uploads/
            if (!str_starts_with($logo, 'uploads/')) {
                $logo = 'uploads/' . $logo;
            }
            return $logo;
        }
        return $logo !== '' ? $logo : 'assets/img/logo.svg';
    }

    /** Get company address. */
    public function getCompanyAddress(): string
    {
        return (string) $this->get('company_address', '');
    }

    /** Get company contact number. */
    public function getCompanyContact(): string
    {
        return (string) $this->get('company_contact', '');
    }

    /** Get company email. */
    public function getCompanyEmail(): string
    {
        return (string) $this->get('company_email', '');
    }
}
