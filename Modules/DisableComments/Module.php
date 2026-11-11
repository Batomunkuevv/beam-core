<?php

namespace Beam\Core\Modules\DisableComments;

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

        if (! ModuleRegistry::is_enabled('disableComments')) {
            return;
        }

        require_once __DIR__ . '/DisableComments.php';
        DisableComments::register();
    }
}
