<?php

declare(strict_types=1);

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return rtrim((string) config('base_url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['_csrf'] ?? '', (string) $token)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('uuid_v4')) {
    function uuid_v4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('has_role')) {
    function has_role(array|string $roles): bool
    {
        $user = current_user();
        $allowed = (array) $roles;

        return $user !== null && in_array($user['role_slug'] ?? '', $allowed, true);
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!current_user()) {
            redirect('login');
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(array|string $roles): void
    {
        require_login();
        if (!has_role($roles)) {
            // Log unauthorized access attempt
            $user = current_user();
            if ($user) {
                try {
                    $auditService = new \App\Services\AuditService();
                    $auditService->log('UNAUTHORIZED_ACCESS', 'security', null, null, [
                        'required_roles' => is_array($roles) ? implode(', ', $roles) : $roles,
                        'user_role' => $user['role_slug'] ?? 'unknown',
                        'requested_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                    ]);
                } catch (\Throwable $e) {
                    // Log error but don't prevent the 403 response
                    error_log('Failed to log unauthorized access: ' . $e->getMessage());
                }
            }
            
            http_response_code(403);
            exit('You do not have permission to access this page.');
        }
    }
}

if (!function_exists('pagination_meta')) {
    function pagination_meta(int $total, int $page, int $perPage): array
    {
        return [
            'total' => $total,
            'page' => max(1, $page),
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / max(1, $perPage))),
            'offset' => max(0, ($page - 1) * $perPage),
        ];
    }
}

if (!function_exists('save_upload')) {
    function save_upload(string $field, array $allowed = ['pdf', 'jpg', 'jpeg', 'png']): ?string
    {
        if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        if ($_FILES[$field]['size'] > 3 * 1024 * 1024) {
            throw new RuntimeException('Attachment must not exceed 3MB.');
        }

        $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Unsupported attachment type.');
        }

        $uploadDir = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'phase2';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $name = uuid_v4() . '.' . $extension;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            throw new RuntimeException('Unable to store attachment.');
        }

        return 'phase2/' . $name;
    }
}

if (!function_exists('date_range_label')) {
    function date_range_label(?string $start, ?string $end): string
    {
        if (!$start && !$end) {
            return 'All dates';
        }

        return trim(($start ?: 'Beginning') . ' to ' . ($end ?: 'Today'));
    }
}
