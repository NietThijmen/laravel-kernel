<?php

namespace Nietthijmen\LaravelKernel\Data;

final readonly class Viewport extends Data
{
    public function __construct(
        public int $width,
        public int $height,
        public ?int $refreshRate,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            width: (int) $data['width'],
            height: (int) $data['height'],
            refreshRate: self::int($data['refresh_rate'] ?? null),
            raw: $data,
        );
    }
}
