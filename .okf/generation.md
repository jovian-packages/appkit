---
type: Process
title: scripts/generate.php — joining every annotation to an SDK declaration
description: >-
  The pipeline that turns 3,614 @zep annotations plus the macOS SDK headers into
  88 DTO classes and 138 enums, and refuses to emit anything it could not join.
resource: scripts/generate.php
tags: [generation, toolchain, sdk, parity, appkit]
status: draft
generated:
  by: claude-opus-5/cursor
  at: 2026-08-29T04:20:00Z
---

# Generation

`src/NS/**`, `src/QuartzCore/**`, `src/Enums/**` and `Runtime/ClassMap.php` are
generated. Nothing in them is hand-edited, ever. Everything else is hand-written.

```bash
php scripts/generate.php --check --ext=../../php-io-extensions/appkit   # join only
php scripts/generate.php --ext=../../php-io-extensions/appkit           # emit
php scripts/generate.php --spine --ext=...                              # 8-class subset
```

`--check` is not a pure dry run. It joins and validates without emitting `NS/`,
`QuartzCore/` or `Enums/`, but it still writes `ClassMap.php` and the three join
sidecars (`param-types.json`, `return-types.json`, `reachable-enums.json`).
That is deliberate — the gates read those sidecars, so they have to exist before
the tree does — but it means `--check` is not side-effect free and a dirty
worktree after one is expected.

`--spine` emits the eight WP3 classes: `NSResponder`, `NSView`, `NSControl`,
`NSWindow`, `NSApplication`, `NSButton`, `NSMenu`, `NSMenuItem`. `ObjCObject` is
hand-written and so is not part of it.

## The two inputs, and why both are needed

**The annotations** (`@zep` / `@zep-construct` in `ext-appkit`'s `src/*.h`) say
what is bound and with what arity. They cannot say what anything *is*: every
object, every `NSInteger`, and every enum is annotated `-> int`.

**The SDK headers** say what each selector actually returns and accepts. The
join is what recovers handles, enums, and structs from a sea of `int`. See
[return-typing.md](/return-typing.md) for the rule that decision follows and the
defect that came from breaking it.

## Counting

3,771 annotations (ext-appkit 0.8.2), of which 55 are `@zep-construct`. 15
belong to `AppKit\Bridge\Bridge`, which is projected by hand in
`src/Runtime/Bridge.php` rather than generated — 13 for handles, pump,
target/action and delegates, plus `pointerOf`/`adopt`, the ext-metal pointer
seam added in ext-appkit 0.8.1. **3,756 generate**, across 97 classes.

Two counting traps, both of which have bitten:

- A pattern that matches `@zep` but not `@zep-construct` undercounts and
  reports phantom mismatches on the classes that use it — several headers
  declare more than one class (`ns-gridview.h` alone declares `NSGridRow`,
  `NSGridColumn` and `NSGridCell`), so counting headers and calling them
  classes understates it.
- Counting `-> int` annotations to estimate handles conflates handles with
  integers and enums. The honest count comes from the join: 716 handles, 153
  enum returns, 192 struct returns. (The figures in this bundle are whatever
  the gates last measured; they were stale at 687/145 for two waves before
  0.8.2, which is its own small lesson — quote the gate, not the memory.)

## C array parameters

Most parameters typed `array` in an annotation are ObjC collections —
`NSArray<NSView *> *`, `NSSet<NSIndexPath *> *`. Two are not: ext-appkit 0.8.2
binds `NSOpenGLPixelFormat::initWithAttributes:`
(`const NSOpenGLPixelFormatAttribute *`) and
`NSOpenGLContext::setValues:forParameter:` (`const GLint *`), which are plain
C arrays the extension marshals from a PHP list of ints.

Both would otherwise be mis-read by the join. A `GLint *` looks like an
out-parameter and would be skipped; a `const NSOpenGLPixelFormatAttribute *`
is NS-prefixed and pointer-valued, so it would have been typed as a handle.
`TypeJoin` therefore consults the annotation first: when the aligned `@zep`
parameter says `array` and the SDK type is pointer-valued but not one of the
collection types (`TypeName::isCScalarArray`), the parameter is an inbound C
array and is typed `array`. The annotation is the only thing that can tell a
C array from an out-parameter, because the SDK spells them identically.

The GL scalar typedefs (`GLint`, `GLenum`, `GLsizei`, …) are also registered
in `TypeName::isPrimitive`, which is what makes `GLint *` read as an
out-pointer and a `GLint` return not read as a handle. `CGLContextObj` and
`CGLPixelFormatObj` are pointer-valued typedefs written without a `*`, so
they already returned plain `int` — pointer bits, never boxed, which is
correct: they are the currency ext-opengl's `OpenGL\CGL\CGL` speaks.
## Hard-fail posture

An annotation that cannot be matched to an SDK declaration is a **fatal error**,
not a skip. A silently skipped selector produces a DTO that is quietly missing a
method — the exact failure the parity gate exists to catch, discovered a release
later.

Deliberate divergences live in an explicit exceptions table, so every gap is
either matched or named. Selector-to-PHP naming reverses the extension's own
convention (`setTitle:` → `setTitle`, `URL` → `Url` because Zephir lexes all-caps
identifiers as constants); irregular cases are listed rather than pattern-matched.

## Gates

| Gate | Asserts |
|---|---|
| `verify-parity.mjs` | DTO method count equals the independently counted annotation count, per class, no extras |
| `verify-return-typing.mjs` | `?ObjCObject` only for pointer / `id` / `instancetype` |
| `verify-enum-typing.mjs` | `NS_ENUM` vs `NS_OPTIONS`, re-derived from the SDK |
| `verify-reflection.mjs` | every extension call a DTO makes exists on the loaded extension |
| `verify-darwin-control.mjs` | the suite actually ran, rather than skipping green |

Parity counts methods, not signatures — which is precisely how 1,010 wrongly
boxed returns passed it. A gate proves what it measures and nothing adjacent.
