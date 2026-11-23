<?php

namespace Beam\Core\Modules\Bitrix24;

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

        if (! ModuleRegistry::is_enabled('bitrix24')) {
            return;
        }

        require_once __DIR__ . '/functions.php';
    }

    public static function load(): void
    {
        foreach (['Config.php', 'Result.php', 'Log.php', 'Client.php', 'Person.php', 'Lead.php', 'SettingsPage.php'] as $file) {
            require_once __DIR__ . '/' . $file;
        }
    }
}
