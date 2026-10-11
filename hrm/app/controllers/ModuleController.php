<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

final class ModuleController extends Controller
{
    /** @param array{0:string,1:string,2:string} $module */
    public function show(array $module): void
    {
        [$key, $title, $action] = $module;
        if (Auth::can($key, $action)) {
            $this->authorize($key, $action);
        } elseif (Auth::can($key, 'personal')) {
            $this->authorize($key, 'personal');
        } else {
            $this->authorize($key, 'personal_manage');
        }
        $this->render('system/coming-soon', [
            'title' => $title,
            'moduleTitle' => $title,
            'canManage' => Auth::can($key, 'manage'),
        ]);
    }
}
