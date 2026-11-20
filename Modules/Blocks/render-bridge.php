<?php

defined('ABSPATH') || exit;

if (! isset($block) || ! is_array($block) || ! class_exists(Beam\Core\Modules\Blocks\Renderer::class)) {
    return;
}

Beam\Core\Modules\Blocks\Renderer::present(
    $block,
    isset($content) && is_string($content) ? $content : '',
    ! empty($is_preview),
    $post_id ?? 0,
    $wp_block ?? null,
    $context ?? false,
);
