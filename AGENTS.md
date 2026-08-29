# Agent guidelines — jovian/appkit

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from the Composer dist via `.gitattributes` `export-ignore`). Before changing code or advising on this package: read [`.okf/index.md`](.okf/index.md) first, open only the concepts the task needs, prefer `status: stable` over `draft`. When you learn something durable, update the affected concept(s) and append [`.okf/log.md`](.okf/log.md); new or changed concepts stay `status: draft` until a human verifies them.

## Where this package sits

`ext-appkit` (1:1 binding, zero opinion) → **`jovian/appkit`** (enums + typed projection) → `jovian/venusian-appkit` (composition) → Surface (cross-platform abstraction). Never reach up the stack, and never build a cross-platform abstraction here — that is Surface's job, and doing it here would couple this package to `jovian/gtk`.

## Rules

1. **Projection, not composition.** A method is legitimate only if it is exactly one extension call with the same arguments in the same order. The test: *can it be written as one extension call?* If not, it belongs in `jovian/venusian-appkit`. There is no `create()`; the old extension had one and it was deliberately removed.
2. **Never hand-edit generated code.** `src/NS/**`, `src/QuartzCore/**`, `src/Enums/**` and `src/Runtime/ClassMap.php` come from `php scripts/generate.php`, which joins the `@zep` and `@zep-construct` annotations in `ext-appkit`'s `src/*.h` against the macOS SDK headers. Edit the generator, then regenerate. `ClassMap` is the one generated file outside the generated directories — check the header comment before editing anything under `src/Runtime/`.
3. **Hand-written code is `src/Runtime/**` (except `ClassMap`) and `src/Values/**`.** `ObjCObject`, `Registry`, `Lifetime`, `Delegate`, `Bridge`, and the five struct value objects.
4. **A return boxes only when the SDK says object pointer, `id`, or `instancetype`.** Decide on the *raw* SDK type, never the canonicalized one — canonicalizing strips the `*`. An `NSInteger` or an enum that boxes becomes `ObjCObject::box(5)`, which looks handle 5 up in the live registry and hands back an unrelated object. Pointer-ness alone is not enough: `isHandleType()` also excludes `NSString`/`NSMutableString` and the collection types, which the extension marshals to PHP strings and arrays. Gate: `verify-return-typing.mjs`.
5. **`NS_ENUM` is `Enum|int`; `NS_OPTIONS` stays `int`,** for parameters and returns alike, because PHP enums cannot be OR'd. Gate: `verify-enum-typing.mjs`, which re-derives kinds from the SDK rather than trusting a sidecar the generator wrote.
6. **Object parameters are `int` handles.** Only returns and callback arguments are boxed. Pass `$obj->handle`.
7. **PHP refcount owns the handle.** Boxing retains, `__destruct` releases. The identity map holds weak references; a destructor evicts its registry entry only if that entry still points at itself, because handles get recycled; and destructors stop releasing once `Lifetime::isShuttingDown()`, because releasing after `NSApp` teardown is a crash while leaking at exit is correct.
8. **Enums are int-backed with FULLY UPPERCASE cases. No class constants anywhere.** Prefer `is_null($x)` over `$x === null`. Gate: `verify-style.mjs`.
9. **Counting annotations? Match `@zep(?:-construct)?`.** A pattern that excludes the construct form undercounts by 51, spread across 46 classes declared in 39 header files — several headers declare more than one class, so counting headers understates the damage. The extension declares 3,614 annotations; 13 are `AppKit\Bridge\Bridge`, which this package projects by hand, leaving the 3,601 that generate.

## Verification

Work in this package is gated. Ledgers live in `.unlazy/appkit-bindings/`; do not check a box without an evidence stamp from the checker, and do not treat a prose summary as evidence.

```bash
php scripts/generate.php --check --ext=../../php-io-extensions/appkit   # GEN_OK
node scripts/gates/verify-parity.mjs             # PARITY_OK       3601 = 3601
node scripts/gates/verify-return-typing.mjs      # RETURN_TYPING_OK 687 boxed
node scripts/gates/verify-enum-typing.mjs        # ENUM_TYPING_OK   145 enum returns
node scripts/gates/verify-reflection.mjs         # REFLECTION_OK    needs ext loaded
node scripts/gates/verify-darwin-control.mjs reflection  # DARWIN_CONTROL_CONSISTENT
node scripts/gates/verify-style.mjs              # STYLE_OK
vendor/bin/pest
```

Every extension-dependent test skips when `ext-appkit` is absent, so a green run off Darwin proves less than it looks like. That is what the Darwin control gate exists to catch — a skipped test reporting success.

When you add a gate, give it a positive control: deliberately break the thing it checks, watch it fail, then restore. A gate that has never failed is not yet evidence.
