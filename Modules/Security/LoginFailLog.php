<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class LoginFailLog
{
    private static bool $syslogOpen = false;

    public static function register(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        add_action('wp_login_failed', [self::class, 'log']);
    }

    public static function isEnabled(): bool
    {
        if (defined('BEAM_DISABLE_LOGIN_FAIL_LOG') && BEAM_DISABLE_LOGIN_FAIL_LOG) {
            return false;
        }

        return (bool) apply_filters('beam_security_log_login_failures', true);
    }

    public static function log(string $username): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $message = sprintf(
            'Authentication failure for %s from %s',
            self::sanitizeUsername($username),
            self::clientIp()
        );

        if (! self::$syslogOpen) {
            if (! openlog('beam-wp', LOG_NDELAY | LOG_PID, LOG_AUTH)) {
                return;
            }
            self::$syslogOpen = true;
        }

        syslog(LOG_NOTICE, $message);
    }

    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $remote = is_string($remote) ? $remote : '';
        $filtered = apply_filters('beam_security_client_ip', $remote);

        if (is_string($filtered) && self::isValidIp($filtered)) {
            return $filtered;
        }

        return self::isValidIp($remote) ? $remote : '0.0.0.0';
    }

    private static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private static function sanitizeUsername(string $username): string
    {
        $username = str_replace(["\r", "\n", "\0"], '', $username);
        $username = trim($username);
        if ($username === '') {
            return '-';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($username, 0, 64);
        }

        return substr($username, 0, 64);
    }
}
