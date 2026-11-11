<?php

namespace Beam\Core\Modules\DisableComments;

defined('ABSPATH') || exit;

final class DisableComments
{
    private const BLOCKED_ADMIN_PAGES = [
        'comment.php',
        'edit-comments.php',
        'options-discussion.php',
    ];

    public static function register(): void
    {
        add_action('widgets_init', [self::class, 'unregisterRecentCommentsWidget']);
        add_filter('wp_headers', [self::class, 'removePingbackHeader']);
        add_action('template_redirect', [self::class, 'blockCommentFeeds'], 9);
        add_action('add_admin_bar_menus', [self::class, 'removeAdminBarComments'], 0);
        add_action('admin_init', [self::class, 'removeAdminBarComments']);
        add_action('wp_loaded', [self::class, 'registerContextHooks']);
        add_action('enqueue_block_editor_assets', [self::class, 'unregisterLatestCommentsBlock']);
        add_filter('xmlrpc_methods', [self::class, 'disableXmlRpcComments']);
        add_filter('comments_array', '__return_empty_array', 20);
        add_filter('rest_pre_insert_comment', [self::class, 'blockRestCommentCreation'], 10, 2);
    }

    public static function registerContextHooks(): void
    {
        self::removePostTypeCommentSupport();

        if (is_admin()) {
            self::registerAdminHooks();
            return;
        }

        self::registerFrontendHooks();
    }

    public static function unregisterRecentCommentsWidget(): void
    {
        unregister_widget('WP_Widget_Recent_Comments');
        add_filter('show_recent_comments_widget_style', '__return_false');
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public static function removePingbackHeader(array $headers): array
    {
        unset($headers['X-Pingback']);

        return $headers;
    }

    public static function blockCommentFeeds(): void
    {
        if (!is_comment_feed()) {
            return;
        }

        wp_die(
            esc_html__('Комментарии отключены.'),
            '',
            ['response' => 403],
        );
    }

    public static function removeAdminBarComments(): void
    {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);

        if (!is_multisite()) {
            return;
        }

        add_action('admin_bar_menu', [self::class, 'removeNetworkCommentLinks'], 500);
    }

    public static function removeNetworkCommentLinks(\WP_Admin_Bar $adminBar): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        foreach ((array) $adminBar->user->blogs as $blog) {
            $adminBar->remove_menu('blog-' . $blog->userblog_id . '-c');
        }
    }

    public static function unregisterLatestCommentsBlock(): void
    {
        wp_add_inline_script(
            'wp-blocks',
            <<<'JS'
wp.domReady(() => {
    const blockType = 'core/latest-comments';

    if (!wp.blocks || !wp.data) {
        return;
    }

    if (wp.data.select('core/blocks').getBlockType(blockType)) {
        wp.blocks.unregisterBlockType(blockType);
    }
});
JS,
        );
    }

    /**
     * @param array<string, mixed> $methods
     * @return array<string, mixed>
     */
    public static function disableXmlRpcComments(array $methods): array
    {
        foreach (
            [
                'wp.deleteComment',
                'wp.editComment',
                'wp.getComment',
                'wp.getCommentCount',
                'wp.getComments',
                'wp.newComment',
                'pingback.extensions.getPingbacks',
                'pingback.ping',
            ] as $method
        ) {
            unset($methods[$method]);
        }

        return $methods;
    }

    /**
     * @param \WP_Comment|array<string, mixed> $preparedComment
     * @param \WP_REST_Request $request
     * @return \WP_Error
     */
    public static function blockRestCommentCreation($preparedComment, $request): \WP_Error
    {
        return new \WP_Error(
            'rest_comment_disabled',
            __('Комментарии отключены.', 'disable-comments'),
            ['status' => 403],
        );
    }

    private static function registerAdminHooks(): void
    {
        add_action('admin_menu', [self::class, 'removeAdminMenus'], 9999);
        add_action('admin_print_styles-index.php', [self::class, 'printAdminStyles']);
        add_action('admin_print_styles-profile.php', [self::class, 'printAdminStyles']);
        add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets']);
        add_filter('pre_option_default_pingback_flag', '__return_zero');
    }

    private static function registerFrontendHooks(): void
    {
        add_action('template_redirect', [self::class, 'disableCommentTemplate']);
        add_filter('comments_open', '__return_false', 20);
        add_filter('pings_open', '__return_false', 20);
        add_filter('post_comments_feed_link', '__return_false');
        add_filter('comments_link_feed', '__return_false');
        add_filter('comment_link', '__return_empty_string');
        add_filter('get_comments_number', '__return_zero');
        add_filter('feed_links_show_comments_feed', '__return_false');
    }

    public static function removeAdminMenus(): void
    {
        global $pagenow;

        if (in_array($pagenow, self::BLOCKED_ADMIN_PAGES, true)) {
            wp_die(
                esc_html__('Комментарии отключены.'),
                '',
                ['response' => 403],
            );
        }

        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    public static function removeDashboardWidgets(): void
    {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }

    public static function printAdminStyles(): void
    {
        echo '<style>
            #dashboard_right_now .comment-count,
            #dashboard_right_now .comment-mod-count,
            #latest-comments,
            #welcome-panel .welcome-comments,
            .user-comment-shortcuts-wrap {
                display: none !important;
            }
        </style>';
    }

    public static function disableCommentTemplate(): void
    {
        if (!is_singular()) {
            return;
        }

        add_filter('comments_template', '__return_empty_string', 20);
        wp_deregister_script('comment-reply');
        remove_action('wp_head', 'feed_links_extra', 3);
    }

    private static function removePostTypeCommentSupport(): void
    {
        $postTypes = get_post_types([], 'names');

        foreach ($postTypes as $postType) {
            if (post_type_supports($postType, 'comments')) {
                remove_post_type_support($postType, 'comments');
            }

            if (post_type_supports($postType, 'trackbacks')) {
                remove_post_type_support($postType, 'trackbacks');
            }
        }
    }
}
