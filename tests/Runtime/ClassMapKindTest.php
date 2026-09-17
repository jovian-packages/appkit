<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\GC\GCExtendedGamepad;
use Jovian\Bindings\AppKit\NS\NSView;
use Jovian\Bindings\AppKit\Runtime\ClassMap;

it('resolves an unnamed runtime class to its most-derived bound ancestor', function (): void {
    $kinds = ['NSObject', 'NSResponder', 'NSView'];

    expect(ClassMap::phpClass('_NSPrivateView'))->toBeNull()
        ->and(ClassMap::phpClassByKind(fn (string $name): bool => in_array($name, $kinds, true)))->toBe(NSView::class);
});

it('resolves a private gamepad profile such as GCDualShockGamepad', function (): void {
    $kinds = ['NSObject', 'GCPhysicalInputProfile', 'GCExtendedGamepad'];

    expect(ClassMap::phpClassByKind(fn (string $name): bool => in_array($name, $kinds, true)))->toBe(GCExtendedGamepad::class);
});

it('returns null when the object is a kind of no bound class', function (): void {
    expect(ClassMap::phpClassByKind(fn (string $name): bool => false))->toBeNull();
});
