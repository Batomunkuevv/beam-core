<?php

namespace Beam\Core\Modules\Favicon;

use Beam\Core\Settings\SettingsPage as CoreSettingsPage;

defined('ABSPATH') || exit;

final class SettingsPage
{
    public static function register_handlers(): void
    {
        add_action('admin_init', [self::class, 'handle_save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== 'settings_page_' . CoreSettingsPage::PAGE_SLUG) {
            return;
        }

        if (CoreSettingsPage::active_tab() !== 'favicon') {
            return;
        }

        wp_enqueue_media();
        wp_register_script('beam-favicon-settings', false, ['jquery', 'media-editor'], BEAM_CORE_VERSION, true);
        wp_enqueue_script('beam-favicon-settings');
        wp_add_inline_script('beam-favicon-settings', self::inlineScript());
    }

    public static function handle_save(): void
    {
        if (! isset($_POST['beam_favicon_save'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_favicon_settings');

        $id = absint(wp_unslash($_POST['beam_favicon_attachment_id'] ?? 0));
        if (! empty($_POST['beam_favicon_remove'])) {
            $id = 0;
        }

        Config::setAttachmentId($id);
        if ($id > 0) {
            Generator::sync($id);
        } else {
            Generator::cleanup();
        }

        add_settings_error(
            'beam_favicon',
            'settings_saved',
            'Настройки фавиконки сохранены.',
            'updated',
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $id = Config::attachmentId();
        $url = $id > 0 ? wp_get_attachment_url($id) : '';
        $url = is_string($url) ? $url : '';
        $formAction = CoreSettingsPage::tab_url('favicon');
        ?>
        <div class="beam-core-settings__section">
            <p class="description">
                SVG или PNG, лучше квадрат от 512×512. Остальные размеры генерируются автоматически.
            </p>
            <form method="post" action="<?= esc_url($formAction) ?>">
                <?php wp_nonce_field('beam_favicon_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Фавиконка</th>
                        <td>
                            <div data-beam-favicon>
                                <div data-beam-favicon-preview style="margin-bottom:12px;">
                                    <?php if ($url !== '') : ?>
                                        <img src="<?= esc_url($url) ?>" alt="" style="width:64px;height:64px;object-fit:contain;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:8px;">
                                    <?php else : ?>
                                        <p class="description" data-beam-favicon-empty>Файл не выбран. Используются иконки по умолчанию (фильтр beam_favicon_defaults) или стандартная иконка WordPress.</p>
                                    <?php endif; ?>
                                </div>
                                <p>
                                    <button type="button" class="button button-primary" data-beam-favicon-select>Выбрать изображение</button>
                                    <button type="button" class="button" data-beam-favicon-clear <?= $id > 0 ? '' : 'style="display:none"' ?>>Удалить</button>
                                </p>
                                <input type="hidden" name="beam_favicon_attachment_id" value="<?= esc_attr((string) $id) ?>" data-beam-favicon-id>
                                <input type="hidden" name="beam_favicon_remove" value="0" data-beam-favicon-remove>
                            </div>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Сохранить настройки', 'primary', 'beam_favicon_save'); ?>
            </form>
        </div>
        <?php
    }

    private static function inlineScript(): string
    {
        return <<<'JS'
jQuery(function ($) {
    var $root = $('[data-beam-favicon]');
    if (!$root.length || typeof wp === 'undefined' || !wp.media) {
        return;
    }
    var frame;
    $root.on('click', '[data-beam-favicon-select]', function (event) {
        event.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Фавиконка',
            button: { text: 'Выбрать' },
            multiple: false,
            library: { type: ['image', 'image/svg+xml'] }
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.url || '';
            $root.find('[data-beam-favicon-id]').val(attachment.id || 0);
            $root.find('[data-beam-favicon-remove]').val('0');
            var $preview = $root.find('[data-beam-favicon-preview]');
            $preview.html('<img src="' + url + '" alt="" style="width:64px;height:64px;object-fit:contain;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:8px;">');
            $root.find('[data-beam-favicon-clear]').show();
        });
        frame.open();
    });
    $root.on('click', '[data-beam-favicon-clear]', function (event) {
        event.preventDefault();
        $root.find('[data-beam-favicon-id]').val('0');
        $root.find('[data-beam-favicon-remove]').val('1');
        $root.find('[data-beam-favicon-preview]').html('<p class="description" data-beam-favicon-empty>Файл не выбран. Используются иконки по умолчанию (фильтр beam_favicon_defaults) или стандартная иконка WordPress.</p>');
        $(this).hide();
    });
});
JS;
    }
}
