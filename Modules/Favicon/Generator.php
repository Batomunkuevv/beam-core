<?php

namespace Beam\Core\Modules\Favicon;

defined('ABSPATH') || exit;

final class Generator
{
    public const OPTION_DERIVED = 'beam_favicon_derived';

    private const SIZES = [
        '32' => 32,
        '180' => 180,
        '192' => 192,
        '512' => 512,
    ];

    private const FLATTEN = ['180', '192', '512'];

    public static function register(): void
    {
        add_action('update_option_' . Config::OPTION, [self::class, 'onOptionChange'], 10, 2);
        add_action('add_option_' . Config::OPTION, [self::class, 'onOptionAdd'], 10, 2);
    }

    public static function onOptionChange(mixed $old, mixed $value): void
    {
        self::sync((int) $value);
    }

    public static function onOptionAdd(string $option, mixed $value): void
    {
        self::sync((int) $value);
    }

    /**
     * @return array<string, mixed>
     */
    public static function ensure(): array
    {
        $id = Config::attachmentId();
        if ($id <= 0) {
            self::cleanup();

            return [];
        }

        $derived = self::stored();
        if (self::isFresh($derived, $id)) {
            return $derived;
        }

        return self::sync($id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sync(int $attachmentId): array
    {
        if ($attachmentId <= 0) {
            self::cleanup();

            return [];
        }

        $path = get_attached_file($attachmentId);
        if (! is_string($path) || ! is_readable($path)) {
            self::cleanup();

            return [];
        }

        $hash = (string) hash_file('sha256', $path);
        $derived = self::stored();
        if (self::isFresh($derived, $attachmentId) && ($derived['hash'] ?? '') === $hash) {
            return $derived;
        }

        $dir = self::directory();
        if (! wp_mkdir_p($dir['path'])) {
            return [];
        }

        $mime = (string) get_post_mime_type($attachmentId);
        $isSvg = $mime === 'image/svg+xml' || str_ends_with(strtolower($path), '.svg');
        $source = $path;
        $rasterFrom = 'editor';

        if ($isSvg) {
            $raster = self::rasterizeSvg($path, 1024);
            if ($raster === null) {
                $fallback = self::fallbackDerived($attachmentId, $hash, $isSvg);

                return self::persist($fallback);
            }
            $source = $raster;
            $rasterFrom = 'imagick';
        }

        $square = self::squareSource($source);
        if ($square === null) {
            $fallback = self::fallbackDerived($attachmentId, $hash, $isSvg);
            if ($source !== $path) {
                self::deleteFile($source);
            }

            return self::persist($fallback);
        }

        $urls = [];
        $files = [];
        $background = Config::backgroundColor();

        foreach (self::SIZES as $key => $px) {
            $name = (int) $px === 180 ? 'apple-touch-icon.png' : 'icon-' . $px . '.png';
            $dest = $dir['path'] . '/' . $name;
            if (! self::resizeTo($square, $dest, $px)) {
                continue;
            }
            if (in_array($key, self::FLATTEN, true)) {
                self::flatten($dest, $px, $background);
            }
            $files[$key] = $dest;
            $urls[$key] = $dir['url'] . '/' . $name;
        }

        if ($square !== $source && $square !== $path) {
            self::deleteFile($square);
        }
        if ($source !== $path) {
            self::deleteFile($source);
        }

        if ($isSvg) {
            $svgUrl = wp_get_attachment_url($attachmentId);
            if (is_string($svgUrl) && $svgUrl !== '') {
                $urls['svg'] = $svgUrl;
            }
        }

        if (isset($urls['192'], $urls['512'])) {
            $manifest = self::writeManifest($dir, $urls);
            if ($manifest !== '') {
                $urls['manifest'] = $manifest;
            }
        }

        $derived = [
            'id' => $attachmentId,
            'hash' => $hash,
            'svg' => $isSvg,
            'raster_from' => $rasterFrom,
            'urls' => $urls,
            'files' => $files,
        ];

        return self::persist($derived);
    }

    /**
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        $stored = get_option(self::OPTION_DERIVED, []);

        return is_array($stored) ? $stored : [];
    }

    public static function cleanup(): void
    {
        $dir = self::directory();
        if (is_dir($dir['path'])) {
            foreach (['icon-32.png', 'icon-180.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest'] as $name) {
                self::deleteFile($dir['path'] . '/' . $name);
            }
        }

        delete_option(self::OPTION_DERIVED);
    }

    /**
     * @param array<string, mixed> $derived
     */
    private static function isFresh(array $derived, int $attachmentId): bool
    {
        if (($derived['id'] ?? 0) !== $attachmentId) {
            return false;
        }

        $urls = is_array($derived['urls'] ?? null) ? $derived['urls'] : [];
        foreach (['32', '180'] as $key) {
            $file = is_array($derived['files'] ?? null) ? ($derived['files'][$key] ?? '') : '';
            if (! is_string($file) || $file === '' || ! is_readable($file)) {
                return false;
            }
            if (! isset($urls[$key]) || ! is_string($urls[$key]) || $urls[$key] === '') {
                return false;
            }
        }

        $path = get_attached_file($attachmentId);
        if (! is_string($path) || ! is_readable($path)) {
            return false;
        }

        return ($derived['hash'] ?? '') === hash_file('sha256', $path);
    }

    /**
     * @return array{path: string, url: string}
     */
    private static function directory(): array
    {
        $uploads = wp_upload_dir();

        return [
            'path' => trailingslashit((string) $uploads['basedir']) . 'beam-favicon',
            'url' => trailingslashit((string) $uploads['baseurl']) . 'beam-favicon',
        ];
    }

    private static function rasterizeSvg(string $path, int $minSide): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        $svg = (string) file_get_contents($path);
        $vbW = 32.0;
        $vbH = 32.0;
        if (preg_match('/viewBox=["\']([^"\']+)["\']/', $svg, $match)) {
            $parts = preg_split('/[\s,]+/', trim($match[1]));
            if (is_array($parts) && count($parts) === 4) {
                $vbW = max(1.0, (float) $parts[2]);
                $vbH = max(1.0, (float) $parts[3]);
            }
        }

        $dpi = 72.0 * ($minSide / max($vbW, $vbH));
        $dir = self::directory();
        $dest = $dir['path'] . '/_source.png';

        try {
            $image = new \Imagick();
            $image->setBackgroundColor(new \ImagickPixel('transparent'));
            $image->setResolution($dpi, $dpi);
            $image->readImage($path);
            $image->setImageFormat('png32');
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            if ($width < 1 || $height < 1) {
                $image->clear();

                return null;
            }
            $scale = $minSide / min($width, $height);
            if ($scale > 1.01 || $scale < 0.99) {
                $image->resizeImage(
                    (int) round($width * $scale),
                    (int) round($height * $scale),
                    \Imagick::FILTER_LANCZOS,
                    1
                );
            }
            wp_mkdir_p($dir['path']);
            $image->writeImage($dest);
            $image->clear();
        } catch (\Throwable $e) {
            return null;
        }

        return is_readable($dest) ? $dest : null;
    }

    private static function squareSource(string $path): ?string
    {
        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor)) {
            return self::squareSourceImagick($path);
        }

        $size = $editor->get_size();
        $width = (int) ($size['width'] ?? 0);
        $height = (int) ($size['height'] ?? 0);
        if ($width < 1 || $height < 1) {
            return null;
        }

        if ($width !== $height) {
            $side = min($width, $height);
            $x = (int) floor(($width - $side) / 2);
            $y = (int) floor(($height - $side) / 2);
            $cropped = $editor->crop($x, $y, $side, $side);
            if (is_wp_error($cropped)) {
                return null;
            }
        }

        $dir = self::directory();
        $dest = $dir['path'] . '/_square.png';
        $saved = $editor->save($dest, 'image/png');
        if (is_wp_error($saved)) {
            return self::squareSourceImagick($path);
        }

        $file = is_array($saved) ? (string) ($saved['path'] ?? $dest) : $dest;

        return is_readable($file) ? $file : null;
    }

    private static function squareSourceImagick(string $path): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick($path);
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $side = min($width, $height);
            $x = (int) floor(($width - $side) / 2);
            $y = (int) floor(($height - $side) / 2);
            $image->cropImage($side, $side, $x, $y);
            $image->setImagePage(0, 0, 0, 0);
            $image->setImageFormat('png32');
            $dest = self::directory()['path'] . '/_square.png';
            $image->writeImage($dest);
            $image->clear();

            return is_readable($dest) ? $dest : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function resizeTo(string $source, string $dest, int $px): bool
    {
        $editor = wp_get_image_editor($source);
        if (! is_wp_error($editor)) {
            $resized = $editor->resize($px, $px, false);
            if (! is_wp_error($resized)) {
                $saved = $editor->save($dest, 'image/png');
                if (! is_wp_error($saved)) {
                    return is_readable($dest);
                }
            }
        }

        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            $image = new \Imagick($source);
            $image->resizeImage($px, $px, \Imagick::FILTER_LANCZOS, 1);
            $image->setImageFormat('png32');
            $image->writeImage($dest);
            $image->clear();

            return is_readable($dest);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function flatten(string $path, int $size, string $color): void
    {
        if (class_exists(\Imagick::class)) {
            try {
                $fg = new \Imagick($path);
                $bg = new \Imagick();
                $bg->newImage($size, $size, new \ImagickPixel($color));
                $bg->setImageFormat('png');
                $bg->compositeImage($fg, \Imagick::COMPOSITE_OVER, 0, 0);
                $bg->writeImage($path);
                $fg->clear();
                $bg->clear();

                return;
            } catch (\Throwable $e) {
                // Fall through to GD.
            }
        }

        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $src = @imagecreatefrompng($path);
        if ($src === false) {
            return;
        }

        $rgb = self::hexToRgb($color);
        $dst = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($dst, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($dst, 0, 0, $size, $size, $bg);
        imagecopy($dst, $src, 0, 0, 0, 0, $size, $size);
        imagepng($dst, $path);
        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $color): array
    {
        $hex = ltrim($color, '#');
        if (strlen($hex) !== 6) {
            return [255, 255, 255];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param array{path: string, url: string} $dir
     * @param array<string, string> $urls
     */
    private static function writeManifest(array $dir, array $urls): string
    {
        $name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $payload = [
            'name' => $name,
            'short_name' => $name,
            'icons' => [
                [
                    'src' => $urls['192'],
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => $urls['512'],
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
            'display' => 'browser',
        ];

        $theme = Config::themeColor();
        $background = Config::backgroundColor();
        if ($theme !== '') {
            $payload['theme_color'] = $theme;
        }
        $payload['background_color'] = $background !== '' ? $background : '#ffffff';

        $file = $dir['path'] . '/site.webmanifest';
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || file_put_contents($file, $json) === false) {
            return '';
        }

        return $dir['url'] . '/site.webmanifest';
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackDerived(int $attachmentId, string $hash, bool $isSvg): array
    {
        $defaults = Config::defaults();
        $urls = [];
        if ($isSvg) {
            $svgUrl = wp_get_attachment_url($attachmentId);
            if (is_string($svgUrl) && $svgUrl !== '') {
                $urls['svg'] = $svgUrl;
            }
        }
        $map = [
            '32' => 'icon_32',
            '180' => 'apple_touch',
            '192' => 'icon_192',
            '512' => 'icon_512',
        ];
        foreach ($map as $key => $defaultKey) {
            if ($defaults[$defaultKey] !== '') {
                $urls[$key] = $defaults[$defaultKey];
            }
        }

        return [
            'id' => $attachmentId,
            'hash' => $hash,
            'svg' => $isSvg,
            'raster_from' => 'fallback',
            'urls' => $urls,
            'files' => [],
        ];
    }

    /**
     * @param array<string, mixed> $derived
     * @return array<string, mixed>
     */
    private static function persist(array $derived): array
    {
        update_option(self::OPTION_DERIVED, $derived, false);

        return $derived;
    }

    private static function deleteFile(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
