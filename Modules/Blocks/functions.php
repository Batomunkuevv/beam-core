<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('beam_render_block')) {
    function beam_render_block(string $slug, array $fields = [], array $args = []): void
    {
        echo Beam\Core\Modules\Blocks\Renderer::markup($slug, $fields, $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if (! function_exists('beam_field')) {
    function beam_field(string $name, mixed $fallback = null): mixed
    {
        return Beam\Core\Modules\Blocks\Fields::value($name, $fallback);
    }
}
