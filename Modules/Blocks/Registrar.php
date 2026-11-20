<?php

namespace Beam\Core\Modules\Blocks;

defined('ABSPATH') || exit;

final class Registrar
{
    public const CATEGORY = 'beam-blocks';

    private const ASSET_FIELDS = ['editorScript', 'script', 'viewScript', 'editorStyle', 'style'];

    /** @var array<string, mixed>|null */
    private static ?array $current = null;

    private static bool $booted = false;

    public static function register(): void
    {
        add_action('init', [self::class, 'onInit'], 99);
    }

    public static function onInit(): void
    {
        if (self::$booted) {
            return;
        }

        if (! function_exists('register_block_type') || ! function_exists('acf_register_block_type') || ! function_exists('acf_add_local_field_group')) {
            return;
        }

        self::$booted = true;
        $active = false;
        $usesCategory = false;

        foreach (Catalog::blocks() as $block) {
            $name = (string) $block['name'];

            if (! self::isRegistered($name) && self::registerBlock($block)) {
                Fields::attach($block);
            }

            if (! self::isRegistered($name)) {
                continue;
            }

            $active = true;
            $usesCategory = $usesCategory || self::blockUsesCategory($name);
        }

        if (! $active) {
            return;
        }

        if ($usesCategory) {
            add_filter('block_categories_all', [self::class, 'categories']);
        }

        add_filter('allowed_block_types_all', [self::class, 'allowed'], 100, 2);
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function registerBlock(array $block): bool
    {
        $blockJson = $block['path'] . '/block.json';
        $real = realpath($blockJson);

        if ($real === false) {
            return false;
        }

        $block['file'] = wp_normalize_path($real);
        self::$current = $block;
        add_filter('block_type_metadata', [self::class, 'filterMetadata']);

        try {
            $result = register_block_type($block['path']);
        } finally {
            remove_filter('block_type_metadata', [self::class, 'filterMetadata']);
            self::$current = null;
        }

        return $result instanceof \WP_Block_Type;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function filterMetadata(array $metadata): array
    {
        $current = self::$current;

        if ($current === null) {
            return $metadata;
        }

        $file = isset($metadata['file']) && is_string($metadata['file']) ? wp_normalize_path($metadata['file']) : '';

        if ($file === '' || $file !== $current['file']) {
            return $metadata;
        }

        $metadata['name'] = $current['name'];

        if (! isset($metadata['apiVersion'])) {
            $metadata['apiVersion'] = 3;
        }

        if (! isset($metadata['category']) || ! is_string($metadata['category']) || $metadata['category'] === '') {
            $metadata['category'] = self::CATEGORY;
        }

        $acf = isset($metadata['acf']) && is_array($metadata['acf']) ? $metadata['acf'] : [];

        if (! isset($acf['mode'])) {
            $acf['mode'] = 'preview';
        }

        if (! isset($acf['blockVersion'])) {
            $acf['blockVersion'] = 3;
        }

        $acf['renderTemplate'] = Renderer::bridge();
        $metadata['acf'] = $acf;

        $assets = self::assets($metadata, $current);

        foreach (self::ASSET_FIELDS as $field) {
            if (! array_key_exists($field, $assets)) {
                unset($metadata[ $field ]);
                continue;
            }

            $metadata[ $field ] = $assets[ $field ];
        }

        return $metadata;
    }

    /**
     * @param mixed $categories
     * @return array<int, array<string, mixed>>
     */
    public static function categories(mixed $categories): array
    {
        if (! is_array($categories)) {
            $categories = [];
        }

        foreach ($categories as $category) {
            if (is_array($category) && ($category['slug'] ?? '') === self::CATEGORY) {
                return $categories;
            }
        }

        $categories[] = self::category();

        return $categories;
    }

    public static function allowed(mixed $allowed, \WP_Block_Editor_Context $context): mixed
    {
        $post = $context->post ?? null;

        if (! $post instanceof \WP_Post) {
            return $allowed;
        }

        $map = apply_filters('beam_blocks_allowed', []);

        if (! is_array($map) || ! isset($map[ $post->post_type ]) || ! is_array($map[ $post->post_type ])) {
            return $allowed;
        }

        $extra = self::names($map[ $post->post_type ]);

        if (! is_array($allowed)) {
            return $allowed;
        }

        return array_values(array_unique(array_merge(self::names($allowed), $extra)));
    }

    /**
     * @return array{slug: string, title: string, icon?: string}
     */
    private static function category(): array
    {
        $defaults = [
            'title' => 'Блоки сайта',
            'icon'  => 'layout',
        ];
        $filtered = apply_filters('beam_blocks_category', $defaults);

        if (! is_array($filtered)) {
            $filtered = $defaults;
        }

        $title = $filtered['title'] ?? $defaults['title'];
        $icon = array_key_exists('icon', $filtered) ? $filtered['icon'] : $defaults['icon'];

        if (! is_string($title) || $title === '') {
            $title = $defaults['title'];
        }

        $category = [
            'slug'  => self::CATEGORY,
            'title' => $title,
        ];

        if (is_string($icon) && $icon !== '') {
            $category['icon'] = $icon;
        }

        return $category;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private static function assets(array $metadata, array $block): array
    {
        $assets = [];

        foreach (self::ASSET_FIELDS as $field) {
            if (! empty($metadata[ $field ])) {
                $assets[ $field ] = $metadata[ $field ];
            }
        }

        $directory = (string) $block['path'];

        if (! isset($assets['style']) && is_file($directory . '/style.css')) {
            $assets['style'] = 'file:./style.css';
        }

        if (! isset($assets['script']) && ! isset($assets['viewScript']) && is_file($directory . '/script.js')) {
            $assets['viewScript'] = 'file:./script.js';
        }

        $context = [
            'slug'     => (string) $block['slug'],
            'name'     => (string) $block['name'],
            'path'   => $directory,
            'source' => (string) $block['source'],
        ];
        $filtered = apply_filters('beam_block_assets', $assets, $context);

        if (! is_array($filtered)) {
            $filtered = $assets;
        }

        $resolved = [];

        foreach (self::ASSET_FIELDS as $field) {
            if (! array_key_exists($field, $filtered)) {
                continue;
            }

            $value = self::normalizeAsset($field, $filtered[ $field ], (string) $block['slug']);

            if ($value !== null) {
                $resolved[ $field ] = $value;
            }
        }

        return $resolved;
    }

    private static function normalizeAsset(string $field, mixed $value, string $slug): mixed
    {
        if ($value === false || $value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return self::normalizeAssetItem($field, $value, $slug, 0);
        }

        if (! is_array($value)) {
            return null;
        }

        $items = [];
        $index = 0;

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                continue;
            }

            $normalized = self::normalizeAssetItem($field, $item, $slug, $index);

            if ($normalized === '') {
                continue;
            }

            $items[] = $normalized;
            ++$index;
        }

        if ($items === []) {
            return null;
        }

        return count($items) === 1 ? $items[0] : $items;
    }

    private static function normalizeAssetItem(string $field, string $value, string $slug, int $index): string
    {
        if (str_starts_with($value, 'file:')) {
            return $value;
        }

        if (! self::isUrl($value)) {
            return $value;
        }

        $handle = self::assetHandle($slug, $field, $index);
        self::registerAsset($field, $handle, $value);

        return $handle;
    }

    private static function isUrl(string $value): bool
    {
        return str_starts_with($value, 'https://')
            || str_starts_with($value, 'http://')
            || str_starts_with($value, '//')
            || str_starts_with($value, '/');
    }

    private static function registerAsset(string $field, string $handle, string $url): void
    {
        $isStyle = $field === 'style' || $field === 'editorStyle';

        if ($isStyle) {
            if (! wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $url, [], null);
            }

            return;
        }

        if (! wp_script_is($handle, 'registered')) {
            wp_register_script($handle, $url, [], null, true);
        }
    }

    private static function assetHandle(string $slug, string $field, int $index): string
    {
        $suffix = match ($field) {
            'editorScript' => 'editor-script',
            'editorStyle'  => 'editor-style',
            'viewScript'   => 'view-script',
            'script'       => 'script',
            default        => 'style',
        };
        $handle = 'beam-' . $slug . '-' . $suffix;

        if ($index > 0) {
            $handle .= '-' . (string) ($index + 1);
        }

        return $handle;
    }

    private static function blockUsesCategory(string $name): bool
    {
        $type = \WP_Block_Type_Registry::get_instance()->get_registered($name);

        return $type instanceof \WP_Block_Type && $type->category === self::CATEGORY;
    }

    private static function isRegistered(string $name): bool
    {
        if (\WP_Block_Type_Registry::get_instance()->is_registered($name)) {
            return true;
        }

        return function_exists('acf_has_block_type') && acf_has_block_type($name);
    }

    /**
     * @param array<mixed> $names
     * @return list<string>
     */
    private static function names(array $names): array
    {
        $clean = [];

        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                $clean[] = $name;
            }
        }

        return array_values(array_unique($clean));
    }
}
