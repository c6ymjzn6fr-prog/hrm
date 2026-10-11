<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function authorize(string $chucNang, string $hanhDong): void
    {
        Auth::requirePermission($chucNang, $hanhDong);
    }

    protected function render(string $view, array $data = []): void
    {
        render($view, $data);
    }

    protected function redirect(string $route): void
    {
        header('Location: ' . url($route));
        exit;
    }
}
