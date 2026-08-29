<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Runtime;

class ObjCObject
{
    public readonly int $handle;

    public function __construct(int $handle)
    {
        $this->handle = $handle;
        Lifetime::register();
        Bridge::retain($handle);
    }

    public function __destruct()
    {
        if (Lifetime::isShuttingDown()) {
            return;
        }

        if (Registry::evictIfSelf($this)) {
            Bridge::release($this->handle);
        }
    }

    public static function box(int $handle): ?ObjCObject
    {
        return Registry::box($handle);
    }

    public function className(): ?string
    {
        $name = Bridge::className($this->handle);

        return is_string($name) ? $name : null;
    }

    public function isValid(): bool
    {
        return Bridge::isValid($this->handle);
    }

    public function isKindOfClass(string $className): bool
    {
        return Bridge::isKindOfClass($this->handle, $className);
    }

    public function onAction(callable $callback): bool
    {
        return Bridge::setAction($this->handle, $callback);
    }

    public function onNotification(string $name, callable $callback): int
    {
        return Bridge::observeNotification($this->handle, $name, $callback);
    }

    public function removeObserver(int $token): void
    {
        Bridge::removeObserver($token);
    }
}
