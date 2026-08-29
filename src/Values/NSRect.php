<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Values;

readonly class NSRect
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['x'] ?? 0),
            (float) ($data['y'] ?? 0),
            (float) ($data['width'] ?? 0),
            (float) ($data['height'] ?? 0),
        );
    }

    /**
     * @return list<float>
     */
    public function toArgs(): array
    {
        return [$this->x, $this->y, $this->width, $this->height];
    }
}
