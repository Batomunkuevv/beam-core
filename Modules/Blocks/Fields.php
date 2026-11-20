<?php

namespace Beam\Core\Modules\Blocks;

defined('ABSPATH') || exit;

final class Fields
{
    /**
     * @param array<string, mixed> $block
     */
    public static function attach(array $block): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        self::loadFile($block);
    }

    /**
     * @return array<string, string>
     */
    public static function keysFor(string $name): array
    {
        $keys = [];

        foreach (self::groupsFor($name) as $group) {
            if (! function_exists('acf_get_fields')) {
                continue;
            }

            $fields = acf_get_fields($group);

            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldName = $field['name'] ?? '';
                $fieldKey = $field['key'] ?? '';

                if (! is_string($fieldName) || $fieldName === '' || ! is_string($fieldKey) || $fieldKey === '') {
                    continue;
                }

                $keys[ $fieldName ] = $fieldKey;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function loadFile(array $block): void
    {
        $path = $block['path'] . '/fields.json';

        if (! is_file($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return;
        }

        if (array_is_list($decoded)) {
            foreach ($decoded as $group) {
                if (is_array($group)) {
                    self::addGroup($group, $path, $block);
                }
            }

            return;
        }

        self::addGroup($decoded, $path, $block);
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, mixed> $block
     */
    private static function addGroup(array $group, string $path, array $block): void
    {
        $machine = str_replace('-', '_', (string) $block['slug']);

        if (! isset($group['key']) || ! is_string($group['key']) || $group['key'] === '') {
            $group['key'] = 'group_beam_' . $machine;
        }

        if (function_exists('acf_is_local_field_group') && acf_is_local_field_group($group['key'])) {
            return;
        }

        if (! isset($group['title']) || ! is_string($group['title']) || $group['title'] === '') {
            $group['title'] = (string) $block['title'];
        }

        if (! isset($group['fields']) || ! is_array($group['fields'])) {
            $group['fields'] = [];
        }

        if (! isset($group['location']) || ! is_array($group['location']) || $group['location'] === []) {
            $group['location'] = [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => (string) $block['name'],
                    ],
                ],
            ];
        }

        if (! isset($group['active'])) {
            $group['active'] = true;
        }

        $group['local'] = 'json';
        $group['local_file'] = wp_normalize_path($path);

        acf_add_local_field_group($group);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function groupsFor(string $name): array
    {
        if (! function_exists('acf_get_field_groups')) {
            return [];
        }

        $groups = acf_get_field_groups();

        if (! is_array($groups)) {
            return [];
        }

        $matched = [];

        foreach ($groups as $group) {
            if (! is_array($group) || empty($group['active'])) {
                continue;
            }

            if (self::targetsBlock($group, $name)) {
                $matched[] = $group;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $group
     */
    private static function targetsBlock(array $group, string $name): bool
    {
        $location = $group['location'] ?? null;

        if (! is_array($location)) {
            return false;
        }

        foreach ($location as $rules) {
            if (! is_array($rules)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (! is_array($rule) || ($rule['param'] ?? '') !== 'block' || ($rule['operator'] ?? '==') !== '==') {
                    continue;
                }

                $value = $rule['value'] ?? '';

                if ($value === $name || $value === 'all') {
                    return true;
                }
            }
        }

        return false;
    }
}
