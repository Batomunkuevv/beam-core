<?php

namespace Beam\Core\Modules\Bitrix24;

defined('ABSPATH') || exit;

final class Config
{
    public const OPTION = 'beam_bitrix24_webhook';

    public const CONSTANT = 'BITRIX24_WEBHOOK';

    private const PATTERN = '~^https://[a-z0-9-]+(?:\.[a-z0-9-]+)*\.bitrix24\.[a-z]{2,6}(?:\.[a-z]{2})?/rest/\d+/[a-z0-9]+/$~i';

    public static function webhook(): string
    {
        $environment = self::environmentWebhook();
        $url = $environment !== '' ? $environment : self::storedWebhook();
        $filtered = apply_filters('beam_bitrix24_webhook', $url);
        $url = is_string($filtered) ? self::normalize($filtered) : $url;

        return self::isValid($url) ? $url : '';
    }

    public static function isConfigured(): bool
    {
        return self::webhook() !== '';
    }

    public static function fromEnvironment(): bool
    {
        return self::environmentWebhook() !== '';
    }

    public static function storedWebhook(): string
    {
        $stored = get_option(self::OPTION, '');

        return is_string($stored) ? self::normalize($stored) : '';
    }

    public static function environmentWebhook(): string
    {
        if (defined(self::CONSTANT)) {
            $value = constant(self::CONSTANT);

            return is_string($value) ? self::normalize($value) : '';
        }

        foreach ([$_ENV[self::CONSTANT] ?? null, $_SERVER[self::CONSTANT] ?? null, getenv(self::CONSTANT)] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return self::normalize($value);
            }
        }

        return '';
    }

    public static function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('~^(https://[^/]+/rest/\d+/[a-z0-9]+)(?:/[a-z0-9_.]*)?$~i', $url, $match) === 1) {
            return $match[1] . '/';
        }

        return $url;
    }

    public static function isValid(string $url): bool
    {
        return preg_match(self::PATTERN, $url) === 1;
    }

    public static function mask(string $url): string
    {
        if (preg_match('~^(https://[^/]+/rest/\d+/)~i', $url, $match) !== 1) {
            return '';
        }

        return $match[1] . '••••/';
    }

    public static function save(string $url): bool
    {
        $url = self::normalize($url);

        if (! self::isValid($url)) {
            return false;
        }

        if (get_option(self::OPTION, null) === null) {
            return add_option(self::OPTION, $url, '', false);
        }

        update_option(self::OPTION, $url, false);

        return true;
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }
}
