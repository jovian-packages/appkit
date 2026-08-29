---
type: Component
title: src/Runtime — identity, lifetime, and the Bridge projection
description: >-
  The hand-written core every generated DTO sits on: ObjCObject, a weak identity
  map, refcount ownership with recycling and shutdown guards, and the boxing
  boundary for callbacks.
resource: src/Runtime
tags: [runtime, identity, lifetime, bridge, appkit]
status: draft
generated:
  by: claude-opus-5/cursor
  at: 2026-08-29T04:20:00Z
---

# src/Runtime

Six classes, five of them hand-written. Everything under `src/NS/`,
`src/QuartzCore/` and `src/Enums/` is generated on top of them.

| Class | Responsibility |
|---|---|
| `ObjCObject` | Base of every DTO. Holds one `public readonly int $handle` and no other state. |
| `Registry` | The identity map: `int $handle` → `WeakReference<ObjCObject>`. |
| `Lifetime` | Shutdown flag, plus `reset()` for tests. |
| `Bridge` | Projection of `AppKit\Bridge\Bridge`, and the boxing boundary for callbacks. |
| `Delegate` | Wraps `delegateNew`/`delegateOn`/`delegateOff`. |
| `ClassMap` | **Generated.** `ObjC class name` → PHP class, for choosing a class when boxing. |

`ClassMap.php` is the one generated file living outside the generated
directories, because it is a runtime concern the generator happens to know the
answer to. Edit `scripts/generate.php`, not the file.

## The DTO stores nothing

`className()`, `isValid()` and `isKindOfClass()` each call `Bridge`, never a
cached copy. That is what makes it impossible for the PHP side and the
Objective-C side to disagree: the PHP object is a *name for a pointer*, not a
mirror of the object.

## Identity

Boxing a handle that is already boxed returns the identical instance, so
`$win->contentView()->window() === $win`, and a sender arriving in a callback is
the same PHP object you already hold.

For a first-seen handle the class comes from `Bridge::className()` looked up in
`ClassMap`, falling back to the nearest bound ancestor and finally `ObjCObject`.
The runtime class is the *only* accurate source here: the `@zep` annotations say
`-> int` for every object, so a call declared as returning a view can hand back
an `NSButton`, and only asking the object gets that right.

The map holds weak references. A strong map would pin every object forever and
defeat refcount ownership.

## Lifetime, and its two hazards

Construction calls `Bridge::retain()`. Destruction calls `Bridge::release()`.
Both hazards below are covered by dedicated tests in `tests/Runtime/`, not left
to review.

**Handle recycling.** The extension's registry can reuse a handle after a
release. So `__destruct` evicts through `Registry::evictIfSelf($this)` and only
releases if that entry still pointed at the dying object — otherwise a stale
destructor would evict the newer object that took the slot.

**Shutdown ordering.** `Lifetime::isShuttingDown()` short-circuits `__destruct`
entirely. Releasing an `NSWindow` after `NSApp` has been torn down is a crash;
leaking during process exit is correct, because the process is going away.

## The boxing boundary

`Bridge` wraps every callback so handles arrive boxed:

- `setAction` → the callable receives `?ObjCObject $sender`.
- `observeNotification` → `(?ObjCObject $object, string $name)`.
- `delegateOn` → each `int` argument is boxed, non-ints pass through unchanged.

`ObjCObject::onAction()`, `onNotification()` and `removeObserver()` are the
instance-side projections of those.

Object *parameters* on generated methods are plain `int`, so you pass
`$obj->handle`. Only returns and callback arguments are boxed. See
[return-typing.md](/return-typing.md) for exactly when a return boxes.
