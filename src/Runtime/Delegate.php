<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Runtime;

final class Delegate
{
    private readonly ?ObjCObject $object;

    public function __construct(string $protocolName)
    {
        $this->object = ObjCObject::box(Bridge::delegateNew($protocolName));
    }

    public function handle(): int
    {
        return is_null($this->object) ? 0 : $this->object->handle;
    }

    public function on(string $selector, callable $callback): bool
    {
        return Bridge::delegateOn($this->handle(), $selector, $callback);
    }

    public function off(string $selector): void
    {
        Bridge::delegateOff($this->handle(), $selector);
    }
}
