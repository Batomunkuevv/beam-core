<?php

namespace Beam\Core\Modules\AdminBranding;

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
        self::defineConstants();
        self::load();

        if (! ModuleRegistry::is_enabled('adminBranding')) {
            return;
        }

        if (class_exists('\BeamAdminBrandingPlugin')) {
            \BeamAdminBrandingPlugin::register();
        }
    }

    private static function defineConstants(): void
    {
        if (! defined('BEAM_ADMIN_BRANDING_VERSION')) {
            define('BEAM_ADMIN_BRANDING_VERSION', BEAM_CORE_VERSION);
        }

        if (! defined('BEAM_ADMIN_BRANDING_DIR')) {
            define('BEAM_ADMIN_BRANDING_DIR', __DIR__);
        }

        if (! defined('BEAM_ADMIN_BRANDING_FILE')) {
            define('BEAM_ADMIN_BRANDING_FILE', BEAM_CORE_FILE);
        }
    }

    private static function load(): void
    {
        $dir = __DIR__;

        foreach ([ 'AssetPath.php', 'Config.php', 'AdminBranding.php', 'SettingsPage.php', 'Plugin.php' ] as $file) {
            require_once $dir . '/' . $file;
        }
    }
}
