<?php

namespace Beam\Core;

defined('ABSPATH') || exit;

/**
 * Встроенная копия полей. Оригинальный плагин, если он активен, загружается
 * раньше хука plugins_loaded, поэтому class_exists('ACF') в этот момент
 * отличает его от нашей копии.
 */
final class Fields
{
    public static function register(): void
    {
        add_action('plugins_loaded', [self::class, 'load'], 0);
    }

    public static function load(): void
    {
        if (class_exists('ACF')) {
            add_action('admin_notices', [self::class, 'notice']);

            return;
        }

        $file = BEAM_CORE_DIR . '/vendor-acf/acf.php';

        if (! is_file($file)) {
            return;
        }

        require_once $file;
    }

    public static function notice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-info"><p>';
        echo esc_html('Уже установлен ACF, используется он.');
        echo '</p></div>';
    }
}
