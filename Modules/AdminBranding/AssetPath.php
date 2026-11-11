<?php

defined('ABSPATH') || exit;

final class BeamAdminBrandingAssetPath
{
    public static function url(string $relative): string
    {
        return plugins_url(ltrim($relative, '/'), BEAM_CORE_DIR . '/Modules/AdminBranding/assets/settings.css');
    }

    public static function path(string $relative): string
    {
        return BEAM_ADMIN_BRANDING_DIR . '/assets/' . ltrim($relative, '/');
    }

    public static function version(string $relative): string
    {
        $path = self::path($relative);

        return file_exists($path) ? (string) filemtime($path) : BEAM_ADMIN_BRANDING_VERSION;
    }
}
