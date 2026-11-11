<?php

namespace Beam\Core\Modules\AcfJsonSync;

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

        if (! ModuleRegistry::is_enabled('acfJsonSync')) {
            return;
        }

        require_once __DIR__ . '/JsonSync.php';
        require_once __DIR__ . '/WpCli.php';

        JsonSync::register();
        WpCli::register();
    }
}
