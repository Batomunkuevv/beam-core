<?php

namespace Beam\Core\Modules\Blocks;

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

        if (! ModuleRegistry::is_enabled('blocks')) {
            return;
        }

        require_once __DIR__ . '/Catalog.php';
        require_once __DIR__ . '/Fields.php';
        require_once __DIR__ . '/Renderer.php';
        require_once __DIR__ . '/Registrar.php';
        require_once __DIR__ . '/WpCli.php';
        require_once __DIR__ . '/functions.php';

        Registrar::register();
        WpCli::register();
    }
}
