<?php

declare(strict_types=1);

/*
| Every generated class must load: a generated method that collides with a
| hand-written ObjCObject method is a fatal only when the class is first
| autoloaded, which no other test may do.
*/

it('loads every generated class without a signature collision', function (string $class): void {
    expect(class_exists($class) || enum_exists($class))->toBeTrue();
})->with(function (): array {
    $root = dirname(__DIR__, 2).'/src';
    $classes = [];

    foreach (['NS', 'QuartzCore', 'AV', 'GC', 'Enums'] as $dir) {
        foreach (glob("{$root}/{$dir}/*.php") ?: [] as $file) {
            $classes[] = ['Jovian\\Bindings\\AppKit\\'.$dir.'\\'.basename($file, '.php')];
        }
    }

    return $classes;
});
