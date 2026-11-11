<?php

namespace Beam\Core\Modules\Favicon;

defined('ABSPATH') || exit;

final class Config
{
    public const OPTION = 'beam_favicon_attachment_id';

    public static function attachmentId(): int
    {
        $stored = (int) get_option(self::OPTION, 0);
        $id = (int) apply_filters('beam_favicon_attachment_id', $stored);

        return $id > 0 ? $id : 0;
    }

    public static function setAttachmentId(int $id): void
    {
        $id = max(0, $id);
        $current = (int) get_option(self::OPTION, 0);

        if ($current === $id) {
            return;
        }

        update_option(self::OPTION, $id, false);
    }

    /**
     * Optional default icon URLs from the theme or a site mu-plugin.
     *
     * @return array{
     *     svg: string,
     *     icon_32: string,
     *     apple_touch: string,
     *     icon_192: string,
     *     icon_512: string,
     *     ico: string
     * }
     */
    public static function defaults(): array
    {
        $defaults = [
            'svg' => '',
            'icon_32' => '',
            'apple_touch' => '',
            'icon_192' => '',
            'icon_512' => '',
            'ico' => '',
        ];

        $filtered = apply_filters('beam_favicon_defaults', $defaults);
        if (! is_array($filtered)) {
            return $defaults;
        }

        foreach ($defaults as $key => $_) {
            $value = $filtered[$key] ?? '';
            $defaults[$key] = is_string($value) ? $value : '';
        }

        return $defaults;
    }

    public static function themeColor(): string
    {
        $color = apply_filters('beam_favicon_theme_color', '');

        return self::sanitizeHex(is_string($color) ? $color : '');
    }

    public static function backgroundColor(): string
    {
        $color = apply_filters('beam_favicon_background_color', self::themeColor());
        $color = self::sanitizeHex(is_string($color) ? $color : '');

        return $color !== '' ? $color : '#ffffff';
    }

    public static function sanitizeHex(string $color): string
    {
        $color = trim($color);
        if ($color !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            if (strlen($color) === 4) {
                $color = sprintf('#%s%s%s%s%s%s', $color[1], $color[1], $color[2], $color[2], $color[3], $color[3]);
            }

            return strtolower($color);
        }

        return '';
    }
}
