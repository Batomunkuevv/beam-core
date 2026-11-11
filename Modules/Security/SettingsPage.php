<?php

namespace Beam\Core\Modules\Security;

use Beam\Core\Settings\SettingsPage as CoreSettingsPage;

defined('ABSPATH') || exit;

final class SettingsPage
{
    public static function register_handlers(): void
    {
        add_action('admin_init', [self::class, 'handle_save']);
        add_action('admin_init', [self::class, 'handle_recheck']);
    }

    public static function handle_save(): void
    {
        if (! isset($_POST['beam_security_save'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_security_settings');

        Config::update(wp_unslash($_POST['beam_security_features'] ?? []));

        add_settings_error(
            'beam_security',
            'settings_saved',
            'Настройки безопасности сохранены.',
            'updated',
        );
    }

    public static function handle_recheck(): void
    {
        if (! isset($_POST['beam_security_xmlrpc_recheck'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_security_xmlrpc_recheck');

        XmlrpcStatus::get(true);

        add_settings_error(
            'beam_security',
            'xmlrpc_rechecked',
            'Статус XML-RPC обновлён.',
            'updated',
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $features = Config::all();
        $formAction = CoreSettingsPage::tab_url('security');
        $fileEditDefined = defined('DISALLOW_FILE_EDIT');
        $xmlrpc = XmlrpcStatus::get();
        ?>
        <div class="beam-core-settings__section">
            <form method="post" action="<?= esc_url($formAction) ?>">
                <?php wp_nonce_field('beam_security_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Защита</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Функции модуля</legend>
                                <label>
                                    <input type="checkbox" name="beam_security_features[disallow_file_edit]" value="1" <?= checked($features['disallow_file_edit'], true, false) ?>>
                                    Отключить редактор файлов темы и плагинов
                                </label>
                                <p class="description">Задаёт константу <code>DISALLOW_FILE_EDIT</code>, если она ещё не определена.</p>
                                <?php if ($fileEditDefined) : ?>
                                    <p class="description">Константа <code>DISALLOW_FILE_EDIT</code> уже задана в wp-config — настройка ниже не действует.</p>
                                <?php endif; ?>
                                <p>
                                    <label>
                                        <input type="checkbox" name="beam_security_features[hide_user_logins]" value="1" <?= checked($features['hide_user_logins'], true, false) ?>>
                                        Скрыть логины пользователей
                                    </label>
                                </p>
                                <p class="description">Для гостей закрывает <code>?author=</code>, архивы <code>/author/</code>, REST <code>/wp/v2/users</code> и авторов в oEmbed и карте сайта. Для авторизованных пользователей доступ сохраняется.</p>
                                <p>
                                    <label>
                                        <input type="checkbox" name="beam_security_features[generic_login_errors]" value="1" <?= checked($features['generic_login_errors'], true, false) ?>>
                                        Скрывать детали ошибок входа
                                    </label>
                                </p>
                                <p class="description">Вместо подробной ошибки входа показывается сообщение «Неверный логин или пароль».</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Сохранить настройки', 'primary', 'beam_security_save'); ?>
            </form>

            <h2>XML-RPC</h2>
            <p class="description">Модуль не отключает XML-RPC, только проверяет, доступен ли он снаружи.</p>
            <?php self::renderXmlrpcStatus($xmlrpc, $formAction); ?>
        </div>
        <?php
    }

    /**
     * @param array{status: string, code: int, checked_at: int, url: string, path: string} $xmlrpc
     */
    private static function renderXmlrpcStatus(array $xmlrpc, string $formAction): void
    {
        $checked = $xmlrpc['checked_at'] > 0
            ? wp_date('d.m.Y H:i', $xmlrpc['checked_at'])
            : '';
        ?>
        <div class="beam-core-xmlrpc">
            <?php if ($xmlrpc['status'] === 'closed') : ?>
                <p><strong>XML-RPC закрыт</strong><?php if ($xmlrpc['code'] > 0) : ?> (HTTP <?= esc_html((string) $xmlrpc['code']) ?>)<?php endif; ?>.</p>
            <?php elseif ($xmlrpc['status'] === 'open') : ?>
                <div class="notice notice-warning inline">
                    <p><strong>XML-RPC открыт — рекомендуем закрыть на уровне nginx</strong><?php if ($xmlrpc['code'] > 0) : ?> (HTTP <?= esc_html((string) $xmlrpc['code']) ?>)<?php endif; ?>.</p>
                    <pre style="background:#fff;padding:12px;overflow:auto;"><code><?= esc_html(XmlrpcStatus::nginxSnippet()) ?></code></pre>
                </div>
            <?php else : ?>
                <p>Не удалось проверить XML-RPC<?= $xmlrpc['code'] > 0 ? ' (HTTP ' . esc_html((string) $xmlrpc['code']) . ')' : '' ?>.</p>
            <?php endif; ?>
            <?php if ($checked !== '') : ?>
                <p class="description">Проверено: <?= esc_html($checked) ?>. Результат кешируется на час.</p>
            <?php endif; ?>
            <form method="post" action="<?= esc_url($formAction) ?>">
                <?php wp_nonce_field('beam_security_xmlrpc_recheck'); ?>
                <?php submit_button('Проверить снова', 'secondary', 'beam_security_xmlrpc_recheck', false); ?>
            </form>
        </div>
        <?php
    }
}
