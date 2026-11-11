<?php

namespace Beam\Core\Modules\AcfOffline;

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

        if (! ModuleRegistry::is_enabled('acfOffline')) {
            return;
        }

        require_once __DIR__ . '/AcfOffline.php';

        if (class_exists('\BeamAcfOffline')) {
            \BeamAcfOffline::register();
        }
    }
}
