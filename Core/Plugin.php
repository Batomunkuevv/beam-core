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
        self::forgetRemovedModules();

        foreach (ModuleRegistry::filtered_modules() as $class) {
            if (class_exists($class) && method_exists($class, 'register')) {
                $class::register();
            }
        }

        if (is_admin()) {
            SettingsPage::register();
            DuplicateNotice::register();
        }
    }

    private static function forgetRemovedModules(): void
    {
        $disabled = get_option(ModuleRegistry::OPTION_DISABLED, []);

        if (! is_array($disabled) || ! in_array('acfOffline', $disabled, true)) {
            return;
        }

        update_option(
            ModuleRegistry::OPTION_DISABLED,
            array_values(array_diff($disabled, ['acfOffline'])),
            false,
        );
    }
}
