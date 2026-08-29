<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Values;

readonly class NSRange
{
    public function __construct(
        public int $location,
        public int $length,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['location'] ?? 0),
            (int) ($data['length'] ?? 0),
        );
    }

    /**
     * @return list<int>
     */
    public function toArgs(): array
    {
        return [$this->location, $this->length];
    }
}
