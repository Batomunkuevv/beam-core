<?php

namespace Beam\Core\Modules\Bitrix24;

defined('ABSPATH') || exit;

final class Result
{
    private function __construct(
        public readonly bool $ok,
        public readonly mixed $data,
        public readonly string $error,
        public readonly string $description,
        public readonly int $status,
    ) {}

    public static function success(mixed $data, int $status = 200): self
    {
        return new self(true, $data, '', '', $status);
    }

    public static function failure(string $error, string $description = '', int $status = 0): self
    {
        return new self(false, null, $error, $description, $status);
    }

    public function id(): ?int
    {
        return $this->ok && is_numeric($this->data) ? (int) $this->data : null;
    }

    /**
     * @return array{ok: bool, id: int|null, error: string, description: string, status: int, data: mixed}
     */
    public function toArray(): array
    {
        return [
            'ok'          => $this->ok,
            'id'          => $this->id(),
            'error'       => $this->error,
            'description' => $this->description,
            'status'      => $this->status,
            'data'        => $this->data,
        ];
    }
}
