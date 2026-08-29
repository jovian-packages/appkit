<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Values;

readonly class NSEdgeInsets
{
    public function __construct(
        public float $top,
        public float $left,
        public float $bottom,
        public float $right,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['top'] ?? 0),
            (float) ($data['left'] ?? 0),
            (float) ($data['bottom'] ?? 0),
            (float) ($data['right'] ?? 0),
        );
    }

    /**
     * @return list<float>
     */
    public function toArgs(): array
    {
        return [$this->top, $this->left, $this->bottom, $this->right];
    }
}
