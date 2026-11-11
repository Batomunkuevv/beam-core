<?php

defined('ABSPATH') || exit;

final class BeamDisableEmoji
{
    public static function register(): void
    {
        add_action('init', [self::class, 'disable']);
        add_action('admin_init', [self::class, 'disableAdmin']);
        add_filter('emoji_svg_url', '__return_false');
        add_filter('tiny_mce_plugins', [self::class, 'removeTinymcePlugin']);
        add_filter('wp_resource_hints', [self::class, 'removeDnsPrefetch'], 10, 2);
    }

    public static function disable(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('embed_head', 'print_emoji_detection_script');
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('enqueue_embed_scripts', 'wp_enqueue_emoji_styles');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    public static function disableAdmin(): void
    {
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
    }

    /**
     * @param string[] $plugins
     * @return string[]
     */
    public static function removeTinymcePlugin(array $plugins): array
    {
        return array_diff($plugins, ['wpemoji']);
    }

    /**
     * @param array<int, string|array<string, mixed>> $urls
     * @return array<int, string|array<string, mixed>>
     */
    public static function removeDnsPrefetch(array $urls, string $relationType): array
    {
        if ($relationType !== 'dns-prefetch') {
            return $urls;
        }

        return array_values(array_filter(
            $urls,
            static function (string|array $resource): bool {
                $url = is_array($resource) ? ($resource['href'] ?? '') : $resource;

                return ! is_string($url) || ! str_starts_with($url, 'https://s.w.org/images/core/emoji/');
            },
        ));
    }
}
