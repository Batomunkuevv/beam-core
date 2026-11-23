<?php

namespace Beam\Core\Modules\Bitrix24;

defined('ABSPATH') || exit;

final class Log
{
    public const OPTION = 'beam_bitrix24_log';

    private const LIMIT = 20;

    public static function add(string $method, Result $result): void
    {
        if (! apply_filters('beam_bitrix24_log_enabled', true, $method, $result)) {
            return;
        }

        $entries = self::entries();
        array_unshift($entries, [
            'time'   => time(),
            'method' => $method,
            'ok'     => $result->ok,
            'id'     => $result->id(),
            'error'  => $result->error,
        ]);

        $entries = array_slice($entries, 0, self::LIMIT);

        if (get_option(self::OPTION, null) === null) {
            add_option(self::OPTION, $entries, '', false);

            return;
        }

        update_option(self::OPTION, $entries, false);
    }

    /**
     * @return list<array{time: int, method: string, ok: bool, id: int|null, error: string}>
     */
    public static function entries(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            return [];
        }

        $entries = [];

        foreach ($stored as $entry) {
            if (! is_array($entry) || ! isset($entry['time'], $entry['method'])) {
                continue;
            }

            if (! is_numeric($entry['time']) || ! is_string($entry['method'])) {
                continue;
            }

            $entries[] = [
                'time'   => (int) $entry['time'],
                'method' => $entry['method'],
                'ok'     => ! empty($entry['ok']),
                'id'     => isset($entry['id']) && is_numeric($entry['id']) ? (int) $entry['id'] : null,
                'error'  => isset($entry['error']) && is_string($entry['error']) ? $entry['error'] : '',
            ];
        }

        return array_slice($entries, 0, self::LIMIT);
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }
}
