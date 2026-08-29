<?php

declare(strict_types=1);

use AppKit\Bridge\Bridge as ExtBridge;
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
    echo "LIFETIME_OK\n";
});

it('releases the handle when the last PHP reference drops', function () {
    $handle = appkitViewHandle();
    $object = ObjCObject::box($handle);

    expect($object)->not->toBeNull()
        ->and(ExtBridge::isValid($handle))->toBeTrue();

    unset($object);
    gc_collect_cycles();

    expect(ExtBridge::isValid($handle))->toBeFalse()
        ->and(ObjCObject::box($handle))->toBeNull();
});

it('does not evict or release a recycled handle from a stale destructor', function () {
    $handle = appkitViewHandle();
    $stale = ObjCObject::box($handle);

    expect($stale)->not->toBeNull();

    Registry::evictIfSelf($stale);
    $fresh = ObjCObject::box($handle);

    expect($fresh)->not->toBeNull()
        ->and($fresh)->not->toBe($stale);

    unset($stale);
    gc_collect_cycles();

    expect(ExtBridge::isValid($handle))->toBeTrue()
        ->and(ObjCObject::box($handle))->toBe($fresh);
});

it('skips release after the shutdown flag is set', function () {
    $handle = appkitViewHandle();
    $object = ObjCObject::box($handle);

    expect($object)->not->toBeNull();

    Lifetime::markShuttingDown();
    unset($object);
    gc_collect_cycles();

    expect(ExtBridge::isValid($handle))->toBeTrue();

    Lifetime::reset();
    ExtBridge::release($handle);
});
