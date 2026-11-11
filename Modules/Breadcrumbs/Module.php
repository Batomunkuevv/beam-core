<?php

namespace Beam\Core\Modules\Breadcrumbs;

use Beam\Core\ModuleRegistry;

defined('ABSPATH') || exit;

final class Module
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        if (! ModuleRegistry::is_enabled('breadcrumbs')) {
            return;
        }

        require_once __DIR__ . '/Breadcrumbs.php';
        require_once __DIR__ . '/Renderer.php';
        require_once __DIR__ . '/functions.php';
    }
}
