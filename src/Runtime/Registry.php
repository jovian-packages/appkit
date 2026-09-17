<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Runtime;

use WeakReference;

final class Registry
{
    /** @var array<int, WeakReference<ObjCObject>> */
    private static array $map = [];

    /** @var array<string, class-string<ObjCObject>> runtime class name → PHP class, resolved by kind */
    private static array $kinds = [];

    public static function box(int $handle): ?ObjCObject
    {
        if ($handle === 0) {
            return null;
        }

        $existing = self::find($handle);
        if (! is_null($existing)) {
            return $existing;
        }

        if (! Bridge::isValid($handle)) {
            return null;
        }

        $phpClass = self::phpClassFor($handle);
        $object = new $phpClass($handle);
        self::$map[$handle] = WeakReference::create($object);

        return $object;
    }

    public static function evictIfSelf(ObjCObject $object): bool
    {
        $handle = $object->handle;
        if (! isset(self::$map[$handle])) {
            return false;
        }

        $current = self::$map[$handle]->get();
        if ($current !== $object) {
            return false;
        }

        unset(self::$map[$handle]);

        return true;
    }

    public static function reset(): void
    {
        self::$map = [];
        self::$kinds = [];
    }

    private static function find(int $handle): ?ObjCObject
    {
        if (! isset(self::$map[$handle])) {
            return null;
        }

        $object = self::$map[$handle]->get();
        if (is_null($object)) {
            unset(self::$map[$handle]);

            return null;
        }

        return $object;
    }

    /**
     * @return class-string<ObjCObject>
     */
    private static function phpClassFor(int $handle): string
    {
        $objcName = Bridge::className($handle);
        if (! is_string($objcName) || $objcName === '') {
            return ObjCObject::class;
        }

        // WP1 generates ClassMap::phpClass(); until then every handle boxes as ObjCObject.
        if (! class_exists(ClassMap::class)) {
            return ObjCObject::class;
        }

        $mapped = ClassMap::phpClass($objcName);
        if (is_string($mapped) && class_exists($mapped)) {
            return $mapped;
        }

        return self::$kinds[$objcName] ??= self::byKind($handle);
    }

    /**
     * @return class-string<ObjCObject>
     */
    private static function byKind(int $handle): string
    {
        $mapped = ClassMap::phpClassByKind(static fn (string $name): bool => Bridge::isKindOfClass($handle, $name));

        return is_string($mapped) && class_exists($mapped) ? $mapped : ObjCObject::class;
    }
}
