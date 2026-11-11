<?php

defined('ABSPATH') || exit;

final class BeamSmtp
{
    public static function register(): void
    {
        BeamSmtpMailer::register();
    }
}
