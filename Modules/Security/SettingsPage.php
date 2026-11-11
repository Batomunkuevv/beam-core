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
        add_action('admin_init', [self::class, 'handle_login_limit_recheck']);
    }

    public static function handle_save(): void
    {
        if (! isset($_POST['beam_security_save']) && ! isset($_POST['beam_security_regenerate_slug'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_security_settings');

        $input = wp_unslash($_POST['beam_security_features'] ?? []);
        if (! is_array($input)) {
            $input = [];
        }

        $regenerate = isset($_POST['beam_security_regenerate_slug']);
        $hideRequested = ! empty($input['hide_login']);
        $slugInput = isset($_POST['beam_security_login_slug'])
            ? Config::sanitizeSlug((string) wp_unslash($_POST['beam_security_login_slug']))
            : Config::storedLoginSlug();

        if ($regenerate || ($hideRequested && $slugInput === '')) {
            $slugInput = Config::generateLoginSlug();
        }

        if ($slugInput !== '') {
            $slugError = Config::validateSlug($slugInput);
            if ($slugError !== null) {
                add_settings_error(
                    'beam_security',
                    'login_slug_invalid',
                    $slugError,
                    'error',
                );

                return;
            }
        } elseif ($hideRequested) {
            add_settings_error(
                'beam_security',
                'login_slug_invalid',
                'Не удалось задать адрес входа. Попробуйте сгенерировать новый.',
                'error',
            );

            return;
        }

        $previousActive = Config::isHideLoginActive();
        $previousSlug = Config::storedLoginSlug();

        Config::update($input, $slugInput !== '' ? $slugInput : null);
        LoginLimitStatus::clear();

        $nowActive = Config::isHideLoginActive();
        $nowUrl = $nowActive ? HideLogin::loginUrl() : site_url('wp-login.php', 'login');
        $addressChanged = $previousActive !== $nowActive || ($nowActive && $previousSlug !== Config::loginSlug());

        add_settings_error(
            'beam_security',
            'settings_saved',
            'Настройки безопасности сохранены.',
            'updated',
        );

        if ($addressChanged && $nowActive) {
            add_settings_error(
                'beam_security',
                'login_url_changed',
                sprintf(
                    'Адрес входа изменился: %s. Сохраните его — прежние /wp-admin и wp-login.php для гостей больше не работают.',
                    $nowUrl,
                ),
                'warning',
            );
        } elseif ($addressChanged && $previousActive && ! $nowActive) {
            add_settings_error(
                'beam_security',
                'login_url_restored',
                sprintf('Скрытие входа выключено. Снова используется %s.', $nowUrl),
                'updated',
            );
        } elseif ($regenerate) {
            add_settings_error(
                'beam_security',
                'login_url_generated',
                sprintf('Новый адрес входа: %s.', HideLogin::loginUrl()),
                'updated',
            );
        }

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

    public static function handle_login_limit_recheck(): void
    {
        if (! isset($_POST['beam_security_login_limit_recheck'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_security_login_limit_recheck');

        LoginLimitStatus::get(true);

        add_settings_error(
            'beam_security',
            'login_limit_rechecked',
            'Статус лимита попыток входа обновлён.',
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
        $hideDisabled = Config::isHideLoginDisabledByConstant();
        $hideActive = Config::isHideLoginActive();
        $loginSlug = Config::loginSlug();
        $loginUrl = $hideActive ? HideLogin::loginUrl() : site_url('wp-login.php', 'login');
        $xmlrpc = XmlrpcStatus::get();
        $loginLimit = LoginLimitStatus::get();
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
                                <p class="description">Для гостей закрывает <code>?author=</code>, архивы <code>/author/</code>, REST <code>/wp/v2/users</code> и авторов в oEmbed и карте сайта. Список пользователей и архивы авторов доступны только тем, у кого есть право <code>list_users</code> (обычно администраторам), а не любому вошедшему.</p>
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
                    <tr>
                        <th scope="row">Скрытие входа</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Скрытие адреса входа</legend>
                                <label>
                                    <input type="checkbox" name="beam_security_features[hide_login]" value="1" <?= checked($features['hide_login'], true, false) ?>>
                                    Скрыть адрес входа
                                </label>
                                <p class="description">Вход будет доступен только по своему адресу. При первом включении, если адрес пустой, создаётся случайный слаг вида <code>login-k7f3q9</code>. Для гостей <code>/wp-admin</code> и <code>wp-login.php</code> отдают 404 темы. <code>admin-ajax.php</code>, <code>admin-post.php</code>, REST и форма входа WooCommerce не затрагиваются.</p>
                                <?php if ($hideDisabled) : ?>
                                    <p class="description">Константа <code>BEAM_DISABLE_HIDE_LOGIN</code> задана в wp-config — скрытие входа выключено.</p>
                                <?php endif; ?>
                                <p>
                                    <label for="beam_security_login_slug">Адрес входа</label><br>
                                    <code><?= esc_html(trailingslashit(home_url('/'))) ?></code>
                                    <input type="text" id="beam_security_login_slug" name="beam_security_login_slug" value="<?= esc_attr($loginSlug) ?>" class="regular-text" autocomplete="off" spellcheck="false" placeholder="login-k7f3q9">
                                    <button type="submit" name="beam_security_regenerate_slug" value="1" class="button">Сгенерировать новый</button>
                                </p>
                                <p class="beam-core-login-url">
                                    Текущий адрес входа:
                                    <code><?= esc_html($loginUrl) ?></code>
                                </p>
                                <p class="description">Запомните или сохраните этот адрес. Если вход станет недоступен, добавьте в <code>wp-config.php</code> константу <code>BEAM_DISABLE_HIDE_LOGIN</code> со значением <code>true</code>.</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Сохранить настройки', 'primary', 'beam_security_save'); ?>
            </form>

            <h2>XML-RPC</h2>
            <p class="description">Модуль не отключает XML-RPC, только проверяет, доступен ли он снаружи.</p>
            <?php self::renderXmlrpcStatus($xmlrpc, $formAction); ?>

            <h2>Лимит попыток входа</h2>
            <p class="description">Модуль сам лимит не включает. По кнопке проверяется, отвечает ли адрес входа кодом 429 или 503. Неудачные входы пишутся в syslog (ident <code>beam-wp</code>, facility auth): при неверном пароле WordPress отвечает 200, и по access-логу nginx fail2ban их не увидит. Отключить запись: <code>define('BEAM_DISABLE_LOGIN_FAIL_LOG', true);</code>.</p>
            <?php self::renderLoginLimitStatus($loginLimit, $formAction, $hideActive); ?>
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
                    <pre class="beam-core-snippet"><code><?= esc_html(XmlrpcStatus::nginxSnippet()) ?></code></pre>
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

    /**
     * @param array{status: string, code: int, checked_at: int, url: string, path: string, attempts: int} $limit
     */
    private static function renderLoginLimitStatus(array $limit, string $formAction, bool $hideActive): void
    {
        $checked = $limit['checked_at'] > 0
            ? wp_date('d.m.Y H:i', $limit['checked_at'])
            : '';
        $http = $limit['code'] > 0 ? ' (HTTP ' . (string) $limit['code'] . ')' : '';
        ?>
        <div class="beam-core-login-limit">
            <?php if ($limit['status'] === 'limited') : ?>
                <p><strong>Лимит попыток входа работает</strong><?= esc_html($http) ?>.</p>
            <?php elseif ($limit['status'] === 'open') : ?>
                <div class="notice notice-warning inline">
                    <p><strong>Лимит попыток входа не обнаружен</strong><?= esc_html($http) ?>. Рекомендуем ограничить частоту запросов в nginx и подключить fail2ban.</p>
                    <?php if ($hideActive) : ?>
                        <p>Скрытие входа включено: в nginx лимит нужно ставить на публичный путь <code><?= esc_html($limit['path']) ?></code>, а не на <code>wp-login.php</code>. Сервер видит исходный URL, а файл входа подключается уже в PHP.</p>
                    <?php endif; ?>
                    <p><strong>nginx</strong></p>
                    <pre class="beam-core-snippet"><code><?= esc_html(LoginLimitStatus::nginxSnippet()) ?></code></pre>
                    <p><strong>fail2ban</strong></p>
                    <pre class="beam-core-snippet"><code><?= esc_html(LoginLimitStatus::fail2banSnippet()) ?></code></pre>
                </div>
            <?php elseif ($limit['status'] === 'unknown') : ?>
                <p>Не удалось проверить лимит попыток входа<?= esc_html($http) ?>.</p>
            <?php else : ?>
                <p>Проверка ещё не выполнялась. Рекомендуем ограничить частоту запросов в nginx и подключить fail2ban.</p>
                <?php if ($hideActive) : ?>
                    <p class="description">Скрытие входа включено: в nginx лимит нужно ставить на публичный путь <code><?= esc_html(LoginLimitStatus::loginPath()) ?></code>, а не на <code>wp-login.php</code>.</p>
                <?php endif; ?>
                <p><strong>nginx</strong></p>
                <pre class="beam-core-snippet"><code><?= esc_html(LoginLimitStatus::nginxSnippet()) ?></code></pre>
                <p><strong>fail2ban</strong></p>
                <pre class="beam-core-snippet"><code><?= esc_html(LoginLimitStatus::fail2banSnippet()) ?></code></pre>
            <?php endif; ?>
            <p class="description">Проверка не запускается сама: по кнопке отправляется несколько быстрых POST с неверными данными на адрес входа. Это может задеть fail2ban (в том числе по syslog), если в <code>ignoreip</code> нет адреса сервера. Результат кешируется на час.</p>
            <?php if ($checked !== '') : ?>
                <p class="description">Проверено: <?= esc_html($checked) ?><?php if ($limit['attempts'] > 0) : ?>, запросов: <?= esc_html((string) $limit['attempts']) ?><?php endif; ?>.</p>
            <?php endif; ?>
            <form method="post" action="<?= esc_url($formAction) ?>">
                <?php wp_nonce_field('beam_security_login_limit_recheck'); ?>
                <?php submit_button('Проверить снова', 'secondary', 'beam_security_login_limit_recheck', false); ?>
            </form>
        </div>
        <?php
    }
}
