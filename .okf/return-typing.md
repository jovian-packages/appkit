---
type: Decision
title: A return boxes only when the SDK says pointer, id, or instancetype
description: >-
  Handle-vs-integer cannot be decided from the @zep annotations, which say
  "-> int" for both. It must be decided on the raw SDK return type, before
  canonicalization strips the pointer star.
resource: scripts/Generator/TypeJoin.php
tags: [generation, types, handles, defect, appkit]
status: draft
generated:
  by: claude-opus-5/cursor
  at: 2026-08-29T04:20:00Z
---

# Return typing

Of 3,601 joined methods, **687 return a handle** — 141 `instancetype`, the rest
class pointers and `id`. **145 return an enum** across 91 distinct enum types.
**192 return a struct.** Everything else is a scalar.

A method may be emitted as `?ObjCObject` only when its raw SDK return type is a
pointer, `id`, or `instancetype`. Enforced by
`scripts/gates/verify-return-typing.mjs` (`RETURN_TYPING_OK`).

## Why this needs a rule at all

The `@zep` annotations type every object return as plain `int`. So does every
`NSInteger`, every `NSWindowLevel`, every enum. The annotation alone cannot tell
a handle from a number, which is why the join against the SDK headers exists.

## The defect this concept exists to prevent

`TypeName::isObject()` originally ended with
`return preg_match('/^(NS|CA)/', $canonical) === 1;`. Two independent
consequences followed:

1. Every NS-prefixed typedef read as an object — `NSInteger`, `NSUInteger`,
   `NSTimeInterval`, `NSControlStateValue`, `NSWindowLevel`, `NSModalResponse`.
2. `TypeJoin::phpReturn()` tested `returnsHandle` *before* the enum branch, so
   even a correctly identified enum return would still box.

1,010 methods returned `?ObjCObject` and none returned an enum, which is not
credible for AppKit. `NSView::tag()`, `NSWindow::level()`,
`NSWindow::windowNumber()` and `NSSwitch::state()` all boxed.

This was never cosmetic. `ObjCObject::box(5)` looks handle 5 up in the live
registry, so a tag or a window number resolved to an unrelated live object, or
retained a bogus handle.

**The fix, and the lesson.** `returnsHandle` is now computed from the *raw*
declaration via `TypeName::isHandleType($raw)`, which requires exactly one `*`
outside generics over an `NS`/`CA` class, or `id`, or `instancetype`.
Canonicalizing first was the whole bug: `canonicalize()` replaces `*` with a
space, so the pointer information the decision depends on was destroyed before
the decision was made. `phpReturn()` now orders struct, enum, handle, scalar.

Parameters escaped both problems only because `scalarParam()` consults the enum
kinds first. `JoinedParam::$handle` was still wrong for `NSInteger` params; it
has no emitter effect, but it was a lie and is now `false` for them.

## Worked examples

| Method | Raw SDK return | PHP return |
|---|---|---|
| `NSWindow::contentView()` | `__kindof NSView *` | `?ObjCObject` |
| `NSView::tag()` | `NSInteger` | `int` |
| `NSWindow::level()` | `NSWindowLevel` | `int` |
| `NSWindow::windowNumber()` | `NSInteger` | `int` |
| `NSWindow::styleMask()` | `NSWindowStyleMask` (`NS_OPTIONS`) | `int` |
| `NSWindow::titleVisibility()` | `NSWindowTitleVisibility` (`NS_ENUM`) | `NSWindowTitleVisibility\|int` |
| `NSSwitch::state()` | `NSControlStateValue` | `int` |

`NSControlStateValue` is the one to watch: it is a
`typedef NSInteger … NS_TYPED_EXTENSIBLE_ENUM` whose named values are
`static const` rather than enumerators, so the miner skips it and the return
stays `int`. See [enums-and-values.md](/enums-and-values.md).

Note also that pointer-ness is necessary but not sufficient. `NSString *`,
`NSMutableString *`, and the collection types (`NSArray`, `NSDictionary`,
`NSSet` and their mutable forms) are pointers that the extension already
marshals to PHP strings and arrays, so `isHandleType()` excludes them by name.
175 pointer returns are not boxed: 96 `array`, 35 `?string`, 30 `mixed`, and 14
`int`. `NSString *` alone accounts for 55 of them.

## Why the gates missed it

Parity counts methods, not signatures. The enum gates covered parameters and
case values, not returns. The smoke test exercised void, bool and handle paths
on the spine, never a scalar getter. Nothing was lying; nothing was looking.

The gate added afterwards carries a positive control — a deliberately mistyped
`NSView.tag` that must fail the gate before the real join passes it.
