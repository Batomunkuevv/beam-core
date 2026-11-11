<?php

defined('ABSPATH') || exit;

final class BeamAdminBrandingConfig
{
    public const OPTION_KEY = 'beam_admin_branding_settings';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'enabled'               => true,
        // Neutral defaults (WordPress' own admin palette); each site sets its colours in Настройки → BE.AM Core.
        'scheme_slug'           => 'beam',
        'scheme_name'           => 'BE.AM',
        'force_scheme'          => true,
        'menu_background'       => '#1d2327',
        'submenu_background'    => '#2c3338',
        'hover_background'      => '#2c3338',
        'accent'                => '#2271b1',
        'notification'          => '#d63638',
        'theme_color'           => '#2271b1',
        'theme_color_darker_10' => '#135e96',
        'theme_color_darker_20' => '#0a4b78',
        'admin_body_background' => '#f0f0f1',
        'login_background'      => '#f0f0f1',
        'login_logo_id'         => 0,
        'login_header_text'     => '',
        'login_header_url'      => '',
        'icon_base'             => '#a7aaad',
        'icon_focus'            => '#ffffff',
        'icon_current'          => '#ffffff',
    ];

    /**
     * @var array<string, string>
     */
    private const CONSTANT_MAP = [
        'enabled'               => 'BEAM_ADMIN_BRANDING_ENABLED',
        'scheme_slug'           => 'BEAM_ADMIN_BRANDING_SCHEME_SLUG',
        'scheme_name'           => 'BEAM_ADMIN_BRANDING_SCHEME_NAME',
        'force_scheme'          => 'BEAM_ADMIN_BRANDING_FORCE_SCHEME',
        'login_logo_id'         => 'BEAM_ADMIN_BRANDING_LOGIN_LOGO_ID',
        'login_header_text'     => 'BEAM_ADMIN_BRANDING_LOGIN_HEADER_TEXT',
        'login_header_url'      => 'BEAM_ADMIN_BRANDING_LOGIN_HEADER_URL',
    ];

    public static function is_enabled(): bool
    {
        return (bool) self::get('enabled');
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_all(): array
    {
        $settings = wp_parse_args(
            get_option(self::OPTION_KEY, []),
            self::DEFAULTS,
        );

        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = self::resolve($key, $settings[$key]);
        }

        /**
         * @param array<string, mixed> $settings
         */
        return apply_filters('beam_admin_branding_config', $settings);
    }

    public static function get(string $key): mixed
    {
        $settings = self::get_all();

        return $settings[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function scheme_preview_colors(): array
    {
        return [
            (string) self::get('menu_background'),
            (string) self::get('submenu_background'),
            (string) self::get('accent'),
            (string) self::get('icon_current'),
        ];
    }

    /**
     * @return array{base: string, focus: string, current: string}
     */
    public static function icon_colors(): array
    {
        return [
            'base'    => (string) self::get('icon_base'),
            'focus'   => (string) self::get('icon_focus'),
            'current' => (string) self::get('icon_current'),
        ];
    }

    public static function login_header_text(): string
    {
        $text = trim((string) self::get('login_header_text'));

        return '' !== $text ? $text : (string) get_bloginfo('name');
    }

    public static function login_header_url(): string
    {
        $url = trim((string) self::get('login_header_url'));

        return '' !== $url ? $url : home_url('/');
    }

    public static function login_logo_url(): string
    {
        $attachment_id = (int) self::get('login_logo_id');

        if ($attachment_id > 0) {
            $url = wp_get_attachment_url($attachment_id);

            if (is_string($url) && '' !== $url) {
                return esc_url($url);
            }
        }

        $stored = get_option(self::OPTION_KEY, []);
        $legacy = is_array($stored) ? trim((string) ($stored['login_logo_url'] ?? '')) : '';

        if ('' !== $legacy) {
            return esc_url($legacy);
        }

        /**
         * @param string $url
         */
        $url = apply_filters('beam_admin_branding_login_logo_url', '');

        if ('' !== $url) {
            return esc_url($url);
        }

        return self::default_login_logo_url();
    }

    /**
     * Fallback when no logo is chosen: the site's custom logo (Внешний вид → Свой логотип), else '' — the login page
     * then shows the login header text (site name). No bundled logo.
     */
    public static function default_login_logo_url(): string
    {
        $custom = (int) get_theme_mod('custom_logo');
        $url    = $custom > 0 ? wp_get_attachment_url($custom) : false;

        return is_string($url) && '' !== $url ? esc_url($url) : '';
    }

    public static function login_logo_preview_url(): string
    {
        $attachment_id = (int) self::get('login_logo_id');

        if ($attachment_id > 0) {
            $url = wp_get_attachment_url($attachment_id);

            if (is_string($url) && '' !== $url) {
                return esc_url($url);
            }
        }

        return self::default_login_logo_url();
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function update(array $input): bool
    {
        $current  = get_option(self::OPTION_KEY, []);
        $settings = wp_parse_args(is_array($current) ? $current : [], self::DEFAULTS);

        $settings['enabled']      = ! empty($input['enabled']);
        $settings['force_scheme'] = ! empty($input['force_scheme']);
        $settings['scheme_slug']  = sanitize_key((string) ($input['scheme_slug'] ?? self::DEFAULTS['scheme_slug']));
        $settings['scheme_name']  = sanitize_text_field((string) ($input['scheme_name'] ?? ''));

        if ('' === $settings['scheme_slug']) {
            $settings['scheme_slug'] = (string) self::DEFAULTS['scheme_slug'];
        }

        $color_keys = [
            'menu_background',
            'submenu_background',
            'hover_background',
            'accent',
            'notification',
            'theme_color',
            'theme_color_darker_10',
            'theme_color_darker_20',
            'admin_body_background',
            'login_background',
            'icon_base',
            'icon_focus',
            'icon_current',
        ];

        foreach ($color_keys as $key) {
            $settings[$key] = self::sanitize_color((string) ($input[$key] ?? ''), (string) self::DEFAULTS[$key]);
        }

        if (! self::is_constant_defined('login_logo_id')) {
            $settings['login_logo_id'] = absint($input['login_logo_id'] ?? 0);

            if (! empty($input['login_logo_remove'])) {
                $settings['login_logo_id'] = 0;
            }
        }

        if (! self::is_constant_defined('login_header_text')) {
            $settings['login_header_text'] = sanitize_text_field((string) ($input['login_header_text'] ?? ''));
        }

        if (! self::is_constant_defined('login_header_url')) {
            $settings['login_header_url'] = esc_url_raw((string) ($input['login_header_url'] ?? ''));
        }

        return update_option(self::OPTION_KEY, $settings, false);
    }

    public static function is_constant_defined(string $key): bool
    {
        $constant = self::CONSTANT_MAP[$key] ?? '';

        return '' !== $constant && defined($constant);
    }

    /**
     * @return list<string>
     */
    public static function defined_constant_names(): array
    {
        $names = [];

        foreach (self::CONSTANT_MAP as $constant) {
            if (defined($constant)) {
                $names[] = $constant;
            }
        }

        return $names;
    }

    private static function resolve(string $key, mixed $stored): mixed
    {
        $constant = self::CONSTANT_MAP[$key] ?? '';

        if ('' === $constant || ! defined($constant)) {
            return $stored;
        }

        $value = constant($constant);

        return match ($key) {
            'enabled', 'force_scheme' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'login_logo_id' => absint($value),
            'login_header_url' => esc_url((string) $value),
            'login_header_text', 'scheme_name' => sanitize_text_field((string) $value),
            'scheme_slug' => sanitize_key((string) $value),
            default => $stored,
        };
    }

    private static function sanitize_color(string $value, string $fallback): string
    {
        $value = sanitize_hex_color($value);

        return is_string($value) && '' !== $value ? $value : $fallback;
    }
}
