---
type: Component
title: src/Enums and src/Values — the two things the extension withholds
description: >-
  138 int-backed enums mined from the macOS SDK, and five readonly struct value
  objects. NS_ENUM appears in signatures; NS_OPTIONS stays int because PHP enums
  cannot be OR'd.
resource: src/Enums
tags: [enums, structs, values, sdk, appkit]
status: draft
generated:
  by: claude-opus-5/cursor
  at: 2026-08-29T04:20:00Z
---

# Enums and value objects

`ext-appkit` binds no constants and marshals structs as loose doubles in and
associative arrays out. Both gaps are filled here, and only here.

## Enums

**138 enums, 767 cases**, mined from the SDK headers rather than transcribed.
Every enum is `int`-backed with FULLY UPPERCASE cases. Only types reachable from
a bound signature are generated, so the set tracks the binding rather than the
SDK.

`tests/Enums/` re-parses the SDK at test time and asserts every case value
against the header, which is the only way to catch Apple changing one.

### NS_ENUM appears in signatures; NS_OPTIONS does not

183 parameters across 98 distinct enum types are typed `SomeEnum|int`. A style
mask
is not, and never can be: `NSWindowStyleMask::TITLED | NSWindowStyleMask::CLOSABLE`
is a `TypeError` in PHP. So an `NS_OPTIONS` type is still *generated* — you write
`NSWindowStyleMask::TITLED->value | NSWindowStyleMask::CLOSABLE->value` — but the
signature on both sides stays `int`.

`scripts/gates/verify-enum-typing.mjs` re-derives the `NS_ENUM`/`NS_OPTIONS`
kind straight from the SDK rather than trusting a sidecar the generator wrote,
so the gate cannot agree with the generator's own mistake.

### NS_TYPED_EXTENSIBLE_ENUM is a real gap, not a trap

`NSControlStateValue`, `NSWindowLevel` and `NSModalResponse` are
`typedef NSInteger … NS_TYPED_EXTENSIBLE_ENUM`, and they **do** carry named
values — as `static const`, not as enumerators:

```objc
typedef NSInteger NSControlStateValue NS_TYPED_EXTENSIBLE_ENUM;   // NSCell.h:71
static const NSControlStateValue NSControlStateValueMixed = -1;
static const NSControlStateValue NSControlStateValueOff = 0;
static const NSControlStateValue NSControlStateValueOn = 1;
```

`NSWindow.h:192` declares nine window levels the same way, and
`NSApplication.h:120` three modal responses.

The miner only reads `NS_ENUM` and `NS_OPTIONS`, so **none of these become PHP
enums**, and `NSSwitch::state()` and `NSWindow::level()` correctly return `int`.
That much is sound. But the consequence is a genuine gap: a caller has no
symbolic way to say "on" or "floating window level" and must hard-code `1` or
`3`. Extending the miner to `NS_TYPED_EXTENSIBLE_ENUM` would close it, at the
cost of the extensibility the annotation is announcing — the value set is
explicitly open, so a PHP enum could reject a legitimate future value. Passing
them as `int` and offering the names as an enum for convenience is the shape to
consider, and this is an open question rather than a settled decision.

### Case naming is not yet uniform

Most enums drop the type prefix — `NSWindowStyleMask::TITLED`,
`NSBezelStyle::PUSH`, `NSApplicationActivationPolicy::REGULAR` — but some keep
it, notably `NSBackingStoreType::NS_BACKING_STORE_BUFFERED`. The values are
verified against the SDK and correct either way; only the names are
inconsistent. Normalizing them is a breaking change to a published API, so it
should happen before 0.8.0 ships or not at all.

## Value objects

Five readonly classes in `src/Values/`: `NSRect`, `NSPoint`, `NSSize`,
`NSRange`, `NSEdgeInsets`. Each has a promoted constructor, `fromArray()` for
the assoc array the extension returns, and `toArgs()` for the flat doubles it
expects.

```php
$btn->setFrame(new NSRect(20.0, 20.0, 140.0, 40.0));
$btn->frame()->width;   // 140.0
```

192 methods return a struct. The generator spreads a value object into the
extension's positional doubles at the call site, so `setFrame` stays exactly one
extension call — see [projection-rule.md](/projection-rule.md).
