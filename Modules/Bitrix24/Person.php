<?php

namespace Beam\Core\Modules\Bitrix24;

defined('ABSPATH') || exit;

final class Person
{
    private const PATRONYMIC = '/(ович|евич|ич|овна|евна|ична|инична|оглы|кызы)$/u';

    /**
     * «Иван», «Иван Иванов», «Иванов Иван Иванович», «Иван Иванович Иванов».
     *
     * @return array{NAME?: string, LAST_NAME?: string, SECOND_NAME?: string}
     */
    public static function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts) || $parts === []) {
            return [];
        }

        if (count($parts) === 1) {
            return ['NAME' => $parts[0]];
        }

        if (count($parts) === 2) {
            return self::isPatronymic($parts[1])
                ? ['NAME' => $parts[0], 'SECOND_NAME' => $parts[1]]
                : ['NAME' => $parts[0], 'LAST_NAME' => $parts[1]];
        }

        if (self::isPatronymic($parts[2])) {
            return ['NAME' => $parts[1], 'LAST_NAME' => $parts[0], 'SECOND_NAME' => implode(' ', array_slice($parts, 2))];
        }

        if (self::isPatronymic($parts[1])) {
            return ['NAME' => $parts[0], 'LAST_NAME' => implode(' ', array_slice($parts, 2)), 'SECOND_NAME' => $parts[1]];
        }

        return ['NAME' => $parts[0], 'LAST_NAME' => implode(' ', array_slice($parts, 1))];
    }

    /**
     * Российский номер в виде 7XXXXXXXXXX (8 и +7 приводятся к 7, десять цифр с 9 получают 7).
     * Другие номера от 10 до 15 цифр остаются цифрами, остальное — пустая строка.
     */
    public static function phone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        $length = strlen($digits);

        if ($length === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
            return '7' . substr($digits, 1);
        }

        if ($length === 10 && $digits[0] === '9') {
            return '7' . $digits;
        }

        return $length >= 10 && $length <= 15 ? $digits : '';
    }

    private static function isPatronymic(string $word): bool
    {
        return preg_match(self::PATRONYMIC, mb_strtolower($word)) === 1;
    }
}
