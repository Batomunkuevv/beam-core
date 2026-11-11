<?php

namespace Beam\Core\Modules\DisableEmoji;

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

        if (! ModuleRegistry::is_enabled('disableEmoji')) {
            return;
        }

        require_once __DIR__ . '/DisableEmoji.php';

        if (class_exists('\BeamDisableEmoji')) {
            \BeamDisableEmoji::register();
        }
    }
}
