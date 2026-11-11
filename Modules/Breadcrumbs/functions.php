<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('breadcrumbs_items')) {
    function breadcrumbs_items(array $args = []): array
    {
        return Beam\Core\Modules\Breadcrumbs\Breadcrumbs::items($args);
    }
}

if (! function_exists('breadcrumbs')) {
    function breadcrumbs(array $args = []): void
    {
        echo Beam\Core\Modules\Breadcrumbs\Renderer::render(breadcrumbs_items($args), $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
