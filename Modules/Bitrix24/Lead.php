<?php

namespace Beam\Core\Modules\Bitrix24;

defined('ABSPATH') || exit;

final class Lead
{
    public const METHOD = 'crm.lead.add';

    private const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    /** @var array<string, mixed> */
    private array $fields = [];

    /** @var list<array{VALUE: string, VALUE_TYPE: string}> */
    private array $phones = [];

    /** @var list<array{VALUE: string, VALUE_TYPE: string}> */
    private array $emails = [];

    /** @var array<string, mixed> */
    private array $context = [];

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        return $this->set('TITLE', self::line($title));
    }

    public function name(string $fullName): self
    {
        foreach (Person::splitName(self::line($fullName)) as $code => $value) {
            $this->fields[ $code ] = $value;
        }

        return $this;
    }

    public function phone(string $phone, string $type = 'MOBILE'): self
    {
        $value = Person::phone($phone);

        if ($value !== '') {
            $this->phones[] = ['VALUE' => $value, 'VALUE_TYPE' => $type];
        }

        return $this;
    }

    public function email(string $email, string $type = 'WORK'): self
    {
        $value = sanitize_email($email);

        if ($value !== '' && is_email($value) !== false) {
            $this->emails[] = ['VALUE' => $value, 'VALUE_TYPE' => $type];
        }

        return $this;
    }

    /**
     * @param string|list<string> $comments
     */
    public function comments(string|array $comments): self
    {
        $text = is_array($comments) ? implode("\n", array_map('strval', $comments)) : $comments;

        return $this->set('COMMENTS', trim(sanitize_textarea_field($text)));
    }

    public function source(string $id, string $description = ''): self
    {
        $this->set('SOURCE_ID', self::line($id));

        return $this->set('SOURCE_DESCRIPTION', self::line($description));
    }

    public function assigned(int $userId): self
    {
        return $userId > 0 ? $this->set('ASSIGNED_BY_ID', $userId) : $this;
    }

    public function opened(bool $opened = true): self
    {
        return $this->set('OPENED', $opened ? 'Y' : 'N');
    }

    public function field(string $code, mixed $value): self
    {
        $code = strtoupper(trim($code));

        return preg_match('/^[A-Z0-9_]+$/', $code) === 1 ? $this->set($code, $value) : $this;
    }

    /**
     * Метки из переданного массива, иначе из запроса, cookie и адреса страницы, с которой пришла заявка.
     *
     * @param array<int|string, mixed>|null $source
     */
    public function utm(?array $source = null): self
    {
        $sources = $source !== null ? [$source] : [$_POST, $_GET, $_COOKIE, self::refererQuery()]; // phpcs:ignore WordPress.Security.NonceVerification

        foreach (self::UTM as $key) {
            foreach ($sources as $values) {
                if (isset($values[ $key ]) && is_scalar($values[ $key ])) {
                    $value = mb_substr(self::line(wp_unslash((string) $values[ $key ])), 0, 255);

                    if ($value !== '') {
                        $this->fields[ strtoupper($key) ] = $value;

                        break;
                    }
                }
            }
        }

        return $this;
    }

    public function context(string $key, mixed $value): self
    {
        $this->context[ $key ] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = $this->fields;

        if ($this->phones !== []) {
            $fields['PHONE'] = $this->phones;
        }

        if ($this->emails !== []) {
            $fields['EMAIL'] = $this->emails;
        }

        $filtered = apply_filters('beam_bitrix24_lead_fields', $fields, $this->context);

        if (! is_array($filtered)) {
            return $fields;
        }

        return array_filter($filtered, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array{fields: array<string, mixed>, params: array<string, string>}
     */
    public function payload(): array
    {
        return [
            'fields' => $this->fields(),
            'params' => ['REGISTER_SONET_EVENT' => 'Y'],
        ];
    }

    public function send(?Client $client = null): Result
    {
        $client ??= Client::make();

        if (! $client->isReady()) {
            return Result::failure('not_configured');
        }

        $payload = $this->payload();
        $result = $client->call(self::METHOD, $payload);
        do_action('beam_bitrix24_sent', $result, $payload, $this->context);

        return $result;
    }

    /**
     * Отправка после ответа посетителю: страница не ждёт Битрикс24.
     */
    public function sendLater(): void
    {
        if (! Config::isConfigured()) {
            return;
        }

        add_action('shutdown', function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }

            $this->send();
        }, 0);
    }

    private function set(string $code, mixed $value): self
    {
        if ($value === '' || $value === null) {
            unset($this->fields[ $code ]);

            return $this;
        }

        $this->fields[ $code ] = $value;

        return $this;
    }

    private static function line(string $value): string
    {
        return trim(sanitize_text_field($value));
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function refererQuery(): array
    {
        $referer = wp_get_referer();
        $query = is_string($referer) ? (string) wp_parse_url($referer, PHP_URL_QUERY) : '';
        $values = [];

        if ($query !== '') {
            parse_str($query, $values);
        }

        return $values;
    }
}
