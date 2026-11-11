<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class LoginLimitStatus
{
    public const TRANSIENT = 'beam_security_login_limit_status';
    public const TTL = HOUR_IN_SECONDS;
    public const ATTEMPTS = 6;

    /**
     * @return array{status: string, code: int, checked_at: int, url: string, path: string, attempts: int}
     */
    public static function get(bool $force = false): array
    {
        if (! $force) {
            $cached = get_transient(self::TRANSIENT);
            if (is_array($cached) && isset($cached['status'])) {
                return self::normalize($cached);
            }

            return self::normalize([]);
        }

        $result = self::probe();
        set_transient(self::TRANSIENT, $result, self::TTL);

        return $result;
    }

    public static function clear(): void
    {
        delete_transient(self::TRANSIENT);
    }

    public static function loginUrl(): string
    {
        if (Config::isHideLoginActive()) {
            return HideLogin::loginUrl();
        }

        return site_url('wp-login.php', 'login');
    }

    public static function loginPath(): string
    {
        $path = wp_parse_url(self::loginUrl(), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/wp-login.php';
    }

    public static function nginxSnippet(): string
    {
        $path = self::loginPath();
        $isPhp = str_ends_with($path, '.php');
        $script = $isPhp
            ? '$document_root$fastcgi_script_name'
            : '$document_root/index.php';
        $lines = [
            '# в блоке http:',
            'limit_req_zone $binary_remote_addr zone=wp_login:10m rate=1r/s;',
            '',
        ];

        if ($isPhp) {
            $lines[] = '# в блоке server (fastcgi_pass и SCRIPT_FILENAME подставьте свои):';
        } else {
            $lines[] = '# в блоке server (fastcgi_pass подставьте свой).';
            $lines[] = '# Лимит ставьте на публичный адрес входа, а не на wp-login.php:';
            $lines[] = '# nginx видит исходный URL, а wp-login.php подключается уже в PHP.';
        }

        $lines[] = 'location = ' . $path . ' {';
        $lines[] = '    limit_req zone=wp_login burst=5 nodelay;';
        $lines[] = '    limit_req_status 429;';
        $lines[] = '    include fastcgi_params;';
        $lines[] = '    fastcgi_param SCRIPT_FILENAME ' . $script . ';';
        $lines[] = '    fastcgi_pass unix:/run/php/php-fpm.sock;';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    public static function fail2banSnippet(): string
    {
        $path = preg_quote(self::loginPath(), '#');

        $lines = [
            '# /etc/fail2ban/filter.d/wordpress-login.conf',
            '[INCLUDES]',
            'before = common.conf',
            '',
            '[Definition]',
            '_daemon = beam-wp',
            'failregex = ^%(__prefix_line)sAuthentication failure for .* from <HOST>\s*$',
            'ignoreregex =',
            '',
            '# /etc/fail2ban/jail.local',
            '# Debian/Ubuntu: /var/log/auth.log; RHEL: /var/log/secure',
            '[wordpress-login]',
            'enabled = true',
            'filter = wordpress-login',
            'logpath = /var/log/auth.log',
            'backend = auto',
            'maxretry = 5',
            'findtime = 60',
            'bantime = 3600',
            '',
            '# опционально: 429 от nginx limit_req',
            '# /etc/fail2ban/filter.d/wordpress-login-limit.conf',
            '# [Definition]',
            '# failregex = ^<HOST> .+ "(GET|POST) ' . $path . '(?:/|\\?| )\\S* HTTP/[\\d.]+" 429',
            '# ignoreregex =',
            '#',
            '# /etc/fail2ban/jail.local',
            '# [wordpress-login-limit]',
            '# enabled = true',
            '# filter = wordpress-login-limit',
            '# logpath = /var/log/nginx/access.log',
            '# maxretry = 5',
            '# findtime = 60',
            '# bantime = 3600',
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array{status: string, code: int, checked_at: int, url: string, path: string, attempts: int}
     */
    private static function probe(): array
    {
        $url = self::loginUrl();
        $path = self::loginPath();
        $codes = [];
        $limited = false;
        $reached = false;

        $body = [
            'log' => 'beam_probe_' . wp_generate_password(8, false),
            'pwd' => wp_generate_password(16, true, true),
            'wp-submit' => 'Log In',
            'testcookie' => '1',
        ];

        for ($i = 0; $i < self::ATTEMPTS; $i++) {
            $response = wp_remote_post(
                $url,
                [
                    'timeout' => 5,
                    'redirection' => 0,
                    'sslverify' => Security::sslVerify($url, 'beam_security_login_limit_sslverify'),
                    'headers' => [
                        'Cookie' => 'wordpress_test_cookie=WP%20Cookie%20check',
                    ],
                    'body' => $body,
                    'user-agent' => 'BE.AM Core login limit probe',
                ]
            );

            if (is_wp_error($response)) {
                continue;
            }

            $reached = true;
            $code = (int) wp_remote_retrieve_response_code($response);
            $codes[] = $code;

            if (in_array($code, [429, 503], true)) {
                $limited = true;
                break;
            }
        }

        if ($limited) {
            $status = 'limited';
        } elseif ($reached) {
            $status = 'open';
        } else {
            $status = 'unknown';
        }

        return [
            'status' => $status,
            'code' => $codes !== [] ? (int) $codes[array_key_last($codes)] : 0,
            'checked_at' => time(),
            'url' => $url,
            'path' => $path,
            'attempts' => count($codes),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: string, code: int, checked_at: int, url: string, path: string, attempts: int}
     */
    private static function normalize(array $data): array
    {
        $status = (string) ($data['status'] ?? 'unchecked');
        if (! in_array($status, ['limited', 'open', 'unknown', 'unchecked'], true)) {
            $status = 'unchecked';
        }

        return [
            'status' => $status,
            'code' => (int) ($data['code'] ?? 0),
            'checked_at' => (int) ($data['checked_at'] ?? 0),
            'url' => is_string($data['url'] ?? null) ? $data['url'] : self::loginUrl(),
            'path' => is_string($data['path'] ?? null) ? $data['path'] : self::loginPath(),
            'attempts' => (int) ($data['attempts'] ?? 0),
        ];
    }
}
