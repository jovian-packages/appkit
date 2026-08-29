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

3,614 annotations, of which 51 are `@zep-construct`. 13 belong to
`AppKit\Bridge\Bridge`, which is projected by hand in `src/Runtime/Bridge.php`
rather than generated. **3,601 generate**, across 88 classes.

Two counting traps, both of which have bitten:

- A pattern that matches `@zep` but not `@zep-construct` undercounts by 51 and
  reports phantom mismatches on the 46 classes that use it. Those 46 live in 39
  header files — `ns-gridview.h` alone declares `NSGridRow`, `NSGridColumn` and
  `NSGridCell` — so counting headers and calling them classes understates it.
- Counting `-> int` annotations to estimate handles conflates handles with
  integers and enums. The honest count comes from the join: 687 handles, 145
  enum returns, 192 struct returns.

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
