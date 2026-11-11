<?php

namespace Beam\Core\Modules\Smtp;

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
        self::load();

        if (! ModuleRegistry::is_enabled('smtp')) {
            return;
        }

        if (class_exists('\BeamSmtp')) {
            \BeamSmtp::register();
        }
    }

    private static function load(): void
    {
        $dir = __DIR__;

        foreach ([ 'Config.php', 'Mailer.php', 'SettingsPage.php', 'Plugin.php' ] as $file) {
            require_once $dir . '/' . $file;
        }
    }
}
