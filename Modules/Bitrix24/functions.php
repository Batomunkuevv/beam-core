<?php

defined('ABSPATH') || exit;

if (! function_exists('beam_bitrix24')) {
    function beam_bitrix24(): Beam\Core\Modules\Bitrix24\Client
    {
        return Beam\Core\Modules\Bitrix24\Client::make();
    }
}

if (! function_exists('beam_bitrix24_lead')) {
    function beam_bitrix24_lead(): Beam\Core\Modules\Bitrix24\Lead
    {
        return Beam\Core\Modules\Bitrix24\Lead::make();
    }
}
