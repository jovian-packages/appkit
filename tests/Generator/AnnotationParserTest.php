<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\AnnotationParser;

it('parses @zep and @zep-construct annotations from a header fixture', function () {
    $dir = sys_get_temp_dir() . '/jovian-appkit-zep-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $header = $dir . '/ns-window.h';
    file_put_contents($header, <<<'C'
/*@zep NS\NSWindow setTitle(int handle, string title) -> void */
void ns_nswindow_set_title(zval *handle, zval *title);

/*@zep-construct NS\NSSwitch initWithFrame(double x, double y, double width, double height) -> int */
zend_long ns_nsswitch_init_with_frame(zval *x, zval *y, zval *width, zval *height);

/*@zep Bridge\Bridge retain(int handle) -> bool */
zend_long ns_bridge_retain(zval *handle);

/*@reserved NS\NSWindow - (instancetype)initWithCoder:(NSCoder *)coder */
C);

    $annotations = AnnotationParser::parseDirectory($dir);

    expect($annotations)->toHaveCount(3)
        ->and($annotations[0]->classPath)->toBe('NS\\NSWindow')
        ->and($annotations[0]->method)->toBe('setTitle')
        ->and($annotations[0]->returnType)->toBe('void')
        ->and($annotations[0]->construct)->toBeFalse()
        ->and($annotations[0]->isBridge())->toBeFalse()
        ->and($annotations[0]->params)->toBe([
            ['type' => 'int', 'name' => 'handle'],
            ['type' => 'string', 'name' => 'title'],
        ])
        ->and($annotations[1]->construct)->toBeTrue()
        ->and($annotations[1]->method)->toBe('initWithFrame')
        ->and($annotations[2]->isBridge())->toBeTrue();

    array_map('unlink', glob($dir . '/*') ?: []);
    rmdir($dir);
});

it('hard-fails a malformed @zep annotation', function () {
    $dir = sys_get_temp_dir() . '/jovian-appkit-zep-bad-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir . '/bad.h', "/*@zep NS\\NSWindow setTitle broken */\n");

    expect(fn () => AnnotationParser::parseDirectory($dir))
        ->toThrow(RuntimeException::class);

    array_map('unlink', glob($dir . '/*') ?: []);
    rmdir($dir);
});
