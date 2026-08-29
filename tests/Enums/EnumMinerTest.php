<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\EnumKind;
use Jovian\Bindings\AppKit\Generator\EnumMiner;

it('mines NSWindowStyleMask bit values from the SDK, including availability-annotated cases', function () {
    $defs = EnumMiner::definitions(appkitRequireSdkFrameworks());

    expect($defs)->toHaveKey('NSWindowStyleMask')
        ->and($defs['NSWindowStyleMask']['kind'])->toBe(EnumKind::OPTIONS)
        ->and($defs['NSWindowStyleMask']['cases']['NSWindowStyleMaskBorderless'])->toBe(0)
        ->and($defs['NSWindowStyleMask']['cases']['NSWindowStyleMaskTitled'])->toBe(1 << 0)
        ->and($defs['NSWindowStyleMask']['cases']['NSWindowStyleMaskClosable'])->toBe(1 << 1)
        ->and($defs['NSWindowStyleMask']['cases']['NSWindowStyleMaskFullSizeContentView'])->toBe(1 << 15)
        ->and($defs['NSWindowStyleMask']['cases']['NSWindowStyleMaskTexturedBackground'])->toBe(1 << 8);
});

it('resolves 1ULL shifts, named identifiers, bitwise OR, and the sign bit', function () {
    $defs = EnumMiner::definitions(appkitRequireSdkFrameworks());

    expect($defs['NSEventType']['cases']['NSEventTypeLeftMouseDown'])->toBe(1)
        ->and($defs['NSEventMask']['cases']['NSEventMaskLeftMouseDown'])->toBe(1 << 1)
        ->and($defs['NSAlignmentOptions']['cases']['NSAlignMinXInward'])->toBe(1 << 0)
        ->and($defs['NSAlignmentOptions']['cases']['NSAlignAllEdgesInward'])->toBe((1 << 0) | (1 << 2) | (1 << 1) | (1 << 3))
        ->and($defs['NSAlignmentOptions']['cases']['NSAlignRectFlipped'])->toBe(PHP_INT_MIN);
});

it('evaluates hex literals, integer suffixes, NSUIntegerMax, and IOKit defines', function () {
    $defs = EnumMiner::definitions(appkitRequireSdkFrameworks());

    expect($defs['NSUnderlineStyle']['cases']['NSUnderlineStyleSingle'])->toBe(0x01)
        ->and($defs['NSUnderlineStyle']['cases']['NSUnderlineStyleByWord'])->toBe(0x8000)
        ->and($defs['NSWindowDepth']['cases']['NSWindowDepthTwentyfourBitRGB'])->toBe(0x208)
        ->and($defs['NSFontDescriptorSymbolicTraits']['cases']['NSFontDescriptorTraitItalic'])->toBe(1 << 0)
        ->and($defs['NSFontDescriptorSymbolicTraits']['cases']['NSFontDescriptorClassMask'])->toBe(0xF0000000)
        ->and($defs['NSEventModifierFlags']['cases']['NSEventModifierFlagDeviceIndependentFlagsMask'])->toBe(0xffff0000)
        ->and($defs['NSDragOperation']['cases']['NSDragOperationEvery'])->toBe(-1)
        ->and($defs['NSPointingDeviceType']['cases']['NSPointingDeviceTypePen'])->toBe(1)
        ->and($defs['NSEventSubtype']['cases']['NSEventSubtypeMouseEvent'])->toBe(0);
});

it('mines NS_CLOSED_ENUM and CoreGraphics-backed NSRectEdge aliases', function () {
    $defs = EnumMiner::definitions(appkitRequireSdkFrameworks());

    expect($defs['NSComparisonResult']['kind'])->toBe(EnumKind::ENUM)
        ->and($defs['NSComparisonResult']['cases']['NSOrderedAscending'])->toBe(-1)
        ->and($defs['NSComparisonResult']['cases']['NSOrderedSame'])->toBe(0)
        ->and($defs['NSComparisonResult']['cases']['NSOrderedDescending'])->toBe(1)
        ->and($defs['NSRectEdge']['cases']['NSRectEdgeMinX'])->toBe(0)
        ->and($defs['NSRectEdge']['cases']['NSRectEdgeMaxY'])->toBe(3);
});

it('keeps the macOS branch of NSTextAlignment after preprocessor blanking', function () {
    $defs = EnumMiner::definitions(appkitRequireSdkFrameworks());

    expect($defs['NSTextAlignment']['kind'])->toBe(EnumKind::ENUM)
        ->and($defs['NSTextAlignment']['cases']['NSTextAlignmentLeft'])->toBe(0)
        ->and($defs['NSTextAlignment']['cases']['NSTextAlignmentRight'])->toBe(1)
        ->and($defs['NSTextAlignment']['cases']['NSTextAlignmentCenter'])->toBe(2);
});
