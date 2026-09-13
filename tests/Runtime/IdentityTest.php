<?php

declare(strict_types=1);

use AppKit\NS\NSControl\NSControl as ExtNSControl;
use Jovian\Bindings\AppKit\Runtime\Bridge;
use Jovian\Bindings\AppKit\Runtime\Delegate;
use Jovian\Bindings\AppKit\Runtime\Lifetime;
use Jovian\Bindings\AppKit\Runtime\ObjCObject;
use Jovian\Bindings\AppKit\Runtime\Registry;

beforeEach(function () {
    if (! appkitExtensionLoaded()) {
        test()->markTestSkipped('ext-appkit is not loaded');
    }

    Lifetime::reset();
    Registry::reset();
    appkitSharedApplication();
});

afterEach(function () {
    Registry::reset();
    Lifetime::reset();
});

afterAll(function () {
    echo "IDENTITY_OK\n";
});

it('boxes the same handle to the same instance', function () {
    $handle = appkitViewHandle();
    $first = ObjCObject::box($handle);
    $second = ObjCObject::box($handle);

    expect($first)->not->toBeNull()
        ->and($second)->toBe($first)
        ->and($first->handle)->toBe($handle);
});

it('asks Bridge for the runtime class instead of trusting the call site', function () {
    $handle = appkitButtonHandle();
    $object = ObjCObject::box($handle);

    expect($object)->not->toBeNull()
        ->and($object->className())->toBe('NSButton')
        ->and($object->isKindOfClass('NSView'))->toBeTrue()
        ->and($object->isKindOfClass('NSWindow'))->toBeFalse()
        ->and($object->isValid())->toBeTrue();
});

it('resolves a callback sender to the instance already held', function () {
    $handle = appkitButtonHandle();
    $button = ObjCObject::box($handle);
    $seen = null;

    Bridge::setAction($handle, function (?ObjCObject $sender) use (&$seen): void {
        $seen = $sender;
    });

    ExtNSControl::performClick($handle, 0);

    expect($seen)->toBe($button);
});

it('returns null for handle zero', function () {
    expect(ObjCObject::box(0))->toBeNull();
});

it('wraps delegateNew, delegateOn, and delegateOff', function () {
    $delegate = new Delegate('NSWindowDelegate');

    expect($delegate->handle())->not->toBe(0)
        ->and($delegate->on('windowShouldClose:', fn () => false))->toBeTrue();

    $delegate->off('windowShouldClose:');
});

it('returns 0 from pointerOf and adopt for invalid input', function () {
    expect(Bridge::pointerOf(0))->toBe(0)
        ->and(Bridge::adopt('NSObject', 0))->toBe(0);
});

it('round-trips a handle through the pointer seam', function () {
    $handle = appkitViewHandle();
    $pointer = Bridge::pointerOf($handle);

    expect($pointer)->not->toBe(0);

    $adopted = Bridge::adopt('NSView', $pointer);

    expect($adopted)->not->toBe(0)
        ->and(Bridge::isValid($adopted))->toBeTrue()
        ->and(ObjCObject::box($adopted)?->className())->toBe('NSView');
});

it('rejects adopt when the pointer is not kind-of the named class', function () {
    $handle = appkitViewHandle();
    $pointer = Bridge::pointerOf($handle);

    expect(Bridge::adopt('NSWindow', $pointer))->toBe(0);
});

it('mirrors Bridge::pointerOf on the instance', function () {
    $handle = appkitViewHandle();
    $object = ObjCObject::box($handle);

    expect($object)->not->toBeNull()
        ->and($object->pointerOf())->toBe(Bridge::pointerOf($handle))
        ->and($object->pointerOf())->not->toBe(0);
});
