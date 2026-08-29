<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Values;

readonly class NSSize
{
    public function __construct(
        public float $width,
        public float $height,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['width'] ?? 0),
            (float) ($data['height'] ?? 0),
        );
    }

    /**
     * @return list<float>
     */
    public function toArgs(): array
    {
        return [$this->width, $this->height];
    }
}
