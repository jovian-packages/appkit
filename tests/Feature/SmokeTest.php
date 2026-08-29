<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Enums\NSApplicationActivationPolicy;
use Jovian\Bindings\AppKit\Enums\NSBackingStoreType;
use Jovian\Bindings\AppKit\Enums\NSBezelStyle;
use Jovian\Bindings\AppKit\Enums\NSWindowStyleMask;
use Jovian\Bindings\AppKit\NS\NSApplication;
use Jovian\Bindings\AppKit\NS\NSButton;
use Jovian\Bindings\AppKit\NS\NSView;
use Jovian\Bindings\AppKit\NS\NSWindow;
use Jovian\Bindings\AppKit\Runtime\Bridge;
use Jovian\Bindings\AppKit\Runtime\Lifetime;
use Jovian\Bindings\AppKit\Runtime\ObjCObject;
use Jovian\Bindings\AppKit\Runtime\Registry;
use Jovian\Bindings\AppKit\Values\NSRect;

beforeEach(function () {
    if (! appkitExtensionLoaded()) {
        test()->markTestSkipped('ext-appkit is not loaded');
    }

    Lifetime::reset();
    Registry::reset();
});

afterEach(function () {
    Registry::reset();
    Lifetime::reset();
});

afterAll(function () {
    echo "SMOKE_OK\n";
});

function appkitSpineStyleMask(): int
{
    return NSWindowStyleMask::TITLED->value
        | NSWindowStyleMask::CLOSABLE->value
        | NSWindowStyleMask::MINIATURIZABLE->value
        | NSWindowStyleMask::RESIZABLE->value;
}

it('delivers a real button click to onAction and pumps', function () {
    $app = NSApplication::sharedApplication();
    expect($app)->toBeInstanceOf(NSApplication::class);
    $app->setActivationPolicy(NSApplicationActivationPolicy::REGULAR);
    $app->finishLaunching();

    $win = NSWindow::initWithContentRectStyleMaskBackingDefer(
        new NSRect(240.0, 240.0, 420.0, 260.0),
        appkitSpineStyleMask(),
        NSBackingStoreType::NS_BACKING_STORE_BUFFERED,
        false,
    );
    expect($win)->toBeInstanceOf(NSWindow::class)
        ->and($win->isValid())->toBeTrue();
    $win->setReleasedWhenClosed(false);
    $win->setTitle('jovian/appkit spine');
    $win->makeKeyAndOrderFront(0);

    $content = $win->contentView();
    expect($content)->toBeInstanceOf(NSView::class);

    $btn = NSButton::buttonWithTitleTargetAction('Smoke', 0, '');
    expect($btn)->toBeInstanceOf(NSButton::class);
    $btn->setFrame(new NSRect(20.0, 20.0, 140.0, 40.0));
    $btn->setEnabled(true);
    $btn->setBezelStyle(NSBezelStyle::PUSH);
    $content->addSubview($btn->handle);
    expect($btn->frame()->width)->toBe(140.0);

    $clicked = false;
    $btn->onAction(function (?ObjCObject $sender) use (&$clicked, $btn): void {
        $clicked = ($sender === $btn);
    });
    $btn->performClick(0);
    $sent = Bridge::pump(0.1);

    expect($clicked)->toBeTrue()
        ->and($sent)->toBeInt();

    $win->close();
});

it('the ported example prints SMOKE_OK', function () {
    $script = dirname(__DIR__, 2) . '/examples/smoke.php';
    expect(is_file($script))->toBeTrue();

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    exec($cmd . ' 2>&1', $output, $status);
    $combined = implode("\n", $output);

    expect($status)->toBe(0)
        ->and($combined)->toContain('CLICK_OK')
        ->and($combined)->toContain('PUMP_OK')
        ->and($combined)->toContain('SMOKE_OK');
});
