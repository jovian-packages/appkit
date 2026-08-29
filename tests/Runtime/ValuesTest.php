<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Values\NSEdgeInsets;
use Jovian\Bindings\AppKit\Values\NSPoint;
use Jovian\Bindings\AppKit\Values\NSRange;
use Jovian\Bindings\AppKit\Values\NSRect;
use Jovian\Bindings\AppKit\Values\NSSize;

it('round-trips NSRect through the extension array shape', function () {
    $rect = NSRect::fromArray(['x' => 1.5, 'y' => 2.5, 'width' => 3.5, 'height' => 4.5]);

    expect($rect->x)->toBe(1.5)
        ->and($rect->y)->toBe(2.5)
        ->and($rect->width)->toBe(3.5)
        ->and($rect->height)->toBe(4.5)
        ->and($rect->toArgs())->toBe([1.5, 2.5, 3.5, 4.5]);
});

it('round-trips NSPoint through the extension array shape', function () {
    $point = NSPoint::fromArray(['x' => 10.0, 'y' => 20.0]);

    expect($point->x)->toBe(10.0)
        ->and($point->y)->toBe(20.0)
        ->and($point->toArgs())->toBe([10.0, 20.0]);
});

it('round-trips NSSize through the extension array shape', function () {
    $size = NSSize::fromArray(['width' => 30.0, 'height' => 40.0]);

    expect($size->width)->toBe(30.0)
        ->and($size->height)->toBe(40.0)
        ->and($size->toArgs())->toBe([30.0, 40.0]);
});

it('round-trips NSRange through the extension array shape', function () {
    $range = NSRange::fromArray(['location' => 3, 'length' => 7]);

    expect($range->location)->toBe(3)
        ->and($range->length)->toBe(7)
        ->and($range->toArgs())->toBe([3, 7]);
});

it('round-trips NSEdgeInsets through the extension array shape', function () {
    $insets = NSEdgeInsets::fromArray([
        'top' => 1.0,
        'left' => 2.0,
        'bottom' => 3.0,
        'right' => 4.0,
    ]);

    expect($insets->top)->toBe(1.0)
        ->and($insets->left)->toBe(2.0)
        ->and($insets->bottom)->toBe(3.0)
        ->and($insets->right)->toBe(4.0)
        ->and($insets->toArgs())->toBe([1.0, 2.0, 3.0, 4.0]);
});

it('defaults missing struct keys to zero', function () {
    expect(NSRect::fromArray([])->toArgs())->toBe([0.0, 0.0, 0.0, 0.0])
        ->and(NSPoint::fromArray([])->toArgs())->toBe([0.0, 0.0])
        ->and(NSSize::fromArray([])->toArgs())->toBe([0.0, 0.0])
        ->and(NSRange::fromArray([])->toArgs())->toBe([0, 0])
        ->and(NSEdgeInsets::fromArray([])->toArgs())->toBe([0.0, 0.0, 0.0, 0.0]);
});
