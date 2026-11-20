<?php

namespace Beam\Core\Modules\Blocks;

defined('ABSPATH') || exit;

final class Renderer
{
    /** @var list<string> */
    private static array $stack = [];

    public static function current(): string
    {
        return self::$stack === [] ? '' : self::$stack[ count(self::$stack) - 1 ];
    }

    public static function bridge(): string
    {
        return __DIR__ . '/render-bridge.php';
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function present(
        array $block,
        string $content,
        bool $isPreview,
        mixed $postId,
        mixed $wpBlock,
        mixed $context,
    ): void {
        $name = isset($block['name']) && is_string($block['name']) ? $block['name'] : '';
        $found = $name !== '' ? Catalog::findByName($name) : null;

        if ($found === null) {
            return;
        }

        if ($isPreview && self::requiredFieldsEmpty($block)) {
            $title = isset($block['title']) && is_string($block['title']) ? $block['title'] : (string) $found['title'];
            echo self::placeholder($title); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            return;
        }

        $template = isset($found['templatePath']) && is_string($found['templatePath']) ? $found['templatePath'] : '';

        if ($template === '' || ! is_file($template)) {
            return;
        }

        self::enqueue($name);

        $is_preview = $isPreview;
        $post_id = $postId;
        $wp_block = $wpBlock;
        $render = static function () use ($template, $block, $content, $is_preview, $post_id, $wp_block, $context): void {
            include $template;
        };
        self::$stack[] = $name;

        try {
            $render();
        } finally {
            array_pop(self::$stack);
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $args
     */
    public static function markup(string $slug, array $fields = [], array $args = []): string
    {
        $name = self::blockName($slug);

        if ($name === '' || ! function_exists('acf_rendered_block')) {
            return '';
        }

        if (! \WP_Block_Type_Registry::get_instance()->is_registered($name)) {
            return '';
        }

        $postId = isset($args['post_id']) ? (int) $args['post_id'] : (int) get_the_ID();
        $attributes = [
            'name'  => $name,
            'data'  => self::fieldData($name, $fields),
            'mode'  => 'preview',
            'align' => isset($args['align']) && is_string($args['align']) ? $args['align'] : '',
        ];

        if (isset($args['className']) && is_string($args['className'])) {
            $attributes['className'] = $args['className'];
        }

        if (isset($args['anchor']) && is_string($args['anchor'])) {
            $attributes['anchor'] = $args['anchor'];
        }

        $content = isset($args['content']) && is_string($args['content']) ? $args['content'] : '';
        $html = acf_rendered_block($attributes, $content, ! empty($args['is_preview']), $postId);

        return is_string($html) ? $html : '';
    }

    public static function placeholder(string $title): string
    {
        if ($title === '') {
            $title = 'Блок';
        }

        $style = 'padding:16px;border:1px dashed #c3c4c7;border-radius:4px;background:#f6f7f7;color:#1d2327;';

        return '<div class="beam-block-placeholder" style="' . esc_attr($style) . '">'
            . '<p class="beam-block-placeholder__title" style="margin:0 0 8px;font-size:16px;line-height:24px;font-weight:600;">' . esc_html($title) . '</p>'
            . '<p class="beam-block-placeholder__text" style="margin:0;font-size:16px;line-height:24px;">Заполните обязательные поля, чтобы увидеть блок.</p>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function requiredFieldsEmpty(array $block): bool
    {
        if (! function_exists('acf_get_block_fields') || ! function_exists('get_field') || ! function_exists('acf_is_empty')) {
            return false;
        }

        $fields = acf_get_block_fields($block);

        if (! is_array($fields)) {
            return false;
        }

        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['required'])) {
                continue;
            }

            $fieldName = $field['name'] ?? '';

            if (! is_string($fieldName) || $fieldName === '') {
                continue;
            }

            if (acf_is_empty(get_field($fieldName))) {
                return true;
            }
        }

        return false;
    }

    private static function blockName(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if ($slug === '') {
            return '';
        }

        if (! str_contains($slug, '/')) {
            return 'acf/' . $slug;
        }

        return $slug;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function fieldData(string $name, array $fields): array
    {
        $data = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key) || $key === '' || str_starts_with($key, '_')) {
                continue;
            }

            $data[ $key ] = $value;
        }

        foreach (Fields::keysFor($name) as $fieldName => $fieldKey) {
            if (array_key_exists($fieldName, $data)) {
                $data[ '_' . $fieldName ] = $fieldKey;
            }
        }

        return $data;
    }

    private static function enqueue(string $name): void
    {
        $registry = \WP_Block_Type_Registry::get_instance();

        if (! $registry->is_registered($name)) {
            return;
        }

        $type = $registry->get_registered($name);

        if (! $type instanceof \WP_Block_Type) {
            return;
        }

        foreach (self::handles($type, 'style_handles') as $handle) {
            wp_enqueue_style($handle);
        }

        foreach (self::handles($type, 'view_style_handles') as $handle) {
            wp_enqueue_style($handle);
        }

        foreach (self::handles($type, 'script_handles') as $handle) {
            wp_enqueue_script($handle);
        }

        foreach (self::handles($type, 'view_script_handles') as $handle) {
            wp_enqueue_script($handle);
        }
    }

    /**
     * @return list<string>
     */
    private static function handles(object $type, string $property): array
    {
        if (! isset($type->{$property}) || ! is_array($type->{$property})) {
            return [];
        }

        $handles = [];

        foreach ($type->{$property} as $handle) {
            if (is_string($handle) && $handle !== '') {
                $handles[] = $handle;
            }
        }

        return $handles;
    }
}
