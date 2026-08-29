---
type: Decision
title: Projection, not composition — the rule that bounds this package
description: >-
  A method belongs in jovian/appkit only if it is exactly one extension call
  with the same arguments in the same order. Anything that bundles calls is a
  framework and belongs one layer up.
tags: [projection, layering, scope, appkit]
status: draft
generated:
  by: claude-opus-5/cursor
  at: 2026-08-29T04:20:00Z
---

# Projection, not composition

`ext-appkit` binds AppKit 1:1 and deliberately withholds two things, naming this
package as the home for one of them: enum values belong here, composition does
not.

The test to apply to any proposed method: **can it be written as one extension
call?** `$win->setTitle('hello')` is `NSWindow::setTitle($handle, 'hello')` in a
different shape, so it invents nothing and is legitimate.

## Why there is no `create()`

An earlier version of the extension shipped `NSWindow::create($title, $w, $h)`.
That single call chose a style mask, a backing store, and a release-when-closed
policy on the caller's behalf. The current extension removed it on purpose, and
re-adding it here would undo that work. Construction is spelled
`initWithContentRectStyleMaskBackingDefer(NSRect, int, NSBackingStoreType|int, bool)`
because that is what AppKit actually offers.

The predecessor packages `jovian/dep-appkit` and `jovian/dep-gtk` were written
against that older, opinionated extension. Their helper files are a style
reference at most — the methods they call no longer exist.

## The layer stack

`ext-appkit` (1:1 binding, zero opinion) → **`jovian/appkit`** (enums + typed
projection) → `jovian/venusian-appkit` (composition) → Surface (cross-platform
abstraction).

A shared abstraction over AppKit and GTK is Surface's job. Building one here
would couple this package to [`jovian/gtk`](https://github.com/jovian/gtk),
which must stay independent — the two packages are shape-parallel by design and
share no code.

## What this rules out

Convenience constructors, default style masks, an application run loop, window
management policy, and any helper that exists only to save the caller two lines.

Note that this package also ships **no global helper functions**, unlike the
rest of the house pattern. AppKit has no C symbol names to mirror — Apple
documents `-[NSWindow setTitle:]`, not a function — so a helper layer would
invent a third naming scheme matching neither the documentation nor the
extension. The DTO is the helper layer here. `jovian/gtk` does ship helpers,
because `gtk_button_set_label` is a real symbol name.

See [runtime.md](/runtime.md) for what the projection is built on and
[generation.md](/generation.md) for how it is produced.
