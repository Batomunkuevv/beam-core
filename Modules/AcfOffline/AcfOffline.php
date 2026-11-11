<?php

defined('ABSPATH') || exit;

final class BeamAcfOffline
{
    public static function register(): void
    {
        add_filter('acf/settings/show_updates', '__return_false');
        add_filter('pre_http_request', [self::class, 'blockConnectRequest'], 10, 3);
        add_filter('pre_option_acf_pro_license', [self::class, 'offlineLicense']);
        add_filter('pre_option_acf_pro_license_status', [self::class, 'offlineLicenseStatus']);
        add_action('init', [self::class, 'removeLicenseHooks'], 100);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public static function blockConnectRequest(mixed $preempt, array $args, string $url): mixed
    {
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || 0 !== strcasecmp($host, 'connect.advancedcustomfields.com')) {
            return $preempt;
        }

        return new WP_Error('acf_offline', 'Запросы ACF на connect отключены.');
    }

    public static function offlineLicense(mixed $preempt): mixed
    {
        if (false !== $preempt) {
            return $preempt;
        }

        static $building = false;

        if ($building) {
            return false;
        }

        $building = true;
        $url = home_url();
        $building = false;

        return base64_encode(maybe_serialize([
            'key' => 'offline',
            'url' => $url,
        ]));
    }

    /**
     * @return array<string, mixed>|mixed
     */
    public static function offlineLicenseStatus(mixed $preempt): mixed
    {
        if (false !== $preempt) {
            return $preempt;
        }

        return [
            'status' => 'active',
            'created' => 0,
            'expiry' => 0,
            'name' => 'ACF PRO',
            'lifetime' => false,
            'refunded' => false,
            'view_licenses_url' => '',
            'manage_subscription_url' => '',
            'error_msg' => '',
            'next_check' => time() + YEAR_IN_SECONDS,
        ];
    }

    public static function removeLicenseHooks(): void
    {
        remove_action('admin_init', 'acf_pro_check_defined_license', 20);
        remove_action('admin_init', 'acf_pro_maybe_reactivate_license', 25);
        remove_action('current_screen', 'acf_pro_display_activation_error', 30);

        self::removeHookMethod('acf/in_admin_header', 'maybe_show_license_status_error');
        self::removeHookMethod('acf/internal_post_type/current_screen', 'invalid_license_redirect');
        self::removeHookMethod('acf/internal_post_type_list/current_screen', 'invalid_license_redirect_notice');
    }

    private static function removeHookMethod(string $hook, string $method): void
    {
        global $wp_filter;

        if (! isset($wp_filter[$hook])) {
            return;
        }

        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];

                if (! is_array($function) || ! is_object($function[0] ?? null)) {
                    continue;
                }

                if (($function[1] ?? null) !== $method) {
                    continue;
                }

                remove_action($hook, $function, (int) $priority);
            }
        }
    }
}
