<?php

namespace Beam\Core\Modules\Breadcrumbs;

use WP_Post;
use WP_Post_Type;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

final class Breadcrumbs
{
    public static function items(array $args = []): array
    {
        $args   = self::args($args);
        $labels = self::labels();
        $items  = [];

        if (is_front_page()) {
            if ($args['show_on_front']) {
                $items[] = self::item($labels['home'], home_url('/'), true);
            }

            return self::filteredItems($items, $args);
        }

        if ($args['show_home']) {
            $items[] = self::item($labels['home'], home_url('/'));
        }

        if (is_home()) {
            self::addHomeItems($items);
        } elseif (is_404()) {
            $items[] = self::item($labels['404'], '', true);
        } elseif (is_search()) {
            $items[] = self::item(sprintf($labels['search'], get_search_query()), '', true);
        } elseif (is_author()) {
            self::addAuthorItems($items, $labels);
        } elseif (is_date()) {
            self::addDateItems($items, $labels);
        } elseif (is_post_type_archive()) {
            self::addPostTypeArchiveItems($items);
        } elseif (is_tax() || is_category() || is_tag()) {
            self::addTermItems($items);
        } elseif (is_singular()) {
            self::addSingularItems($items);
        }

        self::addPaginationItem($items, $labels);

        return self::filteredItems($items, $args);
    }

    private static function args(array $args): array
    {
        $defaults = [
            'show_home'     => true,
            'show_on_front' => true,
        ];

        return array_merge($defaults, $args);
    }

    private static function labels(): array
    {
        $labels = [
            'home'          => __('Главная', 'breadcrumbs'),
            '404'           => __('Ошибка 404', 'breadcrumbs'),
            'search'        => __('Результаты поиска: %s', 'breadcrumbs'),
            'author'        => __('Автор: %s', 'breadcrumbs'),
            'year'          => __('Архив за %d год', 'breadcrumbs'),
            'month'         => __('Архив за %s', 'breadcrumbs'),
            'day'           => __('Архив за %s', 'breadcrumbs'),
            'page'          => __('Страница %d', 'breadcrumbs'),
            'post_type_tax' => [ 'category' ],
        ];

        $filtered = apply_filters('breadcrumbs_labels', $labels);

        return is_array($filtered) ? array_merge($labels, $filtered) : $labels;
    }

    private static function addHomeItems(array &$items): void
    {
        $page = get_queried_object();

        if ($page instanceof WP_Post) {
            $items[] = self::item(get_the_title($page), '', true);
        }
    }

    private static function addAuthorItems(array &$items, array $labels): void
    {
        $author = get_queried_object();

        if (is_object($author) && isset($author->display_name)) {
            $items[] = self::item(sprintf($labels['author'], $author->display_name), '', true);
        }
    }

    private static function addDateItems(array &$items, array $labels): void
    {
        $year = (int) get_query_var('year');

        if ($year <= 0) {
            $year = (int) get_the_time('Y');
        }

        if (is_year()) {
            $items[] = self::item(sprintf($labels['year'], $year), '', true);
            return;
        }

        $items[] = self::item((string) $year, get_year_link($year));

        $month = (int) get_query_var('monthnum');

        if ($month <= 0) {
            $month = (int) get_the_time('m');
        }

        $monthName = date_i18n('F', mktime(0, 0, 0, $month, 1, $year));

        if (is_month()) {
            $items[] = self::item(sprintf($labels['month'], $monthName), '', true);
            return;
        }

        $items[] = self::item($monthName, get_month_link($year, $month));

        if (is_day()) {
            $day = (int) get_query_var('day');
            $items[] = self::item(sprintf($labels['day'], date_i18n(get_option('date_format'), mktime(0, 0, 0, $month, $day, $year))), '', true);
        }
    }

    private static function addPostTypeArchiveItems(array &$items): void
    {
        $postType = get_queried_object();

        if ($postType instanceof WP_Post_Type) {
            $items[] = self::item($postType->labels->name, '', true);
        }
    }

    private static function addTermItems(array &$items): void
    {
        $term = get_queried_object();

        if (! $term instanceof WP_Term) {
            return;
        }

        self::addTaxonomyPostTypeArchive($items, $term);
        self::addTermAncestors($items, $term);

        $items[] = self::item($term->name, '', true);
    }

    private static function addTaxonomyPostTypeArchive(array &$items, WP_Term $term): void
    {
        $taxonomy = get_taxonomy($term->taxonomy);

        if (! $taxonomy || empty($taxonomy->object_type)) {
            return;
        }

        foreach ((array) $taxonomy->object_type as $postTypeName) {
            $postType = get_post_type_object($postTypeName);

            if (! $postType instanceof WP_Post_Type || ! $postType->has_archive || 'post' === $postType->name) {
                continue;
            }

            $archiveUrl = get_post_type_archive_link($postType->name);

            if (false !== $archiveUrl) {
                $items[] = self::item($postType->labels->name, $archiveUrl);
            }

            return;
        }
    }

    private static function addSingularItems(array &$items): void
    {
        $post = get_queried_object();

        if (! $post instanceof WP_Post) {
            return;
        }

        if (is_post_type_hierarchical($post->post_type)) {
            self::addPostAncestors($items, $post);
            $items[] = self::item(get_the_title($post), '', true);
            return;
        }

        self::addPostTypeArchive($items, $post->post_type);
        self::addPrimaryTermTrail($items, $post);

        $items[] = self::item(get_the_title($post), '', true);
    }

    private static function addPostAncestors(array &$items, WP_Post $post): void
    {
        $ancestors = array_reverse(get_post_ancestors($post));

        foreach ($ancestors as $ancestorId) {
            $items[] = self::item(get_the_title($ancestorId), get_permalink($ancestorId));
        }
    }

    private static function addPostTypeArchive(array &$items, string $postTypeName): void
    {
        $postType = get_post_type_object($postTypeName);

        if (! $postType instanceof WP_Post_Type || ! $postType->has_archive || 'post' === $postType->name) {
            return;
        }

        $archiveUrl = get_post_type_archive_link($postType->name);

        if (false !== $archiveUrl) {
            $items[] = self::item($postType->labels->name, $archiveUrl);
        }
    }

    private static function addPrimaryTermTrail(array &$items, WP_Post $post): void
    {
        $term = self::primaryTerm($post);

        if (! $term instanceof WP_Term) {
            return;
        }

        self::addTermAncestors($items, $term);
        $termUrl = self::termUrl($term);

        if ('' !== $termUrl) {
            $items[] = self::item($term->name, $termUrl);
        }
    }

    private static function primaryTerm(WP_Post $post): ?WP_Term
    {
        $taxonomyNames = get_object_taxonomies($post->post_type);
        $taxonomyNames = array_values(
            array_filter(
                $taxonomyNames,
                static function (string $taxonomyName): bool {
                    $taxonomy = get_taxonomy($taxonomyName);

                    return $taxonomy && $taxonomy->public && $taxonomy->hierarchical;
                },
            ),
        );

        $taxonomyNames = apply_filters('breadcrumbs_post_taxonomies', $taxonomyNames, $post);

        if (! is_array($taxonomyNames)) {
            return null;
        }

        foreach ($taxonomyNames as $taxonomyName) {
            $terms = get_the_terms($post, (string) $taxonomyName);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $term = array_shift($terms);

            return $term instanceof WP_Term ? $term : null;
        }

        return null;
    }

    private static function addTermAncestors(array &$items, WP_Term $term): void
    {
        $ancestorIds = array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));

        foreach ($ancestorIds as $ancestorId) {
            $ancestor = get_term($ancestorId, $term->taxonomy);

            if (! $ancestor instanceof WP_Term || is_wp_error($ancestor)) {
                continue;
            }

            $ancestorUrl = self::termUrl($ancestor);

            if ('' !== $ancestorUrl) {
                $items[] = self::item($ancestor->name, $ancestorUrl);
            }
        }
    }

    private static function termUrl(WP_Term $term): string
    {
        $url = get_term_link($term);

        return is_wp_error($url) ? '' : (string) $url;
    }

    private static function addPaginationItem(array &$items, array $labels): void
    {
        $paged = (int) get_query_var('paged');

        if ($paged <= 0) {
            $paged = (int) get_query_var('page');
        }

        if ($paged > 1) {
            self::unmarkCurrent($items);
            $items[] = self::item(sprintf($labels['page'], $paged), '', true);
        }
    }

    private static function unmarkCurrent(array &$items): void
    {
        foreach ($items as &$item) {
            $item['current'] = false;
        }
        unset($item);
    }

    private static function filteredItems(array $items, array $args): array
    {
        return self::validFilteredItems(
            apply_filters('breadcrumbs_items', array_values($items), $args),
            $items,
        );
    }

    private static function validFilteredItems(mixed $filtered, array $fallback): array
    {
        return is_array($filtered)
            ? array_values(array_filter($filtered, 'is_array'))
            : array_values($fallback);
    }

    private static function item(string $label, string $url = '', bool $current = false): array
    {
        return [
            'label'   => wp_strip_all_tags($label),
            'url'     => $url,
            'current' => $current,
        ];
    }
}
