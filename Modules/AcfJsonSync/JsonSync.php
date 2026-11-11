<?php

namespace Beam\Core\Modules\AcfJsonSync;

defined('ABSPATH') || exit;

final class JsonSync
{
    private const JSON_DIR = 'acf-json';

    public static function register(): void
    {
        add_filter('acf/settings/save_json', [self::class, 'savePath']);
        add_filter('acf/settings/load_json', [self::class, 'loadPaths']);
        add_action('acf/init', [self::class, 'onAcfInit'], 99);
    }

    public static function onAcfInit(): void
    {
        self::ensureDirectory();
        acf_update_setting('save_json', self::directory());
    }

    public static function savePath(): string
    {
        return self::directory();
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    public static function loadPaths(array $paths): array
    {
        $directory = self::directory();

        if (! in_array($directory, $paths, true)) {
            $paths[] = $directory;
        }

        return $paths;
    }

    public static function directory(): string
    {
        $directory = get_stylesheet_directory() . '/' . self::JSON_DIR;

        /**
         * Allows projects without a theme-owned schema to move ACF Local JSON.
         *
         * @param string $directory Absolute directory path.
         */
        $filtered = apply_filters('beam_acf_json_directory', $directory);

        if (! is_string($filtered) || '' === trim($filtered)) {
            return $directory;
        }

        return untrailingslashit($filtered);
    }

    private static function ensureDirectory(): void
    {
        $directory = self::directory();

        if (is_dir($directory)) {
            return;
        }

        wp_mkdir_p($directory);
    }
}
