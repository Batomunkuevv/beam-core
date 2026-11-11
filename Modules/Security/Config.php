<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class Config
{
    public const OPTION = 'beam_security_features';
    public const LOGIN_SLUG_OPTION = 'beam_security_login_slug';
    public const FEATURES = [
        'disallow_file_edit',
        'hide_user_logins',
        'generic_login_errors',
        'hide_login',
    ];

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            'disallow_file_edit' => true,
            'hide_user_logins' => true,
            'generic_login_errors' => true,
            'hide_login' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $stored = get_option(self::OPTION, []);
        $features = self::defaults();

        if (! is_array($stored)) {
            return $features;
        }

        foreach (self::FEATURES as $key) {
            if (array_key_exists($key, $stored)) {
                $features[$key] = (bool) $stored[$key];
            }
        }

        return $features;
    }

    public static function isEnabled(string $feature): bool
    {
        $features = self::all();

        return ! empty($features[$feature]);
    }

    public static function isHideLoginDisabledByConstant(): bool
    {
        return defined('BEAM_DISABLE_HIDE_LOGIN') && BEAM_DISABLE_HIDE_LOGIN;
    }

    public static function isHideLoginActive(): bool
    {
        return self::isEnabled('hide_login') && ! self::isHideLoginDisabledByConstant();
    }

    public static function storedLoginSlug(): string
    {
        $stored = get_option(self::LOGIN_SLUG_OPTION, '');
        $slug = is_string($stored) ? self::sanitizeSlug($stored) : '';

        return $slug;
    }

    public static function loginSlug(): string
    {
        $slug = self::storedLoginSlug();
        if ($slug === '' && self::isEnabled('hide_login')) {
            $slug = self::assignGeneratedLoginSlug();
        }

        $filtered = apply_filters('beam_security_login_slug', $slug);
        if (is_string($filtered)) {
            $sanitized = self::sanitizeSlug($filtered);
            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return $slug;
    }

    public static function generateLoginSlug(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $max = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 16; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, $max)];
            }

            $slug = 'login-' . $suffix;
            if (self::validateSlug($slug) === null) {
                return $slug;
            }
        }

        return 'login-' . strtolower(wp_generate_password(6, false, false));
    }

    public static function assignGeneratedLoginSlug(): string
    {
        $slug = self::generateLoginSlug();
        update_option(self::LOGIN_SLUG_OPTION, $slug, false);

        return $slug;
    }

    public static function sanitizeSlug(string $slug): string
    {
        return sanitize_title($slug);
    }

    public static function validateSlug(string $slug): ?string
    {
        $slug = self::sanitizeSlug($slug);

        if ($slug === '' || strlen($slug) > 64) {
            return 'Укажите корректный адрес входа: латиница, цифры и дефис.';
        }

        if (str_contains($slug, 'wp-login') || str_contains($slug, 'wp-admin')) {
            return 'Этот адрес зарезервирован.';
        }

        if (in_array($slug, self::reservedSlugs(), true)) {
            return 'Этот адрес зарезервирован WordPress.';
        }

        $types = get_post_types(['public' => true], 'names');
        if (! is_array($types) || $types === []) {
            $types = ['page', 'post'];
        }

        $existing = get_page_by_path($slug, OBJECT, array_values($types));
        if ($existing instanceof \WP_Post) {
            return 'Страница или запись с таким адресом уже существует.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $wp = new \WP();
        $reserved = array_merge($wp->public_query_vars, $wp->private_query_vars);
        $extra = [
            'admin',
            'wp-admin',
            'wp-login',
            'wp-login.php',
            'wp-cron',
            'wp-json',
            'wp-content',
            'wp-includes',
            'wp-register',
            'wp-signup',
            'wp-activate',
            'xmlrpc',
            'xmlrpc.php',
            'feed',
            'rdf',
            'rss',
            'rss2',
            'atom',
            'robots',
            'favicon',
            'sitemap',
            'author',
            'attachment',
            'embed',
            'rest_route',
            'wordpress',
            'mu-plugins',
            'plugins',
            'themes',
            'uploads',
            'index',
            'cron',
            'install',
            'content',
            'includes',
            'ajax',
        ];

        $sitePath = wp_parse_url(site_url('/'), PHP_URL_PATH);
        if (is_string($sitePath) && $sitePath !== '' && $sitePath !== '/') {
            $extra[] = trim($sitePath, '/');
        }

        $homePath = wp_parse_url(home_url('/'), PHP_URL_PATH);
        if (is_string($homePath) && $homePath !== '' && $homePath !== '/') {
            $first = explode('/', trim($homePath, '/'))[0] ?? '';
            if ($first !== '') {
                $extra[] = $first;
            }
        }

        $unique = [];
        foreach (array_merge($reserved, $extra) as $item) {
            $item = self::sanitizeSlug((string) $item);
            if ($item !== '') {
                $unique[$item] = $item;
            }
        }

        return array_values($unique);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function update(array $input, ?string $loginSlug = null): void
    {
        $features = self::defaults();
        foreach (self::FEATURES as $key) {
            $features[$key] = ! empty($input[$key]);
        }

        update_option(self::OPTION, $features, false);

        if ($loginSlug !== null) {
            $sanitized = self::sanitizeSlug($loginSlug);
            if ($sanitized !== '') {
                update_option(self::LOGIN_SLUG_OPTION, $sanitized, false);
            }
        }
    }
}
