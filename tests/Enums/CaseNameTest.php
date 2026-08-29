<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\Emitter;

it('strips a leading type name and snake-cases the remainder to FULLY UPPERCASE', function () {
    expect(Emitter::enumCaseName('NSWindowStyleMask', 'NSWindowStyleMaskTitled'))->toBe('TITLED')
        ->and(Emitter::enumCaseName('NSWindowStyleMask', 'NSWindowStyleMaskFullSizeContentView'))->toBe('FULL_SIZE_CONTENT_VIEW')
        ->and(Emitter::enumCaseName('NSAlertStyle', 'NSAlertStyleWarning'))->toBe('WARNING');
});

it('does not invent a shorter prefix when the identifier is not the type name', function () {
    expect(Emitter::enumCaseName('NSWindowTitleVisibility', 'NSWindowTitleHidden'))->toBe('NS_WINDOW_TITLE_HIDDEN')
        ->and(Emitter::enumCaseName('NSWindowTitleVisibility', 'NSWindowTitleVisible'))->toBe('NS_WINDOW_TITLE_VISIBLE')
        ->and(Emitter::enumCaseName('NSEventModifierFlags', 'NSEventModifierFlagCommand'))->toBe('NS_EVENT_MODIFIER_FLAG_COMMAND')
        ->and(Emitter::enumCaseName('NSAlignmentOptions', 'NSAlignMinXInward'))->toBe('NS_ALIGN_MIN_X_INWARD');
});

it('prefixes a remainder that would start with a digit so the case is a legal PHP name', function () {
    expect(Emitter::enumCaseName('NSTypesetterBehavior', 'NSTypesetterBehavior_10_2_WithCompatibility'))
        ->toBe('CASE_10_2_WITH_COMPATIBILITY');
});

it('splits acronyms the way the independent enum oracle does', function () {
    expect(Emitter::enumCaseName('NSDisplayGamut', 'NSDisplayGamutSRGB'))->toBe('SRGB')
        ->and(Emitter::enumCaseName('NSDisplayGamut', 'NSDisplayGamutP3'))->toBe('P3');
});
