<?php

namespace Beam\Core\Modules\Blocks;

defined('ABSPATH') || exit;

final class WpCli
{
    public static function register(): void
    {
        if (! class_exists('WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('beam blocks list', [self::class, 'listBlocks']);
        \WP_CLI::add_command('beam blocks make', [self::class, 'make']);
    }

    /**
     * Показывает найденные блоки.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Формат вывода. По умолчанию table.
     *
     * ## EXAMPLES
     *
     *     wp beam blocks list
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public static function listBlocks(array $args, array $assocArgs): void
    {
        unset($args);

        $blocks = Catalog::blocks();

        if ($blocks === []) {
            \WP_CLI::log('Блоки не найдены.');

            return;
        }

        usort(
            $blocks,
            static fn (array $left, array $right): int => strcmp((string) $left['slug'], (string) $right['slug']),
        );

        $rows = [];

        foreach ($blocks as $block) {
            $rows[] = [
                'slug'     => (string) $block['slug'],
                'source'   => self::sourceLabel((string) $block['source']),
                'template' => self::sourceLabel((string) $block['templateSource']),
                'path'     => (string) $block['path'],
            ];
        }

        $format = isset($assocArgs['format']) && is_string($assocArgs['format']) && $assocArgs['format'] !== ''
            ? $assocArgs['format']
            : 'table';

        \WP_CLI\Utils\format_items($format, $rows, ['slug', 'source', 'template', 'path']);
    }

    /**
     * Создаёт каркас блока в папке blocks активной темы.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Слаг блока. Строчные латинские буквы, цифры и дефис.
     *
     * ## EXAMPLES
     *
     *     wp beam blocks make hero
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public static function make(array $args, array $assocArgs): void
    {
        unset($assocArgs);

        $slug = isset($args[0]) ? strtolower(trim($args[0])) : '';

        if (str_contains($slug, '/')) {
            $slug = substr($slug, (int) strrpos($slug, '/') + 1);
        }

        $slug = sanitize_title($slug);

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            \WP_CLI::error('Слаг может содержать только строчные латинские буквы, цифры и дефис.');
        }

        $directory = get_stylesheet_directory() . '/blocks/' . $slug;

        if (is_dir($directory)) {
            $existing = array_diff(scandir($directory) ?: [], ['.', '..']);

            if ($existing !== []) {
                \WP_CLI::error('Папка блока уже есть: ' . $directory);
            }
        } elseif (! wp_mkdir_p($directory)) {
            \WP_CLI::error('Не удалось создать папку: ' . $directory);
        }

        $files = [
            'block.json'  => self::blockJson($slug),
            'render.php'  => self::renderPhp($slug),
            'fields.json' => self::fieldsJson($slug),
        ];

        foreach ($files as $name => $contents) {
            $path = $directory . '/' . $name;

            if (file_put_contents($path, $contents) === false) {
                \WP_CLI::error('Не удалось записать файл: ' . $path);
            }
        }

        \WP_CLI::success('Создан блок ' . $slug . ' в ' . $directory);
    }

    private static function sourceLabel(string $source): string
    {
        return $source === Catalog::SOURCE_CORE ? 'ядро' : 'тема';
    }

    private static function blockJson(string $slug): string
    {
        $title = self::title($slug);
        $encoded = json_encode(
            [
                '$schema'     => 'https://schemas.wp.org/trunk/block.json',
                'apiVersion'  => 3,
                'name'        => 'acf/' . $slug,
                'title'       => $title,
                'category'    => Registrar::CATEGORY,
                'icon'        => 'layout',
                'description' => '',
                'keywords'    => [],
                'acf'         => [
                    'mode'           => 'preview',
                    'renderTemplate' => 'render.php',
                    'blockVersion'   => 3,
                ],
                'supports'    => [
                    'anchor' => true,
                    'align'  => false,
                    'mode'   => false,
                    'jsx'    => true,
                ],
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if (! is_string($encoded)) {
            \WP_CLI::error('Не удалось собрать block.json.');

            return '';
        }

        return $encoded . "\n";
    }

    private static function renderPhp(string $slug): string
    {
        $template = <<<'PHP'
<?php

defined('ABSPATH') || exit;

$title = (string) beam_field('title');
?>
<section class="<?= esc_attr('{{slug}}') ?>">
<?php if ($title !== '') : ?>
    <p class="block-title"><?= esc_html($title) ?></p>
<?php endif; ?>
</section>
PHP;

        return str_replace('{{slug}}', $slug, $template) . "\n";
    }

    private static function fieldsJson(string $slug): string
    {
        $machine = str_replace('-', '_', $slug);
        $title = self::title($slug);
        $encoded = json_encode(
            [
                'key'                   => 'group_beam_' . $machine,
                'title'                 => $title,
                'fields'                => [
                    [
                        'key'           => 'field_beam_' . $machine . '_title',
                        'label'         => 'Заголовок',
                        'name'          => 'title',
                        'type'          => 'text',
                        'instructions'  => '',
                        'required'      => 1,
                        'default_value' => '',
                        'placeholder'   => '',
                    ],
                ],
                'location'              => [
                    [
                        [
                            'param'    => 'block',
                            'operator' => '==',
                            'value'    => 'acf/' . $slug,
                        ],
                    ],
                ],
                'menu_order'            => 0,
                'position'              => 'normal',
                'style'                 => 'default',
                'label_placement'       => 'top',
                'instruction_placement' => 'label',
                'active'                => true,
                'description'           => '',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if (! is_string($encoded)) {
            \WP_CLI::error('Не удалось собрать fields.json.');

            return '';
        }

        return $encoded . "\n";
    }

    private static function title(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
