<?php

defined('ABSPATH') || exit;

final class BeamSmtpSettingsPage
{
    public static function register_handlers(): void
    {
        add_action('admin_init', [self::class, 'handle_save']);
        add_action('admin_init', [self::class, 'handle_test_mail']);
    }

    public static function handle_save(): void
    {
        if (! isset($_POST['beam_smtp_save'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_smtp_settings');

        BeamSmtpConfig::update(wp_unslash($_POST));

        add_settings_error(
            'beam_smtp',
            'settings_saved',
            'Настройки SMTP сохранены.',
            'updated',
        );
    }

    public static function handle_test_mail(): void
    {
        if (! isset($_POST['beam_smtp_test'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_smtp_test');

        $to = sanitize_email((string) wp_unslash($_POST['test_email'] ?? ''));

        if ('' === $to) {
            add_settings_error(
                'beam_smtp',
                'test_invalid_email',
                'Укажите корректный email для тестового письма.',
                'error',
            );

            return;
        }

        $result = BeamSmtpMailer::send_test($to);

        if (is_wp_error($result)) {
            add_settings_error(
                'beam_smtp',
                'test_failed',
                sprintf('Тест не удался: %s', $result->get_error_message()),
                'error',
            );

            return;
        }

        add_settings_error(
            'beam_smtp',
            'test_sent',
            sprintf('Тестовое письмо отправлено на %s.', $to),
            'updated',
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings  = BeamSmtpConfig::get_all();
        $constants = BeamSmtpConfig::defined_constant_names();
        $form_action = \Beam\Core\Settings\SettingsPage::tab_url('smtp');

        ?>
        <div class="beam-core-settings__section">
            <?php if ([] !== $constants) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                'Часть настроек переопределена константами wp-config.php: %s',
                                implode(', ', $constants),
                            ),
                        );
                ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($form_action); ?>">
                <?php wp_nonce_field('beam_smtp_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Включить SMTP</th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="enabled"
                                    value="1"
                                    <?php checked((bool) $settings['enabled']); ?>
                                    <?php disabled(BeamSmtpConfig::is_constant_defined('enabled')); ?>
                                />
                                Отправлять почту через SMTP
                            </label>
                            <p class="description">Если выключено, используется стандартная отправка WordPress.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_host">SMTP-сервер</label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="beam_smtp_host"
                                name="host"
                                value="<?php echo esc_attr((string) $settings['host']); ?>"
                                placeholder="smtp.example.com"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('host')); ?>
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_port">Порт</label></th>
                        <td>
                            <input
                                type="number"
                                class="small-text"
                                id="beam_smtp_port"
                                name="port"
                                min="1"
                                max="65535"
                                value="<?php echo esc_attr((string) $settings['port']); ?>"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('port')); ?>
                            />
                            <p class="description">Обычно 587 (TLS) или 465 (SSL).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_encryption">Шифрование</label></th>
                        <td>
                            <select
                                id="beam_smtp_encryption"
                                name="encryption"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('encryption')); ?>
                            >
                                <option value="none" <?php selected($settings['encryption'], 'none'); ?>>Без шифрования</option>
                                <option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Аутентификация</th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="auth"
                                    value="1"
                                    <?php checked((bool) $settings['auth']); ?>
                                    <?php disabled(BeamSmtpConfig::is_constant_defined('auth')); ?>
                                />
                                Использовать логин и пароль
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_username">Логин</label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="beam_smtp_username"
                                name="username"
                                value="<?php echo esc_attr((string) $settings['username']); ?>"
                                autocomplete="off"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('username')); ?>
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_password">Пароль</label></th>
                        <td>
                            <?php if (BeamSmtpConfig::is_constant_defined('password')) : ?>
                                <p class="description">Пароль задан константой BEAM_SMTP_PASS в wp-config.php.</p>
                            <?php else : ?>
                                <input
                                    type="password"
                                    class="regular-text"
                                    id="beam_smtp_password"
                                    name="password"
                                    value=""
                                    autocomplete="new-password"
                                    placeholder="<?php echo esc_attr('' !== (string) $settings['password'] ? '••••••••' : ''); ?>"
                                />
                                <p class="description">Оставьте пустым, чтобы не менять сохранённый пароль.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_from_email">Email отправителя</label></th>
                        <td>
                            <input
                                type="email"
                                class="regular-text"
                                id="beam_smtp_from_email"
                                name="from_email"
                                value="<?php echo esc_attr((string) $settings['from_email']); ?>"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('from_email')); ?>
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="beam_smtp_from_name">Имя отправителя</label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="beam_smtp_from_name"
                                name="from_name"
                                value="<?php echo esc_attr((string) $settings['from_name']); ?>"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('from_name')); ?>
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Принудительный From</th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="force_from"
                                    value="1"
                                    <?php checked((bool) $settings['force_from']); ?>
                                    <?php disabled(BeamSmtpConfig::is_constant_defined('force_from')); ?>
                                />
                                Всегда использовать email и имя отправителя из настроек
                            </label>
                        </td>
                    </tr>
                </table>

                <h2>Заявки с сайта</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="beam_smtp_lead_email">Email для заявок</label></th>
                        <td>
                            <input
                                type="email"
                                class="regular-text"
                                id="beam_smtp_lead_email"
                                name="lead_email"
                                value="<?php echo esc_attr((string) $settings['lead_email']); ?>"
                                placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>"
                                <?php disabled(BeamSmtpConfig::is_constant_defined('lead_email')); ?>
                            />
                            <p class="description">Адрес для заявок с форм. Если поле пустое, используется email администратора сайта.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Сохранить настройки', 'primary', 'beam_smtp_save'); ?>
            </form>

            <hr />

            <h2>Тестовое письмо</h2>
            <form method="post" action="<?php echo esc_url($form_action); ?>">
                <?php wp_nonce_field('beam_smtp_test'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="beam_smtp_test_email">Получатель</label></th>
                        <td>
                            <input
                                type="email"
                                class="regular-text"
                                id="beam_smtp_test_email"
                                name="test_email"
                                value="<?php echo esc_attr(BeamSmtpConfig::get_lead_email()); ?>"
                                required
                            />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Отправить тест', 'secondary', 'beam_smtp_test'); ?>
            </form>

            <hr />

            <h2>Константы wp-config.php</h2>
            <p>Для production рекомендуется задавать чувствительные данные через константы:</p>
            <pre style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:720px;overflow:auto;">define( 'BEAM_SMTP_ENABLED', true );
define( 'BEAM_SMTP_HOST', 'smtp.example.com' );
define( 'BEAM_SMTP_PORT', 587 );
define( 'BEAM_SMTP_ENCRYPTION', 'tls' ); // none | tls | ssl
define( 'BEAM_SMTP_AUTH', true );
define( 'BEAM_SMTP_USER', 'user@example.com' );
define( 'BEAM_SMTP_PASS', 'secret' );
define( 'BEAM_SMTP_FROM_EMAIL', 'noreply@example.com' );
define( 'BEAM_SMTP_FROM_NAME', 'Site Name' );
define( 'BEAM_SMTP_FORCE_FROM', true );
define( 'BEAM_SMTP_LEAD_EMAIL', 'leads@example.com' );</pre>
        </div>
        <?php
    }
}
