<?php

namespace Beam\Core\Modules\SvgUpload;

defined('ABSPATH') || exit;

/**
 * SVG in the media library: allowed only for users who can upload files, sanitized on upload (Sanitizer), with
 * width/height from the file so the library shows a preview at the right size.
 */
final class SvgUpload
{
    private const MIME = 'image/svg+xml';

    public static function register(): void
    {
        add_filter('upload_mimes', [self::class, 'mimes']);
        add_filter('wp_check_filetype_and_ext', [self::class, 'checkFiletype'], 10, 5);
        add_filter('wp_handle_upload_prefilter', [self::class, 'sanitizeUpload']);
        add_filter('wp_handle_sideload_prefilter', [self::class, 'sanitizeUpload']);
        add_filter('wp_generate_attachment_metadata', [self::class, 'metadata'], 10, 2);
        add_filter('wp_prepare_attachment_for_js', [self::class, 'forJs'], 10, 2);
    }

    /**
     * @param array<string, string> $mimes
     * @return array<string, string>
     */
    public static function mimes(array $mimes): array
    {
        if (current_user_can('upload_files')) {
            $mimes['svg'] = self::MIME;
        }

        return $mimes;
    }

    /**
     * WordPress cannot detect the type of an SVG from its contents; confirm it by extension + the <svg> root.
     *
     * @param array<string, mixed> $data
     * @param array<string, string>|null $mimes
     * @return array<string, mixed>
     */
    public static function checkFiletype(array $data, string $file, string $filename, ?array $mimes = null, mixed $realMime = false): array
    {
        if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'svg') {
            return $data;
        }
        $allowed = $mimes ?? get_allowed_mime_types();
        if (! in_array(self::MIME, $allowed, true)) {
            return $data;
        }
        $head = is_readable($file) ? (string) file_get_contents($file, false, null, 0, 4096) : '';
        if (stripos($head, '<svg') === false) {
            return $data;
        }

        return ['ext' => 'svg', 'type' => self::MIME, 'proper_filename' => $data['proper_filename'] ?? false];
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public static function sanitizeUpload(array $file): array
    {
        $name = (string) ($file['name'] ?? '');
        $path = (string) ($file['tmp_name'] ?? '');
        if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'svg' || $path === '' || ! is_readable($path)) {
            return $file;
        }

        $clean = Sanitizer::sanitize((string) file_get_contents($path));
        if ($clean === null || file_put_contents($path, $clean) === false) {
            $file['error'] = 'Файл SVG повреждён или содержит недопустимый код.';
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function metadata(array $metadata, int $attachmentId): array
    {
        if (get_post_mime_type($attachmentId) !== self::MIME) {
            return $metadata;
        }
        $path = (string) get_attached_file($attachmentId);
        [$width, $height] = Sanitizer::dimensions(is_readable($path) ? (string) file_get_contents($path) : '');
        $metadata['width'] = $width;
        $metadata['height'] = $height;
        $metadata['file'] = _wp_relative_upload_path($path);

        return $metadata;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function forJs(array $response, \WP_Post $attachment): array
    {
        if (($response['mime'] ?? '') !== self::MIME) {
            return $response;
        }
        $meta = wp_get_attachment_metadata($attachment->ID);
        $width = is_array($meta) ? (int) ($meta['width'] ?? 0) : 0;
        $height = is_array($meta) ? (int) ($meta['height'] ?? 0) : 0;
        $url = (string) ($response['url'] ?? '');
        $size = ['url' => $url, 'width' => $width ?: 150, 'height' => $height ?: 150, 'orientation' => $width >= $height ? 'landscape' : 'portrait'];
        $response['image'] = ['src' => $url, 'width' => $size['width'], 'height' => $size['height']];
        $response['thumb'] = $response['image'];
        $response['sizes'] = ['full' => $size, 'medium' => $size, 'thumbnail' => $size];

        return $response;
    }
}
