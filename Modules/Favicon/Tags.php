<?php

namespace Beam\Core\Modules\Favicon;

defined('ABSPATH') || exit;

final class Tags
{
    public static function register(): void
    {
        add_action('wp_head', [self::class, 'output'], 2);
        add_filter('site_icon_meta_tags', [self::class, 'filterCoreTags']);
    }

    public static function output(): void
    {
        $tags = self::links();
        foreach ($tags as $tag) {
            echo $tag . "\n";
        }
    }

    /**
     * @return list<string>
     */
    public static function links(): array
    {
        $id = Config::attachmentId();
        $defaults = Config::defaults();
        $theme = Config::themeColor();
        $tags = [];

        if ($id > 0) {
            $derived = Generator::ensure();
            $urls = is_array($derived['urls'] ?? null) ? $derived['urls'] : [];

            if (! empty($urls['svg'])) {
                $tags[] = self::link('icon', (string) $urls['svg'], ['type' => 'image/svg+xml']);
            }
            if (! empty($urls['32'])) {
                $tags[] = self::link('icon', (string) $urls['32'], ['sizes' => '32x32', 'type' => 'image/png']);
            }
            if (! empty($urls['180'])) {
                $tags[] = self::link('apple-touch-icon', (string) $urls['180'], ['sizes' => '180x180']);
            }
            if (! empty($urls['manifest'])) {
                $tags[] = self::link('manifest', (string) $urls['manifest']);
            }
            if ($defaults['ico'] !== '') {
                $tags[] = self::link('icon', $defaults['ico'], ['sizes' => 'any']);
            }
            if ($theme !== '') {
                $tags[] = '<meta name="theme-color" content="' . esc_attr($theme) . '">';
            }

            return $tags;
        }

        $hasDefaults = $defaults['svg'] !== '' || $defaults['icon_32'] !== '' || $defaults['apple_touch'] !== '';
        if (! $hasDefaults) {
            return [];
        }

        if ($defaults['svg'] !== '') {
            $tags[] = self::link('icon', $defaults['svg'], ['type' => 'image/svg+xml']);
        }
        if ($defaults['icon_32'] !== '') {
            $tags[] = self::link('icon', $defaults['icon_32'], ['sizes' => '32x32', 'type' => 'image/png']);
        }
        if ($defaults['apple_touch'] !== '') {
            $tags[] = self::link('apple-touch-icon', $defaults['apple_touch'], ['sizes' => '180x180']);
        }
        if ($defaults['icon_192'] !== '' && $defaults['icon_512'] !== '') {
        }
        if ($defaults['ico'] !== '') {
            $tags[] = self::link('icon', $defaults['ico'], ['sizes' => 'any']);
        }
        if ($theme !== '') {
            $tags[] = '<meta name="theme-color" content="' . esc_attr($theme) . '">';
        }

        return $tags;
    }

    public static function hasOverride(): bool
    {
        if (Config::attachmentId() > 0) {
            return true;
        }

        $defaults = Config::defaults();

        return $defaults['svg'] !== '' || $defaults['icon_32'] !== '' || $defaults['apple_touch'] !== '';
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    public static function filterCoreTags(array $tags): array
    {
        return self::hasOverride() ? [] : $tags;
    }

    /**
     * @param array<string, string> $attrs
     */
    private static function link(string $rel, string $href, array $attrs = []): string
    {
        $html = '<link rel="' . esc_attr($rel) . '" href="' . esc_url($href) . '"';
        foreach ($attrs as $name => $value) {
            $html .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
        }

        return $html . '>';
    }
}
