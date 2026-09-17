<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Runtime\Bridge;

it('forwards the input tap', function () {
    $watch = new ReflectionMethod(Bridge::class, 'watchInput');

    expect((string) $watch->getReturnType())->toBe('void')
        ->and((string) $watch->getParameters()[0]->getType())->toBe('int')
        ->and((string) (new ReflectionMethod(Bridge::class, 'drainInput'))->getReturnType())->toBe('array');
});

it('drains an array from the live extension', function () {
    Bridge::watchInput(1 << 10);
    expect(Bridge::drainInput())->toBeArray();
    Bridge::watchInput(0);
})->skip(! extension_loaded('appkit'), 'needs ext-appkit');

it('forwards swallowKeysIn with a list of window numbers', function () {
    $swallow = new ReflectionMethod(Bridge::class, 'swallowKeysIn');

    expect((string) $swallow->getReturnType())->toBe('void')
        ->and((string) $swallow->getParameters()[0]->getType())->toBe('array');

    Bridge::watchInput(1 << 10);
    Bridge::swallowKeysIn([1, 2]);
    Bridge::swallowKeysIn([]);
    Bridge::watchInput(0);
})->skip(! extension_loaded('appkit'), 'needs ext-appkit');
