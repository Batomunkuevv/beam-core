<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class HideLogin
{
    private static bool $bootstrapped = false;
    private static bool $isDefaultLoginRequest = false;

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;

        if (! Config::isHideLoginActive()) {
            return;
        }

        add_action('plugins_loaded', [self::class, 'onPluginsLoaded'], 1);
        add_action('setup_theme', [self::class, 'blockCustomizer'], 1);
        add_action('wp_loaded', [self::class, 'onWpLoaded'], 1);
        add_filter('site_url', [self::class, 'filterSiteUrl'], 10, 4);
        add_filter('network_site_url', [self::class, 'filterNetworkSiteUrl'], 10, 3);
        add_filter('wp_redirect', [self::class, 'filterRedirect'], 10, 2);
        add_filter('login_url', [self::class, 'filterNamedUrl'], 10, 1);
        add_filter('logout_url', [self::class, 'filterNamedUrl'], 10, 1);
        add_filter('lostpassword_url', [self::class, 'filterNamedUrl'], 10, 1);
        add_filter('register_url', [self::class, 'filterNamedUrl'], 10, 1);
    }

    public static function loginUrl(?string $scheme = null): string
    {
        $url = home_url('/', $scheme);

        if (get_option('permalink_structure')) {
            return self::withTrailingSlash($url . Config::loginSlug());
        }

        return $url . '?' . Config::loginSlug();
    }

    public static function loginPath(): string
    {
        $path = wp_parse_url(self::loginUrl(), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/' . Config::loginSlug();
    }

    public static function onPluginsLoaded(): void
    {
        if (self::isDefaultLoginRequest() && ! is_admin()) {
            self::$isDefaultLoginRequest = true;
            $_SERVER['REQUEST_URI'] = self::withTrailingSlash('/' . str_repeat('-/', 10));
            $GLOBALS['pagenow'] = 'index.php';

            return;
        }

        if (self::isCustomLoginRequest()) {
            $_SERVER['SCRIPT_NAME'] = Config::loginSlug();
            $GLOBALS['pagenow'] = 'wp-login.php';
        }
    }

    public static function blockCustomizer(): void
    {
        $pagenow = $GLOBALS['pagenow'] ?? '';
        if ($pagenow === 'customize.php' && ! is_user_logged_in()) {
            self::serve404();
        }
    }

    public static function onWpLoaded(): void
    {
        global $pagenow, $error, $interim_login, $action, $user_login;

        if (self::isPostPasswordRequest() && self::$isDefaultLoginRequest) {
            return;
        }

        if (is_admin() && ! is_user_logged_in() && ! self::isAllowedAdminRequest()) {
            self::serve404();
        }

        $requestPath = self::requestUriPath();
        if (
            $pagenow === 'wp-login.php'
            && self::isCustomLoginRequest()
            && get_option('permalink_structure')
            && $requestPath !== ''
            && $requestPath !== self::withTrailingSlash($requestPath)
            && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST'
        ) {
            $target = self::withTrailingSlash(self::loginUrl());
            $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
            wp_safe_redirect($query !== '' ? $target . '?' . $query : $target);
            exit;
        }

        if (self::$isDefaultLoginRequest) {
            self::serve404();
        }

        if ($pagenow !== 'wp-login.php') {
            return;
        }

        if (is_user_logged_in() && empty($_REQUEST['action'])) {
            wp_safe_redirect(admin_url());
            exit;
        }

        if (self::isRunningAsLoginFile()) {
            return;
        }

        $error = $error ?? '';
        $interim_login = $interim_login ?? false;
        $action = $action ?? '';
        $user_login = $user_login ?? '';

        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    /**
     * @param mixed $url
     * @param mixed $path
     * @param mixed $scheme
     * @param mixed $blogId
     */
    public static function filterSiteUrl(mixed $url, mixed $path, mixed $scheme, mixed $blogId): mixed
    {
        unset($path, $blogId);

        return is_string($url) ? self::rewriteLoginUrl($url, is_string($scheme) ? $scheme : null) : $url;
    }

    /**
     * @param mixed $url
     * @param mixed $path
     * @param mixed $scheme
     */
    public static function filterNetworkSiteUrl(mixed $url, mixed $path, mixed $scheme): mixed
    {
        unset($path);

        return is_string($url) ? self::rewriteLoginUrl($url, is_string($scheme) ? $scheme : null) : $url;
    }

    /**
     * @param mixed $location
     * @param mixed $status
     */
    public static function filterRedirect(mixed $location, mixed $status): mixed
    {
        unset($status);

        return is_string($location) ? self::rewriteLoginUrl($location) : $location;
    }

    /**
     * @param mixed $url
     */
    public static function filterNamedUrl(mixed $url): mixed
    {
        return is_string($url) ? self::rewriteLoginUrl($url) : $url;
    }

    public static function rewriteLoginUrl(string $url, ?string $scheme = null): string
    {
        if (stripos($url, 'wp-login.php') === false) {
            return $url;
        }

        if (stripos($url, 'https://wordpress.com/wp-login.php') !== false) {
            return $url;
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        $args = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $args);
            if (isset($args['login'])) {
                $args['login'] = rawurlencode((string) $args['login']);
            }
        }

        $login = self::loginUrl($scheme);

        return $args !== [] ? add_query_arg($args, $login) : $login;
    }


    private static function isRunningAsLoginFile(): bool
    {
        $loginFile = realpath(ABSPATH . 'wp-login.php');
        $script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string) $_SERVER['SCRIPT_FILENAME']) : false;

        return is_string($loginFile) && is_string($script) && $script === $loginFile;
    }

    private static function isDefaultLoginRequest(): bool
    {
        $uri = self::rawRequestUri();

        return stripos($uri, 'wp-login.php') !== false || stripos($uri, 'wp-register.php') !== false;
    }

    private static function isCustomLoginRequest(): bool
    {
        if (! get_option('permalink_structure')) {
            $slug = Config::loginSlug();

            return isset($_GET[$slug]) && (string) $_GET[$slug] === '';
        }

        $path = self::requestPath();
        $loginPath = wp_parse_url(home_url('/' . Config::loginSlug()), PHP_URL_PATH);
        $loginPath = is_string($loginPath) ? untrailingslashit($loginPath) : '/' . Config::loginSlug();

        return $path !== '' && $path === $loginPath;
    }

    private static function isPostPasswordRequest(): bool
    {
        $action = isset($_REQUEST['action']) ? (string) wp_unslash($_REQUEST['action']) : '';

        return $action === 'postpass' && isset($_POST['post_password']);
    }

    private static function isAllowedAdminRequest(): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }

        $pagenow = (string) ($GLOBALS['pagenow'] ?? '');
        $allowed = ['admin-ajax.php', 'admin-post.php'];
        if (in_array($pagenow, $allowed, true)) {
            return true;
        }

        $path = self::requestPath();
        foreach ($allowed as $file) {
            if ($path === $file || str_ends_with($path, '/' . $file)) {
                return true;
            }
        }

        return false;
    }

    private static function serve404(): void
    {
        global $wp_query, $pagenow;

        $pagenow = 'index.php';

        if (! defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }

        if (! did_action('wp')) {
            wp();
        }

        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
            $wp_query->is_404 = true;
            $wp_query->is_page = false;
            $wp_query->is_single = false;
            $wp_query->is_singular = false;
            $wp_query->is_home = false;
            $wp_query->is_front_page = false;
        }

        add_filter('show_admin_bar', '__return_false', PHP_INT_MAX);
        remove_action('wp_body_open', 'wp_admin_bar_render', 0);
        remove_action('wp_footer', 'wp_admin_bar_render', 1000);

        status_header(404);
        nocache_headers();

        require ABSPATH . WPINC . '/template-loader.php';
        exit;
    }

    private static function rawRequestUri(): string
    {
        return rawurldecode((string) ($_SERVER['REQUEST_URI'] ?? ''));
    }

    private static function requestUriPath(): string
    {
        $path = wp_parse_url(self::rawRequestUri(), PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    private static function requestPath(): string
    {
        return untrailingslashit(self::requestUriPath());
    }

    private static function withTrailingSlash(string $value): string
    {
        $structure = (string) get_option('permalink_structure');

        return str_ends_with($structure, '/') ? trailingslashit($value) : untrailingslashit($value);
    }
}
