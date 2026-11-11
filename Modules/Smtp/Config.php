<?php

defined('ABSPATH') || exit;

final class BeamSmtpConfig
{
    public const OPTION_KEY = 'beam_smtp_settings';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'enabled'    => false,
        'host'       => '',
        'port'       => 587,
        'encryption' => 'tls',
        'auth'       => true,
        'username'   => '',
        'password'   => '',
        'from_email' => '',
        'from_name'  => '',
        'force_from' => true,
        'lead_email' => '',
    ];

    /**
     * @var array<string, string>
     */
    private const CONSTANT_MAP = [
        'enabled'    => 'BEAM_SMTP_ENABLED',
        'host'       => 'BEAM_SMTP_HOST',
        'port'       => 'BEAM_SMTP_PORT',
        'encryption' => 'BEAM_SMTP_ENCRYPTION',
        'auth'       => 'BEAM_SMTP_AUTH',
        'username'   => 'BEAM_SMTP_USER',
        'password'   => 'BEAM_SMTP_PASS',
        'from_email' => 'BEAM_SMTP_FROM_EMAIL',
        'from_name'  => 'BEAM_SMTP_FROM_NAME',
        'force_from' => 'BEAM_SMTP_FORCE_FROM',
        'lead_email' => 'BEAM_SMTP_LEAD_EMAIL',
    ];

    public static function is_enabled(): bool
    {
        return (bool) self::get('enabled');
    }

    public static function is_configured(): bool
    {
        return '' !== trim((string) self::get('host'));
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

        return $settings;
    }

    public static function get(string $key): mixed
    {
        $settings = self::get_all();

        return $settings[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function get_lead_email(): string
    {
        $email = sanitize_email((string) self::get('lead_email'));

        if ('' !== $email) {
            return $email;
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function update(array $input): bool
    {
        $current  = get_option(self::OPTION_KEY, []);
        $settings = wp_parse_args(is_array($current) ? $current : [], self::DEFAULTS);

        $submitted = [
            'enabled'    => ! empty($input['enabled']),
            'host'       => sanitize_text_field((string) ($input['host'] ?? '')),
            'port'       => min(65535, max(1, (int) ($input['port'] ?? 587))),
            'encryption' => self::sanitize_encryption((string) ($input['encryption'] ?? 'tls')),
            'auth'       => ! empty($input['auth']),
            'username'   => sanitize_text_field((string) ($input['username'] ?? '')),
            'from_email' => sanitize_email((string) ($input['from_email'] ?? '')),
            'from_name'  => sanitize_text_field((string) ($input['from_name'] ?? '')),
            'force_from' => ! empty($input['force_from']),
            'lead_email' => sanitize_email((string) ($input['lead_email'] ?? '')),
        ];

        foreach ($submitted as $key => $value) {
            if (! self::is_constant_defined($key)) {
                $settings[$key] = $value;
            }
        }

        if (! self::is_constant_defined('password') && '' !== (string) ($input['password'] ?? '')) {
            $settings['password'] = (string) $input['password'];
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
            'enabled', 'auth', 'force_from' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'port'                          => min(65535, max(1, (int) $value)),
            'encryption'                    => self::sanitize_encryption((string) $value),
            'from_email', 'lead_email'      => sanitize_email((string) $value),
            default                         => is_scalar($value) ? (string) $value : $stored,
        };
    }

    private static function sanitize_encryption(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'ssl', 'tls'], true) ? $value : 'tls';
    }
}
