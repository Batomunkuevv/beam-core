<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class XmlrpcStatus
{
    public const TRANSIENT = 'beam_security_xmlrpc_status';
    public const TTL = HOUR_IN_SECONDS;

    /**
     * @return array{status: string, code: int, checked_at: int, url: string, path: string}
     */
    public static function get(bool $force = false): array
    {
        if (! $force) {
            $cached = get_transient(self::TRANSIENT);
            if (is_array($cached) && isset($cached['status'])) {
                return self::normalize($cached);
            }
        }

        $result = self::probe();
        set_transient(self::TRANSIENT, $result, self::TTL);

        return $result;
    }

    public static function xmlrpcUrl(): string
    {
        return site_url('xmlrpc.php');
    }

    public static function xmlrpcPath(): string
    {
        $path = wp_parse_url(self::xmlrpcUrl(), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/xmlrpc.php';
    }

    public static function nginxSnippet(): string
    {
        return 'location = ' . self::xmlrpcPath() . ' { deny all; }';
    }

    /**
     * @return array{status: string, code: int, checked_at: int, url: string, path: string}
     */
    private static function probe(): array
    {
        $url = self::xmlrpcUrl();
        $body = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';
        $response = wp_remote_post(
            $url,
            [
                'timeout' => 3,
                'redirection' => 0,
                'sslverify' => apply_filters('beam_security_xmlrpc_sslverify', false),
                'headers' => [
                    'Content-Type' => 'text/xml',
                ],
                'body' => $body,
            ]
        );

        $code = 0;
        $payload = '';
        if (is_wp_error($response)) {
            $status = 'unknown';
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);
            $payload = (string) wp_remote_retrieve_body($response);
            $status = self::classify($code, $payload);
        }

        return [
            'status' => $status,
            'code' => $code,
            'checked_at' => time(),
            'url' => $url,
            'path' => self::xmlrpcPath(),
        ];
    }

    public static function classify(int $code, string $body): string
    {
        if (in_array($code, [401, 403, 404, 405, 410, 421, 451, 501], true)) {
            return 'closed';
        }

        if ($code >= 200 && $code < 300) {
            if (stripos($body, 'XML-RPC services are disabled') !== false) {
                return 'closed';
            }

            if (str_contains($body, 'faultCode') && stripos($body, 'disabled') !== false) {
                return 'closed';
            }

            if (str_contains($body, 'methodResponse') && str_contains($body, 'fault')) {
                return 'closed';
            }

            if (str_contains($body, 'methodResponse') || stripos($body, 'system.listMethods') !== false || str_contains($body, '<methodName>')) {
                return 'open';
            }

            return 'open';
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: string, code: int, checked_at: int, url: string, path: string}
     */
    private static function normalize(array $data): array
    {
        $status = (string) ($data['status'] ?? 'unknown');
        if (! in_array($status, ['open', 'closed', 'unknown'], true)) {
            $status = 'unknown';
        }

        return [
            'status' => $status,
            'code' => (int) ($data['code'] ?? 0),
            'checked_at' => (int) ($data['checked_at'] ?? 0),
            'url' => is_string($data['url'] ?? null) ? $data['url'] : self::xmlrpcUrl(),
            'path' => is_string($data['path'] ?? null) ? $data['path'] : self::xmlrpcPath(),
        ];
    }
}
