---
okf_version: "0.2"
---

# jovian/appkit — knowledge bundle

The AppKit object graph typed in PHP: `ext-appkit` projected into DTOs, plus the
enums the extension deliberately withholds. Read this index first, then open
only the concepts the task needs.

- [projection-rule.md](/projection-rule.md) — the rule that bounds this package:
  one method = one extension call. Why there is no `create()` and no global
  helpers, and where composition goes instead.
- [runtime.md](/runtime.md) — the hand-written core: `ObjCObject`, the weak
  identity map, refcount ownership with its recycling and shutdown hazards, and
  the boxing boundary for callbacks.
- [return-typing.md](/return-typing.md) — when a return boxes, why the raw SDK
  type is the only sound basis for deciding, and the 1,010-method defect that
  came from deciding it after canonicalization.
- [enums-and-values.md](/enums-and-values.md) — SDK-mined enums (146 as of GC), why
  `NS_OPTIONS` stays `int`, the `typedef NSInteger` trap, and the five struct
  value objects.
- [generation.md](/generation.md) — the annotation × SDK join, the counting
  traps, hard-fail posture, and what each gate does and does not prove.

## Fast facts

| | |
|---|---|
| Version | 0.8.0, PHP `^8.4`, requires `ext-appkit` `^0.8.0`, macOS only |
| Namespace | `Jovian\Bindings\AppKit\` |
| Generated | 103 classes / 3,799 methods: NS 94, QuartzCore 1, AV 2, GC 6 (GameController) |
| Hand-written | `src/Runtime/` (5 of 6; `ClassMap` is generated), `src/Values/` (5 structs) |
| Returns | 746 handles, 154 enums, 192 structs (gates, after GC) |
