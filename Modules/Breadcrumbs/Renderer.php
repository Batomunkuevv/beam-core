<?php

namespace Beam\Core\Modules\Breadcrumbs;

if (! defined('ABSPATH')) {
    exit;
}

final class Renderer
{
    public static function render(array $items, array $args = []): string
    {
        $items = array_values(
            array_filter(
                $items,
                static fn($item): bool => is_array($item) && '' !== trim((string) ($item['label'] ?? '')),
            ),
        );

        if ([] === $items) {
            return '';
        }

        $args = self::args($args);

        $navAttributes = [
            'class'      => $args['class'],
            'aria-label' => $args['aria_label'],
        ];

        if ($args['schema']) {
            $navAttributes['itemscope'] = true;
            $navAttributes['itemtype']  = 'https://schema.org/BreadcrumbList';
        }

        $html  = '<nav' . self::attributes($navAttributes) . '>';
        $html .= '<ol class="' . esc_attr($args['list_class']) . '">';

        foreach ($items as $index => $item) {
            $isCurrent = array_key_last($items) === $index;

            if ($index > 0) {
                $html .= self::separatorItem($args);
            }

            $html .= self::item($item, $index + 1, $isCurrent, $args);
        }

        $html .= '</ol>';
        $html .= '</nav>';

        return $html;
    }

    private static function args(array $args): array
    {
        $defaults = [
            'class'              => 'breadcrumbs',
            'list_class'         => 'breadcrumbs__list',
            'item_class'         => 'breadcrumbs__item',
            'link_class'         => 'breadcrumbs__link',
            'current_class'      => 'breadcrumbs__current',
            'separator_class'    => 'breadcrumbs__separator',
            'separator'          => '/',
            'separator_callback' => null,
            'aria_label'         => __('Хлебные крошки', 'breadcrumbs'),
            'schema'             => true,
        ];

        $args = array_merge($defaults, $args);
        $args = apply_filters('breadcrumbs_render_args', $args);

        return is_array($args) ? array_merge($defaults, $args) : $defaults;
    }

    private static function item(array $item, int $position, bool $isCurrent, array $args): string
    {
        $label = (string) ($item['label'] ?? '');
        $url   = (string) ($item['url'] ?? '');

        $attributes = [
            'class' => $args['item_class'],
        ];

        if ($args['schema']) {
            $attributes['itemprop']  = 'itemListElement';
            $attributes['itemscope'] = true;
            $attributes['itemtype']  = 'https://schema.org/ListItem';
        }

        $html = '<li' . self::attributes($attributes) . '>';

        if (! $isCurrent && '' !== $url) {
            $html .= '<a class="' . esc_attr($args['link_class']) . '" href="' . esc_url($url) . '"' . ($args['schema'] ? ' itemprop="item"' : '') . '>';
            $html .= '<span' . ($args['schema'] ? ' itemprop="name"' : '') . '>' . esc_html($label) . '</span>';
            $html .= '</a>';
        } else {
            $html .= '<span class="' . esc_attr($args['current_class']) . '" aria-current="page"' . ($args['schema'] ? ' itemprop="name"' : '') . '>' . esc_html($label) . '</span>';
        }

        if ($args['schema']) {
            $html .= '<meta itemprop="position" content="' . esc_attr((string) $position) . '">';
        }

        $html .= '</li>';

        return $html;
    }

    private static function separatorItem(array $args): string
    {
        $separator = '';

        if (is_callable($args['separator_callback'])) {
            $separator = (string) call_user_func($args['separator_callback']);
        } else {
            $separator = esc_html((string) $args['separator']);
        }

        if ('' === $separator) {
            return '';
        }

        return '<li class="' . esc_attr($args['separator_class']) . '" aria-hidden="true">' . wp_kses($separator, self::separatorAllowedHtml()) . '</li>';
    }

    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if (false === $value || null === $value || '' === $value) {
                continue;
            }

            if (true === $value) {
                $html .= ' ' . esc_attr((string) $name);
                continue;
            }

            $html .= ' ' . esc_attr((string) $name) . '="' . esc_attr((string) $value) . '"';
        }

        return $html;
    }

    private static function separatorAllowedHtml(): array
    {
        return [
            'svg'  => [
                'class'       => true,
                'width'       => true,
                'height'      => true,
                'viewbox'     => true,
                'viewBox'     => true,
                'fill'        => true,
                'xmlns'       => true,
                'aria-hidden' => true,
                'role'        => true,
                'focusable'   => true,
            ],
            'path' => [
                'd'            => true,
                'fill'         => true,
                'fill-opacity' => true,
            ],
            'span' => [
                'class'       => true,
                'aria-hidden' => true,
            ],
        ];
    }
}
