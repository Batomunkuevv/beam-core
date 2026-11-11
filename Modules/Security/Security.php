<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class Security
{
    public static function register(): void
    {
        if (Config::isEnabled('hide_user_logins')) {
            add_action('template_redirect', [self::class, 'blockAuthorArchives'], 0);
            add_filter('rest_endpoints', [self::class, 'filterRestEndpoints']);
            add_filter('rest_pre_dispatch', [self::class, 'blockUsersRest'], 10, 3);
            add_filter('oembed_response_data', [self::class, 'stripOembedAuthor']);
            add_filter('wp_sitemaps_add_provider', [self::class, 'removeUsersSitemap'], 10, 2);
        }

        if (Config::isEnabled('generic_login_errors')) {
            add_filter('login_errors', [self::class, 'genericLoginError']);
        }
    }

    public static function blockAuthorArchives(): void
    {
        if (is_admin() || is_user_logged_in()) {
            return;
        }

        if (! apply_filters('beam_security_block_author_archives', true)) {
            return;
        }

        $authorQuery = get_query_var('author');
        $authorName = get_query_var('author_name');
        $requested = isset($_GET['author']) && (string) wp_unslash($_GET['author']) !== '';

        if (! is_author() && ! $requested && ($authorQuery === '' || $authorQuery === false || $authorQuery === null) && $authorName === '') {
            return;
        }

        global $wp_query;
        $wp_query->set_404();
        $wp_query->is_author = false;
        $wp_query->is_archive = false;
        $wp_query->is_home = false;
        $wp_query->is_404 = true;
        status_header(404);
        nocache_headers();
    }

    /**
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public static function filterRestEndpoints(array $endpoints): array
    {
        if (is_user_logged_in()) {
            return $endpoints;
        }

        foreach (array_keys($endpoints) as $route) {
            if (self::isUsersRoute((string) $route)) {
                unset($endpoints[$route]);
            }
        }

        return $endpoints;
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public static function blockUsersRest(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        if ($result !== null || is_user_logged_in()) {
            return $result;
        }

        if (! self::isUsersRoute($request->get_route())) {
            return $result;
        }

        return new \WP_Error(
            'rest_forbidden',
            'Доступ запрещён.',
            ['status' => 401]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function stripOembedAuthor(array $data): array
    {
        unset($data['author_name'], $data['author_url']);

        return $data;
    }

    /**
     * @param mixed $provider
     * @return mixed
     */
    public static function removeUsersSitemap(mixed $provider, string $name): mixed
    {
        return $name === 'users' ? false : $provider;
    }

    public static function genericLoginError(string $error): string
    {
        return 'Неверный логин или пароль';
    }

    private static function isUsersRoute(string $route): bool
    {
        return (bool) preg_match('#^/wp/v2/users(?:/|$)#', $route);
    }
}
