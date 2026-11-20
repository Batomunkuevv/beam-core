<?php

namespace Beam\Core\Modules\Blocks;

defined('ABSPATH') || exit;

final class Catalog
{
    public const SOURCE_CORE = 'core';

    public const SOURCE_THEME = 'theme';

    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const NAME = '/^[a-z0-9-]+\/[a-z0-9-]+$/';

    /** @var list<array<string, mixed>>|null */
    private static ?array $blocks = null;

    /** @var array<string, array<string, mixed>> */
    private static array $byName = [];

    /**
     * @return list<array<string, mixed>>
     */
    public static function blocks(): array
    {
        if (self::$blocks !== null) {
            return self::$blocks;
        }

        self::$blocks = [];
        self::$byName = [];

        if (! function_exists('get_stylesheet_directory') || ! function_exists('wp_normalize_path')) {
            return self::$blocks;
        }

        $found = [];

        foreach (self::paths() as $entry) {
            foreach (self::scan($entry['path'], $entry['source']) as $block) {
                $found[ $block['slug'] ] = $block;
            }
        }

        self::$blocks = array_values($found);

        foreach (self::$blocks as $block) {
            self::$byName[ $block['name'] ] = $block;
        }

        return self::$blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByName(string $name): ?array
    {
        self::blocks();

        return self::$byName[ $name ] ?? null;
    }

    public static function coreDirectory(): string
    {
        return wp_normalize_path(BEAM_CORE_DIR . '/Modules/Blocks/blocks');
    }

    /**
     * @return list<array{path: string, source: string}>
     */
    public static function paths(): array
    {
        $core = self::coreDirectory();
        $paths = [];

        if ($core !== '') {
            $paths[] = [
                'path'   => $core,
                'source' => self::SOURCE_CORE,
            ];
        }

        $template = wp_normalize_path(get_template_directory() . '/blocks');
        $stylesheet = wp_normalize_path(get_stylesheet_directory() . '/blocks');

        $paths[] = [
            'path'   => $template,
            'source' => self::SOURCE_THEME,
        ];

        if ($stylesheet !== $template) {
            $paths[] = [
                'path'   => $stylesheet,
                'source' => self::SOURCE_THEME,
            ];
        }

        $filtered = apply_filters('beam_blocks_paths', $paths);

        if (! is_array($filtered)) {
            $filtered = $paths;
        }

        return self::normalizePaths($filtered);
    }

    /**
     * @param list<mixed> $paths
     * @return list<array{path: string, source: string}>
     */
    private static function normalizePaths(array $paths): array
    {
        $core = self::coreDirectory();
        $normalized = [];

        foreach ($paths as $entry) {
            $path = '';
            $source = '';

            if (is_string($entry)) {
                $path = $entry;
            } elseif (is_array($entry)) {
                $path = isset($entry['path']) && is_string($entry['path']) ? $entry['path'] : '';
                $source = isset($entry['source']) && is_string($entry['source']) ? $entry['source'] : '';
            }

            $path = trim($path);

            if ($path === '') {
                continue;
            }

            $real = realpath($path);

            if ($real === false || ! is_dir($real)) {
                continue;
            }

            $real = wp_normalize_path($real);

            if ($source !== self::SOURCE_CORE && $source !== self::SOURCE_THEME) {
                $source = ($core !== '' && ($real === $core || str_starts_with($real, $core . '/')))
                    ? self::SOURCE_CORE
                    : self::SOURCE_THEME;
            }

            $normalized[ $real ] = [
                'path'   => $real,
                'source' => $source,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function scan(string $directory, string $source): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            return [];
        }

        sort($entries, SORT_STRING);
        $blocks = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            if (! preg_match(self::SLUG, $entry)) {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (! is_dir($path) || ! is_file($path . '/block.json')) {
                continue;
            }

            $block = self::readBlock($path, $entry, $source);

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function readBlock(string $directory, string $slug, string $source): ?array
    {
        $metadata = self::readJson($directory . '/block.json');

        if ($metadata === null) {
            return null;
        }

        $realDirectory = realpath($directory);

        if ($realDirectory === false) {
            return null;
        }

        $directory = wp_normalize_path($realDirectory);

        $name = isset($metadata['name']) && is_string($metadata['name']) ? strtolower(trim($metadata['name'])) : '';

        if ($name === '') {
            $name = 'acf/' . $slug;
        } elseif (! str_contains($name, '/')) {
            $name = 'acf/' . $name;
        }

        if (! preg_match(self::NAME, $name)) {
            return null;
        }

        $title = isset($metadata['title']) && is_string($metadata['title']) && $metadata['title'] !== ''
            ? $metadata['title']
            : $slug;
        $relative = 'render.php';
        $acf = $metadata['acf'] ?? null;

        if (is_array($acf) && isset($acf['renderTemplate']) && is_string($acf['renderTemplate']) && $acf['renderTemplate'] !== '') {
            $relative = $acf['renderTemplate'];
        }

        $templatePath = self::templatePath($slug, $directory, $relative);

        if ($templatePath === '') {
            self::reportMissingTemplate($name, $directory . '/' . ltrim($relative, '/'));

            return null;
        }

        $templateSource = str_starts_with($templatePath, $directory . '/') ? $source : self::SOURCE_THEME;

        return [
            'slug'            => $slug,
            'name'            => $name,
            'title'           => $title,
            'path'            => $directory,
            'source'          => $source,
            'templatePath'    => $templatePath,
            'templateSource'  => $templateSource,
        ];
    }

    private static function reportMissingTemplate(string $name, string $template): void
    {
        $message = sprintf('BE.AM Core: блок %s не зарегистрирован, нет шаблона %s.', $name, $template);

        error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('_doing_it_wrong')) {
            _doing_it_wrong(__CLASS__ . '::blocks', esc_html($message), '2.1.1');
        }
    }

    private static function templatePath(string $slug, string $directory, string $relative): string
    {
        $override = self::themeOverride($slug, $directory);

        if ($override !== '') {
            return $override;
        }

        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..')) {
            $relative = 'render.php';
        }

        $path = $directory . '/' . $relative;
        $real = realpath($path);

        if ($real === false || ! is_file($real)) {
            return '';
        }

        return wp_normalize_path($real);
    }

    private static function themeOverride(string $slug, string $directory): string
    {
        $own = wp_normalize_path($directory);
        $stylesheet = self::existingDirectory(get_stylesheet_directory() . '/blocks/' . $slug);

        if ($stylesheet !== '' && $own === $stylesheet) {
            return '';
        }

        $template = self::existingDirectory(get_template_directory() . '/blocks/' . $slug);
        $candidates = [];

        if ($stylesheet !== '' && $stylesheet !== $own) {
            $candidates[] = $stylesheet . '/render.php';
        }

        if ($template !== '' && $template !== $own && $template !== $stylesheet) {
            $candidates[] = $template . '/render.php';
        }

        foreach ($candidates as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $real = realpath($candidate);

            if ($real !== false) {
                return wp_normalize_path($real);
            }
        }

        return '';
    }

    private static function existingDirectory(string $path): string
    {
        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            return '';
        }

        return wp_normalize_path($real);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function readJson(string $path): ?array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
