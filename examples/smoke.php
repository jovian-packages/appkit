<?php
/*
 * Spine proof — DTO API.
 * Port of ext-appkit examples/smoke.php onto typed handle DTOs.
 * Application + window + view + control lifecycle, a PHP closure firing
 * from a real target/action dispatch, a notification observation, a
 * protocol delegate answering windowShouldClose:, a menu bar, Bridge::pump,
 * and the failure paths that stay on the spine. Wave B (table, alert,
 * toolbar, status item, synthesized non-spine constructors) waits for WP4.
 *
 * Run: php examples/smoke.php
 *
 * A method here is exactly one extension call. There is no create().
 */

declare(strict_types=1);

use Jovian\Bindings\AppKit\Enums\NSApplicationActivationPolicy;
use Jovian\Bindings\AppKit\Enums\NSBackingStoreType;
use Jovian\Bindings\AppKit\Enums\NSBezelStyle;
use Jovian\Bindings\AppKit\Enums\NSWindowStyleMask;
use Jovian\Bindings\AppKit\NS\NSApplication;
use Jovian\Bindings\AppKit\NS\NSButton;
use Jovian\Bindings\AppKit\NS\NSMenu;
use Jovian\Bindings\AppKit\NS\NSMenuItem;
use Jovian\Bindings\AppKit\NS\NSView;
use Jovian\Bindings\AppKit\NS\NSWindow;
use Jovian\Bindings\AppKit\Runtime\Bridge;
use Jovian\Bindings\AppKit\Runtime\Delegate;
use Jovian\Bindings\AppKit\Runtime\ObjCObject;
use Jovian\Bindings\AppKit\Values\NSRect;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "smoke: run composer install first\n");
    exit(1);
}
require $autoload;

if (! extension_loaded('appkit')) {
    fwrite(STDERR, "smoke: the appkit extension is not loaded\n");
    exit(1);
}

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "{$label}\n";

        return;
    }
    $failures++;
    fwrite(STDERR, "FAILED: {$label}\n");
}

$style = NSWindowStyleMask::TITLED->value
    | NSWindowStyleMask::CLOSABLE->value
    | NSWindowStyleMask::MINIATURIZABLE->value
    | NSWindowStyleMask::RESIZABLE->value;

/* ---- application ---- */
$app = NSApplication::sharedApplication();
check($app instanceof NSApplication, 'APP_OK');
if ($app instanceof NSApplication) {
    $app->setActivationPolicy(NSApplicationActivationPolicy::REGULAR);
    $app->finishLaunching();
}

/* ---- window ---- */
$win = NSWindow::initWithContentRectStyleMaskBackingDefer(
    new NSRect(240.0, 240.0, 420.0, 260.0),
    $style,
    NSBackingStoreType::NS_BACKING_STORE_BUFFERED,
    false,
);
check($win instanceof NSWindow && $win->isValid(), 'WINDOW_OK');
if (! ($win instanceof NSWindow)) {
    fwrite(STDERR, "SMOKE_FAILED (no window)\n");
    exit(1);
}
// The bridge registry owns the handle; AppKit must not also release on close.
$win->setReleasedWhenClosed(false);
$win->setTitle('jovian/appkit spine');
$win->makeKeyAndOrderFront(0);
$content = $win->contentView();
check($content instanceof NSView, 'CONTENT_VIEW_OK');

/* ---- button + NSControl/NSView setters through the typed subclass ---- */
$btn = NSButton::buttonWithTitleTargetAction('Smoke', 0, '');
check($btn instanceof NSButton, 'BUTTON_OK');
if ($btn instanceof NSButton) {
    $btn->setFrame(new NSRect(20.0, 20.0, 140.0, 40.0));
    $btn->setEnabled(true);
    $btn->setBezelStyle(NSBezelStyle::PUSH);
    if ($content instanceof NSView) {
        $content->addSubview($btn->handle);
    }
    $frame = $btn->frame();
    check($frame->width === 140.0, 'INHERITED_SETTER_OK');

    /* ---- click: PHP closure fired from target/action, then pump ---- */
    $clicked = false;
    $btn->onAction(function (?ObjCObject $sender) use (&$clicked, $btn): void {
        $clicked = ($sender === $btn);
    });
    $btn->performClick(0);
    check($clicked, 'CLICK_OK');
}

/* ---- notification: NSWindowDidResizeNotification ---- */
$resized = false;
$token = $win->onNotification('NSWindowDidResizeNotification', function (?ObjCObject $object, string $name) use (&$resized, $win): void {
    $resized = ($object === $win && $name === 'NSWindowDidResizeNotification');
});
check($token !== 0, 'OBSERVE_TOKEN_OK');
$win->setFrameDisplay(new NSRect(240.0, 240.0, 500.0, 300.0), true);
check($resized, 'RESIZE_OK');
$win->removeObserver($token);

/* ---- delegate: windowShouldClose: returns false, window survives ---- */
$asked = false;
$delegate = new Delegate('NSWindowDelegate');
check($delegate->handle() !== 0, 'DELEGATE_NEW_OK');
$delegate->on('windowShouldClose:', function () use (&$asked): bool {
    $asked = true;

    return false;
});
$win->setDelegate($delegate->handle());
$win->performClose(0);
check($asked && $win->isVisible(), 'SHOULD_CLOSE_OK');

/* ---- menu bar ---- */
$bar = NSMenu::initWithTitle('MainMenu');
$appItem = NSMenuItem::initWithTitleActionKeyEquivalent('App', '', '');
$appMenu = NSMenu::initWithTitle('App');
check(
    $bar instanceof NSMenu && $appItem instanceof NSMenuItem && $appMenu instanceof NSMenu,
    'MENU_BUILD_OK',
);
if ($bar instanceof NSMenu && $appItem instanceof NSMenuItem && $appMenu instanceof NSMenu && $app instanceof NSApplication) {
    $appItem->setSubmenu($appMenu->handle);
    $bar->addItem($appItem->handle);
    $app->setMainMenu($bar->handle);
    check($app->mainMenu() === $bar, 'MENU_OK');
}

/* ---- pump ---- */
$sent = Bridge::pump(0.1);
check(is_int($sent), 'PUMP_OK');

/* ---- failure path: unknown handle boxes to null ---- */
check(is_null(ObjCObject::box(999999999)) && ! Bridge::isValid(999999999) && is_null(Bridge::className(999999999)), 'UNKNOWN_HANDLE_OK');

/* ---- failure path: PHP exception inside a callback propagates out ---- */
$boom = NSButton::buttonWithTitleTargetAction('Boom', 0, '');
$caught = false;
if ($boom instanceof NSButton) {
    $boom->onAction(function (): void {
        throw new RuntimeException('boom from callback');
    });
    try {
        $boom->performClick(0);
    } catch (RuntimeException $e) {
        $caught = ($e->getMessage() === 'boom from callback');
    }
}
check($caught, 'EXCEPTION_OK');

/* ---- teardown ---- */
$win->setDelegate(0);
$win->close();

if ($failures > 0) {
    fwrite(STDERR, "SMOKE_FAILED ({$failures})\n");
    exit(1);
}
echo "SMOKE_OK\n";
