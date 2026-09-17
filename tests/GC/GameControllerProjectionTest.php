<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\GC\GCController;
use Jovian\Bindings\AppKit\GC\GCControllerAxisInput;
use Jovian\Bindings\AppKit\GC\GCControllerButtonInput;
use Jovian\Bindings\AppKit\GC\GCControllerDirectionPad;
use Jovian\Bindings\AppKit\GC\GCExtendedGamepad;
use Jovian\Bindings\AppKit\GC\GCMicroGamepad;
use Jovian\Bindings\AppKit\Runtime\ObjCObject;

it('projects every bound GameController selector', function (string $class, array $methods) {
    foreach ($methods as $method) {
        expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}");
    }
    expect(is_subclass_of($class, ObjCObject::class))->toBeTrue();
})->with([
    [GCController::class, ['controllers', 'current', 'shouldMonitorBackgroundEvents', 'setShouldMonitorBackgroundEvents', 'extendedGamepad', 'microGamepad', 'vendorName', 'productCategory', 'playerIndex', 'setPlayerIndex', 'isAttachedToDevice', 'isSnapshot']],
    [GCExtendedGamepad::class, ['controller', 'dpad', 'buttonA', 'buttonB', 'buttonX', 'buttonY', 'leftShoulder', 'rightShoulder', 'leftTrigger', 'rightTrigger', 'leftThumbstick', 'rightThumbstick', 'leftThumbstickButton', 'rightThumbstickButton', 'buttonMenu', 'buttonOptions', 'buttonHome']],
    [GCMicroGamepad::class, ['dpad', 'buttonA', 'buttonX', 'buttonMenu']],
    [GCControllerButtonInput::class, ['isPressed', 'value', 'isTouched']],
    [GCControllerAxisInput::class, ['value']],
    [GCControllerDirectionPad::class, ['xAxis', 'yAxis', 'up', 'down', 'left', 'right']],
]);

it('keeps static class properties static and element getters on the instance', function () {
    expect((new ReflectionMethod(GCController::class, 'controllers'))->isStatic())->toBeTrue()
        ->and((new ReflectionMethod(GCController::class, 'current'))->isStatic())->toBeTrue()
        ->and((new ReflectionMethod(GCController::class, 'extendedGamepad'))->isStatic())->toBeFalse()
        ->and((new ReflectionMethod(GCExtendedGamepad::class, 'buttonA'))->isStatic())->toBeFalse();
});

it('boxes element getters as objects and leaves scalars alone', function () {
    $pad = new ReflectionMethod(GCExtendedGamepad::class, 'buttonA');
    $pressed = new ReflectionMethod(GCControllerButtonInput::class, 'isPressed');
    $axis = new ReflectionMethod(GCControllerAxisInput::class, 'value');
    $current = new ReflectionMethod(GCController::class, 'current');
    $controllers = new ReflectionMethod(GCController::class, 'controllers');

    expect((string) $pad->getReturnType())->toBe('?' . ObjCObject::class)
        ->and((string) $current->getReturnType())->toBe('?' . ObjCObject::class)
        ->and((string) $pressed->getReturnType())->toBe('bool')
        ->and((string) $axis->getReturnType())->toBe('float')
        ->and((string) $controllers->getReturnType())->toBe('array');
});
