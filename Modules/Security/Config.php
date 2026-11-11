<?php

namespace Beam\Core\Modules\Security;

defined('ABSPATH') || exit;

final class Config
{
    public const OPTION = 'beam_security_features';

    public const FEATURES = [
        'disallow_file_edit',
        'hide_user_logins',
        'generic_login_errors',
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

    /**
     * @param array<string, mixed> $input
     */
    public static function update(array $input): void
    {
        $features = self::defaults();
        foreach (self::FEATURES as $key) {
            $features[$key] = ! empty($input[$key]);
        }

        update_option(self::OPTION, $features, false);
    }
}
