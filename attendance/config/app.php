<?php

declare(strict_types=1);

return [
    'name' => 'Attendance Management System',
    'version' => '2.0.0',
    'timezone' => 'Asia/Manila',
    'base_url' => '/attendance-ims/attendance/public',
    'upload_path' => dirname(__DIR__) . '/public/uploads',
    'company_logo' => '/attendance/public/assets/img/logo.svg',
    'items_per_page' => 15,
    'exclude_weekends_from_leave' => true,
];
