<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route): string
{
    return APP_BASE_URL . '/index.php?route=' . rawurlencode($route);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function consumeFlash(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($messages) ? $messages : [];
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = APP_ROOT . '/app/views/' . $view . '.php';

    if (!is_file($viewFile)) {
        throw new RuntimeException('Không tìm thấy giao diện: ' . $view);
    }

    require APP_ROOT . '/app/views/layout.php';
}
