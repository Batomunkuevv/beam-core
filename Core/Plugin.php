<?php

namespace Beam\Core;

use Beam\Core\Settings\SettingsPage;

defined('ABSPATH') || exit;

final class Plugin
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        foreach (ModuleRegistry::filtered_modules() as $class) {
            if (class_exists($class) && method_exists($class, 'register')) {
                $class::register();
            }
        }

        if (is_admin()) {
            SettingsPage::register();
        }
    }
}
