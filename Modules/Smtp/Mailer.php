<?php

use PHPMailer\PHPMailer\PHPMailer;

defined('ABSPATH') || exit;

final class BeamSmtpMailer
{
    public static function register(): void
    {
        add_filter('pre_wp_mail', [self::class, 'preventInvalidTransport'], 10, 2);
        add_action('phpmailer_init', [self::class, 'configure']);
        add_filter('wp_mail_from', [self::class, 'filter_from_email']);
        add_filter('wp_mail_from_name', [self::class, 'filter_from_name']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function preventInvalidTransport(?bool $result, array $attributes): ?bool
    {
        if (null !== $result || ! BeamSmtpConfig::is_enabled()) {
            return $result;
        }

        return BeamSmtpConfig::is_configured() ? null : false;
    }

    public static function configure(PHPMailer $phpmailer): void
    {
        if (! BeamSmtpConfig::is_enabled()) {
            return;
        }

        $host       = (string) BeamSmtpConfig::get('host');
        $port       = (int) BeamSmtpConfig::get('port');
        $encryption = (string) BeamSmtpConfig::get('encryption');
        $auth       = (bool) BeamSmtpConfig::get('auth');
        $username   = (string) BeamSmtpConfig::get('username');
        $password   = (string) BeamSmtpConfig::get('password');

        if ('' === $host || $port <= 0) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port;

        if ($auth) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $username;
            $phpmailer->Password = $password;
        } else {
            $phpmailer->SMTPAuth = false;
        }

        if ('none' === $encryption) {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        } else {
            $phpmailer->SMTPSecure = $encryption;
            $phpmailer->SMTPAutoTLS = 'tls' === $encryption;
        }

        $from_email = (string) BeamSmtpConfig::get('from_email');
        $from_name  = (string) BeamSmtpConfig::get('from_name');

        if ('' !== $from_email && (bool) BeamSmtpConfig::get('force_from')) {
            $phpmailer->setFrom($from_email, $from_name, false);
        }
    }

    public static function filter_from_email(string $email): string
    {
        if (! BeamSmtpConfig::is_enabled() || ! (bool) BeamSmtpConfig::get('force_from')) {
            return $email;
        }

        $from_email = (string) BeamSmtpConfig::get('from_email');

        return '' !== $from_email ? $from_email : $email;
    }

    public static function filter_from_name(string $name): string
    {
        if (! BeamSmtpConfig::is_enabled() || ! (bool) BeamSmtpConfig::get('force_from')) {
            return $name;
        }

        $from_name = (string) BeamSmtpConfig::get('from_name');

        return '' !== $from_name ? $from_name : $name;
    }

    public static function send_test(string $to): bool|\WP_Error
    {
        if (! BeamSmtpConfig::is_enabled()) {
            return new \WP_Error('beam_smtp_disabled', 'SMTP не включён.');
        }

        if (! BeamSmtpConfig::is_configured()) {
            return new \WP_Error('beam_smtp_invalid_config', 'Не указан SMTP-сервер.');
        }

        $subject = sprintf(
            'Тестовое письмо с сайта %s',
            get_bloginfo('name'),
        );

        $body = sprintf(
            "Это тестовое письмо BE.AM SMTP.\n\nСайт: %s\nДата: %s\n",
            home_url('/'),
            wp_date('d.m.Y H:i'),
        );

        $sent = wp_mail($to, $subject, $body);

        if ($sent) {
            return true;
        }

        global $phpmailer;

        if ($phpmailer instanceof PHPMailer && '' !== $phpmailer->ErrorInfo) {
            return new \WP_Error('beam_smtp_send_failed', $phpmailer->ErrorInfo);
        }

        return new \WP_Error(
            'beam_smtp_send_failed',
            'Не удалось отправить письмо. Проверьте настройки SMTP.',
        );
    }
}
