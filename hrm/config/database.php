<?php
declare(strict_types=1);

return [
    'host' => getenv('HRM_DB_HOST') ?: 'localhost',
    'name' => getenv('HRM_DB_NAME') ?: 'hrm_htmedia',
    'user' => getenv('HRM_DB_USER') ?: 'root',
    'password' => getenv('HRM_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
