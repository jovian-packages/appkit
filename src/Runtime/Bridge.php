<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Runtime;

use AppKit\Bridge\Bridge as ExtBridge;

final class Bridge
{
    public static function retain(int $handle): bool
    {
        return ExtBridge::retain($handle);
    }

    public static function release(int $handle): void
    {
        ExtBridge::release($handle);
    }

    public static function isValid(int $handle): bool
    {
        return ExtBridge::isValid($handle);
    }

    public static function className(int $handle): ?string
    {
        $name = ExtBridge::className($handle);

        return is_string($name) ? $name : null;
    }

    public static function isKindOfClass(int $handle, string $className): bool
    {
        return ExtBridge::isKindOfClass($handle, $className);
    }

    public static function pump(float $timeout): int
    {
        return ExtBridge::pump($timeout);
    }

    public static function setAction(int $handle, callable $callable): bool
    {
        return ExtBridge::setAction($handle, static function (int $sender) use ($callable): mixed {
            return $callable(ObjCObject::box($sender));
        });
    }

    public static function removeAction(int $handle): void
    {
        ExtBridge::removeAction($handle);
    }

    public static function observeNotification(int $object, string $name, callable $callable): int
    {
        return ExtBridge::observeNotification($object, $name, static function (int $obj, string $notification) use ($callable): mixed {
            return $callable(ObjCObject::box($obj), $notification);
        });
    }

    public static function removeObserver(int $token): void
    {
        ExtBridge::removeObserver($token);
    }

    public static function delegateNew(string $protocolName): int
    {
        return ExtBridge::delegateNew($protocolName);
    }

    public static function delegateOn(int $delegate, string $selector, callable $callable): bool
    {
        return ExtBridge::delegateOn($delegate, $selector, static function (mixed ...$args) use ($callable): mixed {
            return $callable(...array_map(self::boxArgument(...), $args));
        });
    }

    public static function delegateOff(int $delegate, string $selector): void
    {
        ExtBridge::delegateOff($delegate, $selector);
    }

    private static function boxArgument(mixed $value): mixed
    {
        if (! is_int($value)) {
            return $value;
        }

        $boxed = ObjCObject::box($value);

        return is_null($boxed) ? $value : $boxed;
    }
}
