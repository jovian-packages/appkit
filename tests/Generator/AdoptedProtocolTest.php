<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\AnnotationParser;
use Jovian\Bindings\AppKit\Generator\SdkHeaderResolver;
use Jovian\Bindings\AppKit\Generator\SdkParser;

function appkitFakeGameControllerSdk(string $interfaceLine): string
{
    $root = sys_get_temp_dir() . '/jovian-appkit-gc-' . bin2hex(random_bytes(4));
    $headers = $root . '/GameController.framework/Headers';
    mkdir($headers, 0777, true);
    file_put_contents($headers . '/GCDevice.h', <<<'C'
@protocol GCDevice;
@protocol GCDevice <NSObject>
@property (nonatomic, readonly, copy, nullable) NSString *vendorName API_AVAILABLE(macos(10.9));
@end
C);
    file_put_contents($headers . '/GCController.h', $interfaceLine . "\n" . <<<'C'
@property (nonatomic, readonly, getter = isAttachedToDevice) BOOL attachedToDevice;
@end
C);

    return $root;
}

function appkitRemoveTree(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $path) {
        is_dir($path) ? appkitRemoveTree($path) : unlink($path);
    }
    rmdir($dir);
}

it('reads @audit adopts markers and rejects one without a reason', function () {
    $dir = sys_get_temp_dir() . '/jovian-appkit-adopts-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir . '/gc-controller.h', "/*@audit adopts GC\\GCController GCDevice vendorName lives on the protocol */\n");

    expect(AnnotationParser::parseAdoptions($dir))
        ->toBe(['GC\\GCController' => ['GCDevice' => 'vendorName lives on the protocol']]);

    file_put_contents($dir . '/gc-controller.h', "/*@audit adopts GC\\GCController GCDevice */\n");
    expect(fn () => AnnotationParser::parseAdoptions($dir))->toThrow(RuntimeException::class, 'malformed @audit adopts');

    appkitRemoveTree($dir);
});

it('folds an adopted protocol into the class and reads spaced getter attributes', function () {
    $sdk = appkitFakeGameControllerSdk('@interface GCController : NSObject <GCDevice>');
    $adoptions = ['GC\\GCController' => ['GCDevice' => 'reason']];

    $class = (new SdkParser(new SdkHeaderResolver($sdk), $adoptions))->parseClassPath('GC\\GCController');
    $selectors = $class->selectors();

    expect($selectors)->toContain('vendorName')
        ->and($selectors)->toContain('isAttachedToDevice')
        ->and((new SdkParser(new SdkHeaderResolver($sdk)))->parseClassPath('GC\\GCController')->selectors())
        ->not->toContain('vendorName');

    appkitRemoveTree($sdk);
});

it('fails hard when the class does not adopt the marked protocol', function () {
    $sdk = appkitFakeGameControllerSdk('@interface GCController : NSObject');
    $parser = new SdkParser(new SdkHeaderResolver($sdk), ['GC\\GCController' => ['GCDevice' => 'reason']]);

    expect(fn () => $parser->parseClassPath('GC\\GCController'))
        ->toThrow(RuntimeException::class, 'does not adopt it');

    appkitRemoveTree($sdk);
});

it('fails hard when the marked protocol is not defined in the framework', function () {
    $sdk = appkitFakeGameControllerSdk('@interface GCController : NSObject <GCDevice, GCMissing>');
    $parser = new SdkParser(new SdkHeaderResolver($sdk), ['GC\\GCController' => ['GCMissing' => 'reason']]);

    expect(fn () => $parser->parseClassPath('GC\\GCController'))
        ->toThrow(RuntimeException::class, 'protocol not found');

    appkitRemoveTree($sdk);
});
