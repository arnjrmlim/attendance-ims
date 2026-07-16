<?php

declare(strict_types=1);

use App\Core\Router;

session_start();

require dirname(__DIR__) . '/app/helpers/functions.php';

date_default_timezone_set((string) config('timezone', 'Asia/Manila'));

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'App\\' => dirname(__DIR__) . '/app/',
            'Config\\' => dirname(__DIR__) . '/config/',
        ];
        foreach ($prefixes as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });
}

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
