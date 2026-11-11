<?php

defined('ABSPATH') || exit;

final class BeamAdminBrandingSettingsPage
{
    public static function register_handlers(): void
    {
        add_action('admin_init', [self::class, 'handle_save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(string $hook): void
    {
        if ('settings_page_beam-core' !== $hook) {
            return;
        }

        if ('admin-branding' !== \Beam\Core\Settings\SettingsPage::active_tab()) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'beam-admin-branding-settings',
            BeamAdminBrandingAssetPath::url('css/settings.css'),
            [],
            BeamAdminBrandingAssetPath::version('css/settings.css'),
        );

        wp_register_script('beam-admin-branding-settings', false, ['jquery', 'media-editor'], BEAM_ADMIN_BRANDING_VERSION, true);
        wp_enqueue_script('beam-admin-branding-settings');
        wp_add_inline_script('beam-admin-branding-settings', self::inline_script());
    }

    public static function handle_save(): void
    {
        if (! isset($_POST['beam_admin_branding_save'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_admin_branding_settings');

        BeamAdminBrandingConfig::update(wp_unslash($_POST));

        add_settings_error(
            'beam_admin_branding',
            'settings_saved',
            'Настройки брендинга админки сохранены.',
            'updated',
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings  = BeamAdminBrandingConfig::get_all();
        $constants = BeamAdminBrandingConfig::defined_constant_names();
        $form_action = \Beam\Core\Settings\SettingsPage::tab_url('admin-branding');
        ?>
        <div class="beam-branding-settings beam-core-settings__section">
            <?php if ([] !== $constants) : ?>
                <div class="notice notice-info">
                    <p>
                        Часть настроек задана через константы в <code>wp-config.php</code>:
                        <?php echo esc_html(implode(', ', $constants)); ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($form_action); ?>">
                <?php wp_nonce_field('beam_admin_branding_settings'); ?>

                <h2>Общие</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Включить</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(! empty($settings['enabled'])); ?>>
                                Применять эту схему в админке и на странице входа
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scheme_slug">Slug схемы</label></th>
                        <td>
                            <input type="text" class="regular-text code" id="scheme_slug" name="scheme_slug" value="<?php echo esc_attr((string) $settings['scheme_slug']); ?>">
                            <p class="description">Латиница, без пробелов. На работающем сайте лучше не менять.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scheme_name">Название схемы</label></th>
                        <td>
                            <input type="text" class="regular-text" id="scheme_name" name="scheme_name" value="<?php echo esc_attr((string) $settings['scheme_name']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Принудительно</th>
                        <td>
                            <label>
                                <input type="checkbox" name="force_scheme" value="1" <?php checked(! empty($settings['force_scheme'])); ?>>
                                Всегда использовать эту схему для всех пользователей
                            </label>
                        </td>
                    </tr>
                </table>

                <h2>Цвета админки</h2>
                <table class="form-table" role="presentation">
                    <?php self::render_color_row('menu_background', 'Фон меню', $settings); ?>
                    <?php self::render_color_row('submenu_background', 'Фон подменю', $settings); ?>
                    <?php self::render_color_row('hover_background', 'Наведение на пункт', $settings); ?>
                    <?php self::render_color_row('accent', 'Активный пункт', $settings); ?>
                    <?php self::render_color_row('notification', 'Бейджи', $settings); ?>
                    <?php self::render_color_row('theme_color', 'Кнопки и фокус', $settings); ?>
                    <?php self::render_color_row('theme_color_darker_10', 'Наведение на кнопку', $settings); ?>
                    <?php self::render_color_row('theme_color_darker_20', 'Нажатие кнопки', $settings); ?>
                    <?php self::render_color_row('admin_body_background', 'Фон контента', $settings); ?>
                </table>

                <h2>Страница входа</h2>
                <table class="form-table" role="presentation">
                    <?php self::render_color_row('login_background', 'Фон страницы', $settings); ?>
                    <?php self::render_login_logo_row($settings); ?>
                    <tr>
                        <th scope="row"><label for="login_header_text">Текст ссылки логотипа</label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="login_header_text"
                                name="login_header_text"
                                value="<?php echo esc_attr((string) $settings['login_header_text']); ?>"
                                placeholder="<?php echo esc_attr((string) get_bloginfo('name')); ?>"
                                <?php disabled(BeamAdminBrandingConfig::is_constant_defined('login_header_text')); ?>
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="login_header_url">URL ссылки логотипа</label></th>
                        <td>
                            <input
                                type="url"
                                class="large-text code"
                                id="login_header_url"
                                name="login_header_url"
                                value="<?php echo esc_attr((string) $settings['login_header_url']); ?>"
                                placeholder="<?php echo esc_url(home_url('/')); ?>"
                                <?php disabled(BeamAdminBrandingConfig::is_constant_defined('login_header_url')); ?>
                            >
                        </td>
                    </tr>
                </table>

                <h2>Иконки меню</h2>
                <table class="form-table" role="presentation">
                    <?php self::render_color_row('icon_base', 'Базовый', $settings); ?>
                    <?php self::render_color_row('icon_focus', 'В фокусе', $settings); ?>
                    <?php self::render_color_row('icon_current', 'Текущий пункт', $settings); ?>
                </table>

                <?php submit_button('Сохранить', 'primary', 'beam_admin_branding_save'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_login_logo_row(array $settings): void
    {
        $logo_id       = (int) ($settings['login_logo_id'] ?? 0);
        $has_custom    = $logo_id > 0;
        $preview_url   = BeamAdminBrandingConfig::login_logo_preview_url();
        $fallback_url  = BeamAdminBrandingConfig::default_login_logo_url();
        $login_bg      = (string) ($settings['login_background'] ?? '#f0f0f1');
        $is_locked     = BeamAdminBrandingConfig::is_constant_defined('login_logo_id');
        $show_preview  = '' !== $preview_url;
        ?>
        <tr>
            <th scope="row">Логотип</th>
            <td>
                <div
                    class="beam-branding-logo"
                    data-beam-login-logo
                    data-fallback-url="<?php echo esc_attr($fallback_url); ?>"
                    style="--beam-branding-login-bg: <?php echo esc_attr($login_bg); ?>;"
                >
                    <div class="beam-branding-logo__preview<?php echo $show_preview ? '' : ' is-empty'; ?>" data-beam-login-logo-preview>
                        <?php if ($show_preview) : ?>
                            <img
                                class="beam-branding-logo__image"
                                src="<?php echo esc_url($preview_url); ?>"
                                alt=""
                                data-beam-login-logo-image
                            >
                        <?php else : ?>
                            <div class="beam-branding-logo__placeholder" data-beam-login-logo-placeholder>
                                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                <span>Логотип не выбран</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="beam-branding-logo__meta">
                        <span class="beam-branding-logo__status<?php echo $has_custom ? ' is-custom' : ''; ?>" data-beam-login-logo-status>
                            <?php echo esc_html($has_custom ? 'Свой файл' : 'По умолчанию'); ?>
                        </span>

                        <div class="beam-branding-logo__actions">
                            <button type="button" class="button button-primary" data-beam-login-logo-select <?php disabled($is_locked); ?>>
                                <?php echo esc_html($has_custom ? 'Заменить' : 'Выбрать из медиатеки'); ?>
                            </button>
                            <button
                                type="button"
                                class="button"
                                data-beam-login-logo-remove
                                <?php disabled($is_locked || ! $has_custom); ?>
                            >
                                Сбросить
                            </button>
                        </div>
                    </div>

                    <p class="beam-branding-logo__hint">
                        SVG или PNG, лучше 240×40 px. Если файл не выбран, берётся логотип сайта (Внешний вид → Свой логотип) или название сайта.
                    </p>

                    <input type="hidden" name="login_logo_id" value="<?php echo esc_attr((string) $logo_id); ?>" data-beam-login-logo-id>
                    <input type="hidden" name="login_logo_remove" value="0" data-beam-login-logo-remove-flag>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_color_row(string $key, string $label, array $settings): void
    {
        $value = (string) ($settings[$key] ?? '');
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <div class="beam-branding-color">
                    <input
                        type="color"
                        class="beam-branding-color__picker"
                        id="<?php echo esc_attr($key); ?>-picker"
                        value="<?php echo esc_attr($value); ?>"
                        data-beam-color-picker
                        data-beam-color-target="<?php echo esc_attr($key); ?>"
                        aria-label="<?php echo esc_attr($label); ?>"
                    >
                    <input
                        type="text"
                        class="regular-text code beam-branding-color__input"
                        id="<?php echo esc_attr($key); ?>"
                        name="<?php echo esc_attr($key); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        pattern="#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})"
                        data-beam-color-input
                        data-beam-color-target="<?php echo esc_attr($key); ?>"
                    >
                </div>
            </td>
        </tr>
        <?php
    }

    private static function inline_script(): string
    {
        return <<<'JS'
            (function ($) {
                var mediaFrame;

                function syncColorFields() {
                    $('[data-beam-color-picker]').each(function () {
                        var target = $(this).data('beam-color-target');
                        var $text = $('#' + target);

                        $(this).on('input change', function () {
                            $text.val(this.value);
                        });

                        $text.on('input change', function () {
                            if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(this.value)) {
                                $('#' + target + '-picker').val(this.value);
                            }
                        });
                    });
                }

                function renderLogoPreview($root, url) {
                    var $preview = $root.find('[data-beam-login-logo-preview]');
                    var $image = $root.find('[data-beam-login-logo-image]');
                    var $placeholder = $root.find('[data-beam-login-logo-placeholder]');

                    if (url) {
                        if (!$image.length) {
                            $placeholder.remove();
                            $image = $('<img>', {
                                class: 'beam-branding-logo__image',
                                'data-beam-login-logo-image': true,
                                alt: ''
                            }).appendTo($preview);
                        }

                        $image.attr('src', url);
                        $preview.removeClass('is-empty');
                        return;
                    }

                    $image.remove();

                    if (!$placeholder.length) {
                        $placeholder = $('<div>', {
                            class: 'beam-branding-logo__placeholder',
                            'data-beam-login-logo-placeholder': true,
                            html: '<span class="dashicons dashicons-format-image" aria-hidden="true"></span><span>Логотип не выбран</span>'
                        }).appendTo($preview);
                    }

                    $preview.addClass('is-empty');
                }

                function setLogoState($root, attachmentId, url, isCustom) {
                    $root.find('[data-beam-login-logo-id]').val(String(attachmentId || 0));
                    $root.find('[data-beam-login-logo-remove-flag]').val(isCustom ? '0' : '1');
                    $root.find('[data-beam-login-logo-status]')
                        .toggleClass('is-custom', isCustom)
                        .text(isCustom ? 'Свой файл' : 'По умолчанию');
                    $root.find('[data-beam-login-logo-remove]').prop('disabled', !isCustom);
                    renderLogoPreview($root, url || $root.data('fallback-url') || '');
                }

                function initLoginLogoField() {
                    $('[data-beam-login-logo]').each(function () {
                        var $root = $(this);

                        $root.find('[data-beam-login-logo-select]').on('click', function (event) {
                            event.preventDefault();

                            if (mediaFrame) {
                                mediaFrame.open();
                                return;
                            }

                            mediaFrame = wp.media({
                                title: 'Логотип для страницы входа',
                                button: { text: 'Использовать' },
                                library: { type: 'image' },
                                multiple: false
                            });

                            mediaFrame.on('select', function () {
                                var attachment = mediaFrame.state().get('selection').first().toJSON();
                                setLogoState($root, attachment.id, attachment.url, true);
                            });

                            mediaFrame.open();
                        });

                        $root.find('[data-beam-login-logo-remove]').on('click', function (event) {
                            event.preventDefault();
                            setLogoState($root, 0, $root.data('fallback-url') || '', false);
                        });
                    });
                }

                $(function () {
                    syncColorFields();
                    initLoginLogoField();
                });
            }(jQuery));
            JS;
    }
}
