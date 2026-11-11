<?php

namespace Beam\Core;

defined('ABSPATH') || exit;

final class DuplicateNotice
{
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'render']);
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $duplicate = self::detect();
        if ($duplicate === null) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html($duplicate);
        echo '</p></div>';
    }

    public static function detect(): ?string
    {
        if (! defined('BEAM_CORE_FILE') || ! defined('WPMU_PLUGIN_DIR')) {
            return null;
        }

        $loaded = realpath(BEAM_CORE_FILE);
        $candidates = [];

        foreach (self::candidatePaths() as $path) {
            if (! is_file($path)) {
                continue;
            }

            $real = realpath($path);
            if (! is_string($real) || $real === '') {
                continue;
            }

            $candidates[$real] = $path;
        }

        if (count($candidates) < 2) {
            return null;
        }

        $root = is_string(realpath(WPMU_PLUGIN_DIR . '/beam-core.php'))
            ? realpath(WPMU_PLUGIN_DIR . '/beam-core.php')
            : '';
        $package = is_string(realpath(WPMU_PLUGIN_DIR . '/beam-core/beam-core.php'))
            ? realpath(WPMU_PLUGIN_DIR . '/beam-core/beam-core.php')
            : '';

        $rootIsLoader = $root !== '' && $package !== '' && $root !== $package && self::isLoaderFile(WPMU_PLUGIN_DIR . '/beam-core.php');
        if ($rootIsLoader && ! self::hasMuAutoloader() && count($candidates) === 2) {
            return null;
        }

        $loadedLabel = is_string($loaded) ? self::describe($loaded) : self::describe(BEAM_CORE_FILE);
        $others = [];
        foreach ($candidates as $real => $path) {
            if ($loaded !== false && $real === $loaded) {
                continue;
            }
            $others[] = self::describe($path);
        }

        if ($others === []) {
            return null;
        }

        return sprintf(
            'Обнаружена ещё одна копия BE.AM Core. Для установки через Composer или Bedrock удалите старый загрузчик mu-plugins/beam-core.php (или лишнюю копию плагина): иначе из-за константы BEAM_CORE_LOADED может загрузиться не тот файл. Сейчас загружен: %s. Также найден: %s.',
            $loadedLabel,
            implode(', ', $others),
        );
    }

    /**
     * @return list<string>
     */
    private static function candidatePaths(): array
    {
        $paths = [
            WPMU_PLUGIN_DIR . '/beam-core.php',
            WPMU_PLUGIN_DIR . '/beam-core/beam-core.php',
        ];

        if (defined('WP_PLUGIN_DIR')) {
            $paths[] = WP_PLUGIN_DIR . '/beam-core/beam-core.php';
        }

        return $paths;
    }

    private static function hasMuAutoloader(): bool
    {
        return is_file(WPMU_PLUGIN_DIR . '/bedrock-autoloader.php');
    }

    private static function isLoaderFile(string $path): bool
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return false;
        }

        if (preg_match('/^\s*\*\s*Plugin Name:/mi', $contents)) {
            return false;
        }

        return str_contains($contents, 'beam-core.php');
    }

    private static function describe(string $path): string
    {
        $version = self::versionFromFile($path);
        $relative = $path;
        if (defined('ABSPATH') && str_starts_with($path, ABSPATH)) {
            $relative = substr($path, strlen(ABSPATH));
        } elseif (defined('WP_CONTENT_DIR') && str_starts_with($path, dirname(WP_CONTENT_DIR))) {
            $relative = ltrim(substr($path, strlen(dirname(WP_CONTENT_DIR))), '/');
        }

        return $version !== '' ? $relative . ' (' . $version . ')' : $relative;
    }

    private static function versionFromFile(string $path): string
    {
        $contents = file_get_contents($path, false, null, 0, 4096);
        if (! is_string($contents)) {
            return '';
        }

        if (preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $contents, $match)) {
            return trim($match[1]);
        }

        return '';
    }
}
