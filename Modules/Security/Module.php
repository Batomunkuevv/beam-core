<?php

namespace Beam\Core\Modules\Security;

use Beam\Core\ModuleRegistry;

defined('ABSPATH') || exit;

final class Module
{
    private static bool $registered = false;
    private static bool $bootstrapped = false;

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;

        require_once __DIR__ . '/Config.php';

        if (! ModuleRegistry::is_enabled('security')) {
            return;
        }

        if (Config::isEnabled('disallow_file_edit') && ! defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        require_once __DIR__ . '/HideLogin.php';
        HideLogin::bootstrap();
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        require_once __DIR__ . '/Config.php';
        require_once __DIR__ . '/Security.php';
        require_once __DIR__ . '/HideLogin.php';
        require_once __DIR__ . '/XmlrpcStatus.php';
        require_once __DIR__ . '/LoginLimitStatus.php';
        require_once __DIR__ . '/SettingsPage.php';

        if (! ModuleRegistry::is_enabled('security')) {
            return;
        }

        Security::register();
    }
}
