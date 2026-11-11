<?php

namespace Beam\Core\Modules\AcfJsonSync;

defined('ABSPATH') || exit;

final class WpCli
{
    public static function register(): void
    {
        if (! class_exists('WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('acf sync', [ self::class, 'sync' ]);
    }

    public static function sync(): void
    {
        if (! function_exists('acf_get_local_json_files')) {
            \WP_CLI::error('ACF не активен.');
        }

        $postType = 'acf-field-group';
        $files    = acf_get_local_json_files($postType);

        if ($files === []) {
            \WP_CLI::success('JSON-файлы групп полей не найдены.');

            return;
        }

        acf_update_setting('json', false);

        $posts  = acf_get_internal_post_type_posts($postType);
        $ids    = [];
        $synced = [];

        foreach ($posts as $post) {
            $key = $post['key'] ?? '';

            if ('' !== $key && ! empty($post['ID'])) {
                $ids[ $key ] = (int) $post['ID'];
            }
        }

        foreach ($files as $key => $file) {
            $localPost = json_decode((string) file_get_contents($file), true);

            if (! is_array($localPost)) {
                \WP_CLI::warning(sprintf('Не удалось прочитать JSON: %s', $file));
                continue;
            }

            $localPost['ID'] = $ids[ $key ] ?? 0;
            $result          = acf_import_internal_post_type($localPost, $postType);

            if (is_array($result) && ! empty($result['title'])) {
                $synced[] = (string) $result['title'];
            }
        }

        acf_update_setting('json', true);

        if ($synced === []) {
            \WP_CLI::success('Нет групп для синхронизации.');

            return;
        }

        \WP_CLI::success('Синхронизировано: ' . implode(', ', $synced));
    }
}
