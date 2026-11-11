<?php

namespace Beam\Core\Modules\SvgUpload;

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

        // The sanitizer is always available (themes use it for inline output), the upload hooks only when enabled.
        require_once __DIR__ . '/Sanitizer.php';

        if (! ModuleRegistry::is_enabled('svgUpload')) {
            return;
        }

        require_once __DIR__ . '/SvgUpload.php';
        SvgUpload::register();
    }
}
