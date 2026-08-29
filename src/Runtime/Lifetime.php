<?php

declare(strict_types=1);

namespace Jovian\Bindings\AppKit\Runtime;

final class Lifetime
{
    private static bool $shuttingDown = false;

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        register_shutdown_function(static function (): void {
            self::markShuttingDown();
        });
    }

    public static function markShuttingDown(): void
    {
        self::$shuttingDown = true;
    }

    public static function isShuttingDown(): bool
    {
        return self::$shuttingDown;
    }

    public static function reset(): void
    {
        self::$shuttingDown = false;
    }
}
