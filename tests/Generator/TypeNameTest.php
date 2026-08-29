<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\TypeName;

it('does not treat NSArray of object pointers as an out-parameter', function () {
    expect(TypeName::isOutPointer('NSArray<NSUserInterfaceCompressionOptions *> *'))->toBeFalse()
        ->and(TypeName::isOutPointer('NSArray<__kindof NSView *> *'))->toBeFalse();
});

it('still treats NSError pointers and C arrays as out-parameters', function () {
    expect(TypeName::isOutPointer('NSError **'))->toBeTrue()
        ->and(TypeName::isOutPointer('NSRangePointer'))->toBeTrue()
        ->and(TypeName::isOutPointer('NSInteger *'))->toBeTrue()
        ->and(TypeName::isOutPointer('NSRect[_Nonnull 4]'))->toBeTrue();
});

it('treats CoreGraphics Ref types as in-parameters', function () {
    expect(TypeName::isOutPointer('CGColorRef'))->toBeFalse()
        ->and(TypeName::isOutPointer('CGEventRef'))->toBeFalse()
        ->and(TypeName::isOutPointer('CGImageRef'))->toBeFalse();
});

it('counts stars outside generics so NSArray<Foo *> * is one pointer', function () {
    expect(TypeName::starsOutsideGenerics('NSArray<NSUserInterfaceCompressionOptions *> *'))->toBe(1)
        ->and(TypeName::starsOutsideGenerics('NSError **'))->toBe(2)
        ->and(TypeName::starsOutsideGenerics('NSView *'))->toBe(1)
        ->and(TypeName::starsOutsideGenerics('NSInteger'))->toBe(0)
        ->and(TypeName::starsOutsideGenerics('id<NSWindowDelegate>'))->toBe(0);
});

it('treats only id, instancetype, or a single star over an ObjC class as a handle type', function () {
    expect(TypeName::isHandleType('id'))->toBeTrue()
        ->and(TypeName::isHandleType('instancetype'))->toBeTrue()
        ->and(TypeName::isHandleType('id<NSWindowDelegate>'))->toBeTrue()
        ->and(TypeName::isHandleType('NSView *'))->toBeTrue()
        ->and(TypeName::isHandleType('CALayer *'))->toBeTrue()
        ->and(TypeName::isHandleType('NSInteger'))->toBeFalse()
        ->and(TypeName::isHandleType('NSUInteger'))->toBeFalse()
        ->and(TypeName::isHandleType('NSTimeInterval'))->toBeFalse()
        ->and(TypeName::isHandleType('NSControlStateValue'))->toBeFalse()
        ->and(TypeName::isHandleType('NSWindowLevel'))->toBeFalse()
        ->and(TypeName::isHandleType('NSModalResponse'))->toBeFalse()
        ->and(TypeName::isHandleType('NSInteger *'))->toBeFalse()
        ->and(TypeName::isHandleType('NSArray<NSView *> *'))->toBeFalse();
});
