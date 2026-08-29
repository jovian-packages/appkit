# jovian/appkit

The AppKit object graph, typed, in PHP. One class per bound Objective-C class,
one method per selector, no opinions: this package is `ext-appkit` projected
into PHP objects plus the enum values the extension deliberately leaves out.

Hold a PHP `NSWindow` and there is exactly one live `NSWindow` on the
Objective-C side. Every question the PHP object answers, it answers by asking
AppKit.

Requires macOS, PHP 8.4+, and [`ext-appkit`](https://github.com/php-io-extensions/appkit) 0.8.

```bash
composer require jovian/appkit
```

## Projection, not composition

A method here is exactly one extension call with the same arguments in the same
order. `$win->setTitle('hello')` is `NSWindow::setTitle($handle, 'hello')` in a
different shape, so it invents nothing.

There is no `create()`. Choosing a style mask, a backing store, or a
release-when-closed policy on your behalf would make this a framework;
composition lives in `jovian/venusian-appkit`, and abstraction lives in Surface.

The test for any method: **can it be written as one extension call?**

## What the projection adds

| `ext-appkit` gives you | this package gives you |
|---|---|
| `int` handles, 0 = nil | `NSWindow`, `NSButton`, … mirroring AppKit's inheritance chain |
| the same handle twice | the same PHP instance twice, `===` |
| manual `retain`/`release` | PHP refcount ownership; boxing retains, GC releases |
| structs as loose doubles in, assoc arrays out | `NSRect`, `NSPoint`, `NSSize`, `NSRange`, `NSEdgeInsets` |
| raw ints for enums | 138 int-backed enums mined from the macOS SDK |
| `-> int` for everything | `?ObjCObject` only where the SDK returns an object pointer, `id`, or `instancetype` |

The DTOs mirror AppKit's real inheritance chain, so `NSButton` extends
`NSControl` extends `NSView` extends `NSResponder`. Inherited selectors bind on
the declaring class and reach subclasses through PHP inheritance, exactly as the
extension intends when it says handles are untyped.

## Example

```php
use Jovian\Bindings\AppKit\Enums\NSApplicationActivationPolicy;
use Jovian\Bindings\AppKit\Enums\NSBackingStoreType;
use Jovian\Bindings\AppKit\Enums\NSWindowStyleMask;
use Jovian\Bindings\AppKit\NS\NSApplication;
use Jovian\Bindings\AppKit\NS\NSButton;
use Jovian\Bindings\AppKit\NS\NSWindow;
use Jovian\Bindings\AppKit\Runtime\Bridge;
use Jovian\Bindings\AppKit\Runtime\ObjCObject;
use Jovian\Bindings\AppKit\Values\NSRect;

$app = NSApplication::sharedApplication();
$app->setActivationPolicy(NSApplicationActivationPolicy::REGULAR);
$app->finishLaunching();

$win = NSWindow::initWithContentRectStyleMaskBackingDefer(
    new NSRect(240.0, 240.0, 420.0, 260.0),
    NSWindowStyleMask::TITLED->value
        | NSWindowStyleMask::CLOSABLE->value
        | NSWindowStyleMask::RESIZABLE->value,
    NSBackingStoreType::NS_BACKING_STORE_BUFFERED,
    false,
);
$win->setReleasedWhenClosed(false);
$win->setTitle('hello from PHP');
$win->makeKeyAndOrderFront(0);

$btn = NSButton::buttonWithTitleTargetAction('Click me', 0, '');
$btn->setFrame(new NSRect(20.0, 20.0, 140.0, 40.0));
$win->contentView()->addSubview($btn->handle);

$btn->onAction(function (?ObjCObject $sender) use ($btn): void {
    echo $sender === $btn ? "clicked\n" : "clicked by someone else\n";
});

while ($win->isVisible()) {
    Bridge::pump(0.05);
}
```

Two things that example is quietly demonstrating. `$win->contentView()` returns
a boxed `NSView`, and the sender delivered to the action closure is the
*identical* `$btn` instance — `===`, not an equal copy — because both resolve
through the same identity map.

Note `addSubview($btn->handle)`. Object **parameters** are `int` handles, not
DTOs; only returns and callback arguments are boxed. `$obj->handle` is public
and readonly, so dropping to a raw handle is always available and never
surprising.

Enum parameters take the enum or an int, so `NSBackingStoreType::NS_BACKING_STORE_BUFFERED`
passes directly. `NS_OPTIONS` parameters like the style mask take an `int`, so
you OR the `->value`s yourself.

## Scalars are scalars

`NSWindow::level()`, `NSView::tag()` and `NSSwitch::state()` return `int`, not a
boxed object, because the SDK declares them `NSWindowLevel`, `NSInteger` and
`NSControlStateValue`. This is enforced by a gate rather than by care: the
annotations say `-> int` for handles and integers alike, and boxing an integer
would look it up in the live handle registry and hand back an unrelated object.

Pointer-ness is necessary but not sufficient. `NSString *` and the collection
types are pointers the extension already marshals, so they arrive as `?string`
and `array` — 175 pointer returns are not boxed.

`NS_ENUM` returns come back as `Enum|int`; `NS_OPTIONS` returns stay `int`,
because PHP enums cannot be OR'd.

## Lifetime

Boxing retains and garbage collection releases. Two details matter:

- The identity map holds weak references, so it never pins an object alive.
- During shutdown, destructors stop releasing. Releasing an `NSWindow` after
  `NSApp` has been torn down is a crash; leaking at process exit is correct.

## Working on this package

`src/NS/**`, `src/QuartzCore/**` and `src/Enums/**` are generated from the
`@zep` and `@zep-construct` annotations in `ext-appkit`'s `src/*.h`, joined
against the macOS SDK headers. Never hand-edit them.

```bash
php scripts/generate.php --check --ext=../../php-io-extensions/appkit   # GEN_OK
node scripts/gates/verify-parity.mjs                                    # PARITY_OK
node scripts/gates/verify-return-typing.mjs                             # RETURN_TYPING_OK
node scripts/gates/verify-enum-typing.mjs                               # ENUM_TYPING_OK
node scripts/gates/verify-reflection.mjs                                # REFLECTION_OK
vendor/bin/pest
```

See [`AGENTS.md`](AGENTS.md) for the rules and [`.okf/`](.okf/index.md) for the
knowledge bundle.

## License

MIT.
