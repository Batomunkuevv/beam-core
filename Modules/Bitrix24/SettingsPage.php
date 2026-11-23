<?php

namespace Beam\Core\Modules\Bitrix24;

use Beam\Core\Settings\SettingsPage as CoreSettingsPage;

defined('ABSPATH') || exit;

final class SettingsPage
{
    public static function register_handlers(): void
    {
        add_action('admin_init', [self::class, 'handle_save']);
        add_action('admin_init', [self::class, 'handle_test']);
        add_action('admin_init', [self::class, 'handle_log_clear']);
    }

    public static function handle_save(): void
    {
        if (! isset($_POST['beam_bitrix24_save']) || ! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_bitrix24_settings');

        if (Config::fromEnvironment()) {
            self::notice('env', 'Вебхук задан в окружении сервера, здесь его не изменить.', 'warning');

            return;
        }

        if (! empty($_POST['beam_bitrix24_clear'])) {
            Config::clear();
            self::notice('cleared', 'Вебхук удалён. Заявки в Битрикс24 больше не отправляются.');

            return;
        }

        $input = isset($_POST['beam_bitrix24_webhook']) && is_string($_POST['beam_bitrix24_webhook'])
            ? trim(wp_unslash($_POST['beam_bitrix24_webhook']))
            : '';

        if ($input === '') {
            self::notice('unchanged', 'Изменений нет.');

            return;
        }

        if (! Config::save(sanitize_url($input))) {
            self::notice('invalid', 'Это не похоже на адрес вебхука. Нужен вид https://портал.bitrix24.ru/rest/1/код/', 'error');

            return;
        }

        self::notice('saved', 'Вебхук сохранён. Проверьте подключение.');
    }

    public static function handle_test(): void
    {
        if (! isset($_POST['beam_bitrix24_test']) || ! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_bitrix24_test');

        $result = Client::make()->call('crm.lead.fields');

        if ($result->ok) {
            self::notice('test_ok', 'Подключение работает: Битрикс24 ответил, доступ к лидам есть.');

            return;
        }

        self::notice('test_failed', sprintf('Не получилось: %s.', self::errorLabel($result->error)), 'error');
    }

    public static function handle_log_clear(): void
    {
        if (! isset($_POST['beam_bitrix24_log_clear']) || ! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_bitrix24_log');

        Log::clear();
        self::notice('log_cleared', 'Журнал очищен.');
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = CoreSettingsPage::tab_url('bitrix24');
        $fromEnvironment = Config::fromEnvironment();
        $stored = Config::storedWebhook();
        $current = Config::webhook();
        $entries = Log::entries();
        ?>
        <div class="beam-core-settings__section">
            <form method="post" action="<?= esc_url($action) ?>">
                <?php wp_nonce_field('beam_bitrix24_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="beam_bitrix24_webhook">Вебхук Битрикс24</label></th>
                        <td>
                            <?php if ($fromEnvironment) : ?>
                                <input
                                    type="text"
                                    class="regular-text code"
                                    id="beam_bitrix24_webhook"
                                    value="<?= esc_attr(Config::mask($current)) ?>"
                                    readonly
                                />
                                <p class="description">Задан в окружении сервера (<code><?= esc_html(Config::CONSTANT) ?></code>). Изменить его можно только там.</p>
                            <?php else : ?>
                                <input
                                    type="password"
                                    class="regular-text code"
                                    id="beam_bitrix24_webhook"
                                    name="beam_bitrix24_webhook"
                                    value=""
                                    placeholder="<?= esc_attr($stored !== '' ? Config::mask($stored) : 'https://портал.bitrix24.ru/rest/1/код/') ?>"
                                    autocomplete="off"
                                    spellcheck="false"
                                />
                                <?php if ($stored !== '') : ?>
                                    <p class="description">Сохранён: <code><?= esc_html(Config::mask($stored)) ?></code>. Чтобы заменить, вставьте новый адрес.</p>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="beam_bitrix24_clear" value="1" />
                                            Удалить сохранённый вебхук
                                        </label>
                                    </p>
                                <?php else : ?>
                                    <p class="description">В Битрикс24: Разработчикам → Другое → Входящий вебхук, права «CRM». Скопируйте адрес целиком.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Статус</th>
                        <td>
                            <?php if ($current !== '') : ?>
                                <span class="beam-core-settings__status is-active">Подключено — заявки уходят в Битрикс24</span>
                            <?php else : ?>
                                <span class="beam-core-settings__status">Не подключено — заявки в Битрикс24 не отправляются</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php if (! $fromEnvironment) : ?>
                    <?php submit_button('Сохранить', 'primary', 'beam_bitrix24_save'); ?>
                <?php endif; ?>
            </form>

            <h2>Проверка</h2>
            <form method="post" action="<?= esc_url($action) ?>">
                <?php wp_nonce_field('beam_bitrix24_test'); ?>
                <p class="description">Запрашивает у Битрикс24 список полей лида. Лиды при этом не создаются.</p>
                <?php submit_button('Проверить подключение', 'secondary', 'beam_bitrix24_test', true, $current === '' ? ['disabled' => 'disabled'] : []); ?>
            </form>

            <h2>Последние отправки</h2>
            <?php if ($entries === []) : ?>
                <p class="description">Пока ничего не отправлялось.</p>
            <?php else : ?>
                <table class="widefat striped beam-bitrix24-log">
                    <thead>
                        <tr>
                            <th scope="col">Время</th>
                            <th scope="col">Запрос</th>
                            <th scope="col">Результат</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry) : ?>
                            <tr>
                                <td><?= esc_html(wp_date('d.m.Y H:i:s', $entry['time']) ?: '') ?></td>
                                <td><?= esc_html(self::methodLabel($entry['method'])) ?></td>
                                <td>
                                    <?php if ($entry['ok']) : ?>
                                        <span class="beam-core-settings__status is-active"><?= esc_html($entry['id'] !== null ? sprintf('Успешно, лид №%d', $entry['id']) : 'Успешно') ?></span>
                                    <?php else : ?>
                                        <?= esc_html(sprintf('Ошибка: %s', self::errorLabel($entry['error']))) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" action="<?= esc_url($action) ?>">
                    <?php wp_nonce_field('beam_bitrix24_log'); ?>
                    <?php submit_button('Очистить журнал', 'secondary small', 'beam_bitrix24_log_clear'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function methodLabel(string $method): string
    {
        return match ($method) {
            Lead::METHOD      => 'Новый лид',
            'crm.lead.fields' => 'Проверка подключения',
            default           => $method,
        };
    }

    private static function errorLabel(string $code): string
    {
        return match ($code) {
            'http_error', 'exception'                                 => 'Битрикс24 не ответил',
            'bad_response'                                            => 'Битрикс24 ответил непонятно',
            'not_configured'                                          => 'вебхук не задан',
            'INVALID_CREDENTIALS', 'NO_AUTH_FOUND', 'expired_token'   => 'вебхук не принят, проверьте адрес',
            'insufficient_scope', 'ACCESS_DENIED'                     => 'у вебхука нет прав на CRM',
            'QUERY_LIMIT_EXCEEDED'                                    => 'слишком много запросов, попробуйте позже',
            default                                                   => $code,
        };
    }

    private static function notice(string $code, string $message, string $type = 'updated'): void
    {
        add_settings_error('beam_bitrix24', $code, $message, $type);
    }
}
