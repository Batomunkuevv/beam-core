<?php

namespace Beam\Core\Modules\Favicon;

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

        require_once __DIR__ . '/Config.php';
        require_once __DIR__ . '/Generator.php';
        require_once __DIR__ . '/Tags.php';
        require_once __DIR__ . '/SettingsPage.php';

        if (! ModuleRegistry::is_enabled('favicon')) {
            return;
        }

        Tags::register();
        Generator::register();
    }
}
