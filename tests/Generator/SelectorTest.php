<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\Selector;

it('derives camelCase from a multi-keyword selector', function () {
    expect(Selector::toCamelCase('initWithContentRect:styleMask:backing:defer:'))
        ->toBe('initWithContentRectStyleMaskBackingDefer');
});

it('keeps a unary selector as the method name', function () {
    expect(Selector::toCamelCase('title'))->toBe('title')
        ->and(Selector::toCamelCase('setTitle:'))->toBe('setTitle')
        ->and(Selector::toCamelCase('URL'))->toBe('URL')
        ->and(Selector::toCamelCase('setURL:'))->toBe('setURL');
});

it('capitalizes each keyword after the first', function () {
    expect(Selector::toCamelCase('frameRectForContentRect:styleMask:'))
        ->toBe('frameRectForContentRectStyleMask')
        ->and(Selector::toCamelCase('insertTitlebarAccessoryViewController:atIndex:'))
        ->toBe('insertTitlebarAccessoryViewControllerAtIndex')
        ->and(Selector::toCamelCase('getPeriodicDelay:interval:'))
        ->toBe('getPeriodicDelayInterval');
});

it('looks a camelCase name back up in a selector list', function () {
    $selectors = [
        'initWithContentRect:styleMask:backing:defer:',
        'initWithContentRect:styleMask:backing:defer:screen:',
        'setTitle:',
    ];

    expect(Selector::fromCamelCase('initWithContentRectStyleMaskBackingDefer', $selectors))
        ->toBe('initWithContentRect:styleMask:backing:defer:')
        ->and(Selector::fromCamelCase('setTitle', $selectors))->toBe('setTitle:');
});

it('uses the exceptions table when a name will not reverse', function () {
    $exceptions = [
        'NS\\NSButton::oddName' => 'setButtonType:',
    ];

    expect(Selector::fromCamelCase('oddName', ['setButtonType:'], $exceptions, 'NS\\NSButton'))
        ->toBe('setButtonType:');
});

it('returns null for an unmatched name so the generator can hard-fail', function () {
    expect(Selector::fromCamelCase('notARealSelector', ['setTitle:']))->toBeNull();
});

it('strips a Zephir reserved trailing underscore before reversing', function () {
    expect(Selector::fromCamelCase('print_', ['print:']))->toBe('print:')
        ->and(Selector::fromCamelCase('string_', ['string']))->toBe('string')
        ->and(Selector::fromCamelCase('copy_', ['copy:']))->toBe('copy:');
});
