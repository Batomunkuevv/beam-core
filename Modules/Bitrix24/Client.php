<?php

namespace Beam\Core\Modules\Bitrix24;

defined('ABSPATH') || exit;

final class Client
{
    private const TIMEOUT = 10;

    public function __construct(private readonly string $webhook) {}

    public static function make(): self
    {
        return new self(Config::webhook());
    }

    public function isReady(): bool
    {
        return Config::isValid($this->webhook);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function call(string $method, array $params = []): Result
    {
        if (! $this->isReady()) {
            return Result::failure('not_configured', 'Вебхук Битрикс24 не задан.');
        }

        if (preg_match('/^[a-z0-9_.]+$/i', $method) !== 1) {
            return Result::failure('invalid_method', $method);
        }

        try {
            $filtered = apply_filters('beam_bitrix24_timeout', self::TIMEOUT, $method);
            $timeout = is_numeric($filtered) ? (int) $filtered : self::TIMEOUT;
            $response = wp_remote_post($this->webhook . $method . '.json', [
                'timeout'     => max(1, $timeout),
                'headers'     => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept'       => 'application/json',
                ],
                'body'        => (string) wp_json_encode($params, JSON_UNESCAPED_UNICODE),
                'data_format' => 'body',
            ]);
            $result = self::parse($response);
        } catch (\Throwable $exception) {
            $result = Result::failure('exception', $exception->getMessage());
        }

        if (! $result->ok) {
            $this->report($method, $result);
        }

        Log::add($method, $result);
        do_action('beam_bitrix24_response', $result, $method, $params);

        return $result;
    }

    private static function parse(mixed $response): Result
    {
        if (is_wp_error($response)) {
            return Result::failure('http_error', $response->get_error_message());
        }

        if (! is_array($response)) {
            return Result::failure('bad_response');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (is_array($body) && isset($body['error'])) {
            $error = is_scalar($body['error']) ? (string) $body['error'] : 'error';
            $description = isset($body['error_description']) && is_scalar($body['error_description']) ? (string) $body['error_description'] : '';

            return Result::failure($error, $description, $status);
        }

        if ($status < 200 || $status >= 300 || ! is_array($body) || ! array_key_exists('result', $body)) {
            return Result::failure('bad_response', sprintf('HTTP %d', $status), $status);
        }

        return Result::success($body['result'], $status);
    }

    private function report(string $method, Result $result): void
    {
        $details = $result->description !== '' ? ' (' . $this->redact($result->description) . ')' : '';

        error_log(sprintf('BE.AM Core Bitrix24: %s — %s%s', $method, $result->error, $details)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    private function redact(string $text): string
    {
        $token = preg_match('~/rest/\d+/([a-z0-9]+)/~i', $this->webhook, $match) === 1 ? $match[1] : '';

        return $token !== '' ? str_replace($token, '••••', $text) : $text;
    }
}
