<?php

defined('ABSPATH') || exit;

final class BeamAdminBranding
{
    public static function register(): void
    {
        if (! BeamAdminBrandingConfig::is_enabled()) {
            return;
        }

        add_action('admin_init', [self::class, 'registerColorScheme']);
        add_filter('get_user_option_admin_color', [self::class, 'defaultColorScheme']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminStyles']);
        add_action('login_enqueue_scripts', [self::class, 'enqueueLoginStyles']);
        add_filter('login_headerurl', [self::class, 'loginHeaderUrl']);
        add_filter('login_headertext', [self::class, 'loginHeaderText']);
    }

    public static function registerColorScheme(): void
    {
        wp_admin_css_color(
            (string) BeamAdminBrandingConfig::get('scheme_slug'),
            (string) BeamAdminBrandingConfig::get('scheme_name'),
            BeamAdminBrandingAssetPath::url('css/admin-colors.css'),
            BeamAdminBrandingConfig::scheme_preview_colors(),
            BeamAdminBrandingConfig::icon_colors(),
        );
    }

    /**
     * @param mixed $result
     */
    public static function defaultColorScheme($result): string
    {
        if (! BeamAdminBrandingConfig::get('force_scheme')) {
            return is_string($result) ? $result : (string) BeamAdminBrandingConfig::get('scheme_slug');
        }

        return (string) BeamAdminBrandingConfig::get('scheme_slug');
    }

    public static function enqueueAdminStyles(): void
    {
        wp_enqueue_style(
            'beam-admin-branding',
            BeamAdminBrandingAssetPath::url('css/admin-branding.css'),
            ['colors'],
            BeamAdminBrandingAssetPath::version('css/admin-branding.css'),
        );

        wp_add_inline_style('beam-admin-branding', self::dynamicCss());
    }

    public static function enqueueLoginStyles(): void
    {
        wp_enqueue_style(
            'beam-admin-branding-login',
            BeamAdminBrandingAssetPath::url('css/admin-branding.css'),
            [],
            BeamAdminBrandingAssetPath::version('css/admin-branding.css'),
        );

        wp_add_inline_style('beam-admin-branding-login', self::dynamicCss(true));
    }

    public static function loginHeaderUrl(): string
    {
        return BeamAdminBrandingConfig::login_header_url();
    }

    public static function loginHeaderText(): string
    {
        return BeamAdminBrandingConfig::login_header_text();
    }

    private static function dynamicCss(bool $login_only = false): string
    {
        $css = self::colorVariablesCss();

        if ($login_only) {
            $css .= self::loginCss();
        } else {
            $css .= self::adminOverridesCss();
        }

        return $css;
    }

    private static function colorVariablesCss(): string
    {
        $theme = (string) BeamAdminBrandingConfig::get('theme_color');
        $rgb   = self::hexToRgb($theme);

        return sprintf(
            ':root {
                --beam-admin-theme-color: %1$s;
                --beam-admin-theme-color-rgb: %2$s;
                --beam-admin-theme-color-darker-10: %3$s;
                --beam-admin-theme-color-darker-20: %4$s;
            }
            body {
                --wp-admin-theme-color: %1$s;
                --wp-admin-theme-color--rgb: %2$s;
                --wp-admin-theme-color-darker-10: %3$s;
                --wp-admin-theme-color-darker-20: %4$s;
                background: %5$s;
            }',
            esc_attr($theme),
            esc_attr($rgb),
            esc_attr((string) BeamAdminBrandingConfig::get('theme_color_darker_10')),
            esc_attr((string) BeamAdminBrandingConfig::get('theme_color_darker_20')),
            esc_attr((string) BeamAdminBrandingConfig::get('admin_body_background')),
        );
    }

    private static function adminOverridesCss(): string
    {
        $menu    = esc_attr((string) BeamAdminBrandingConfig::get('menu_background'));
        $submenu = esc_attr((string) BeamAdminBrandingConfig::get('submenu_background'));
        $hover   = esc_attr((string) BeamAdminBrandingConfig::get('hover_background'));
        $accent  = esc_attr((string) BeamAdminBrandingConfig::get('accent'));
        $notify  = esc_attr((string) BeamAdminBrandingConfig::get('notification'));

        return <<<CSS
            #adminmenuback,
            #adminmenuwrap,
            #adminmenu,
            #wpadminbar {
                background: {$menu};
            }
            #adminmenu a:hover,
            #adminmenu li.menu-top:hover,
            #adminmenu li.opensub > a.menu-top,
            #adminmenu li > a.menu-top:focus,
            #wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item,
            #wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus,
            #wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus,
            #wpadminbar.nojs .ab-top-menu > li.menupop:hover > .ab-item,
            #wpadminbar .ab-top-menu > li.menupop.hover > .ab-item {
                background-color: {$hover};
            }
            #adminmenu .wp-submenu,
            #adminmenu .wp-has-current-submenu .wp-submenu,
            #adminmenu .wp-has-current-submenu.opensub .wp-submenu,
            #adminmenu a.wp-has-current-submenu:focus + .wp-submenu,
            #wpadminbar .menupop .ab-sub-wrapper {
                background: {$submenu};
            }
            #adminmenu li.current a.menu-top,
            #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,
            #adminmenu li.wp-has-current-submenu .wp-submenu .wp-submenu-head,
            .folded #adminmenu li.current.menu-top,
            .wp-core-ui .wp-ui-highlight {
                background: {$accent};
            }
            #adminmenu .menu-counter,
            #adminmenu .awaiting-mod,
            #adminmenu .update-plugins,
            #wpadminbar #wp-admin-bar-recovery-mode {
                background: {$notify};
            }
            CSS;
    }

    private static function loginCss(): string
    {
        $background = esc_attr((string) BeamAdminBrandingConfig::get('login_background'));
        $theme      = esc_attr((string) BeamAdminBrandingConfig::get('theme_color'));
        $hover      = esc_attr((string) BeamAdminBrandingConfig::get('theme_color_darker_10'));
        $muted      = esc_attr((string) BeamAdminBrandingConfig::get('icon_base'));
        $logo = BeamAdminBrandingConfig::login_logo_url();

        $css = <<<CSS
            body.login {
                background: {$background};
                color: #1d2327;
            }
            body.login a { color: {$theme}; }
            body.login a:hover,
            body.login a:focus,
            body.login a:active { color: {$hover}; }
            body.login .button-primary {
                background: {$theme};
                border-color: {$theme};
            }
            body.login .button-primary:hover,
            body.login .button-primary:focus {
                background: {$hover};
                border-color: {$hover};
            }
            body.login input[type='text']:focus,
            body.login input[type='password']:focus {
                border-color: {$theme};
                box-shadow: 0 0 0 1px {$theme};
            }
            body.login #nav a,
            body.login #backtoblog a,
            body.login .privacy-policy-page-link a { color: {$muted}; }
            body.login #nav a:hover,
            body.login #nav a:focus,
            body.login #backtoblog a:hover,
            body.login #backtoblog a:focus,
            body.login .privacy-policy-page-link a:hover,
            body.login .privacy-policy-page-link a:focus { color: {$theme}; }
            body.login .language-switcher .button {
                background: {$theme};
                border-color: {$theme};
                color: #ffffff;
            }
            body.login .language-switcher .button:hover,
            body.login .language-switcher .button:focus {
                background: {$hover};
                border-color: {$hover};
            }
            body.login .message,
            body.login .notice,
            body.login .success { border-left-color: {$theme}; }
            CSS;

        if ('' !== $logo) {
            $css .= sprintf(
                "body.login #login h1 a { background-image: url('%s'); }",
                esc_url($logo),
            );
        } else {
            // No logo: the header text (site name) instead of the WordPress mark.
            $css .= "body.login #login h1 a { background: none; width: auto; height: auto; text-indent: 0; overflow: visible; font-size: 24px; font-weight: 600; letter-spacing: .04em; line-height: 1.3; color: {$theme}; }";
        }

        return $css;
    }

    private static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (3 === strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (6 !== strlen($hex)) {
            return '37, 55, 69';
        }

        return sprintf(
            '%d, %d, %d',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
}
