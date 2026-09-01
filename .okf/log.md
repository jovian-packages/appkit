# Change log

## 2026-08-31 (AV segment)
* **Generation**: the emitter, `Framework` map, enum miner and gates learn the `AV\`
  namespace segment (AVKit, falling back to AVFoundation for headers) alongside
  QuartzCore; DTOs land in `src/AV/`, enums mined from both frameworks
  (AVPlayerStatus, AVPlayerTimeControlStatus, AVPlayerViewControlsStyle).
  `AVPlayerView extends NSView` falls out of the parenting walk for free. All gates
  green, including the AV directory in parity/reflection/enum scans.

## 2026-08-31 (parenting)
* **Fix**: `Emitter` walks past unprojected ancestors to the nearest projected one when
  choosing a PHP parent — `NSTextFieldCell : NSActionCell : NSCell` used to flatten onto
  `ObjCObject` because NSActionCell has no projection, hiding every inherited NSCell
  method (`cellSizeForBounds` among them). Eight classes gained honest parents: six
  cells -> NSCell, NSSavePanel -> NSWindow, NSTextStorage -> NSAttributedString. All
  gates green (GEN/PARITY/RETURN/ENUM/PARAM_TYPING/REFLECTION/DARWIN_CONTROL).

## 2026-08-30 (typedefs)
* **Fix**: `TypeName::canonicalize` resolves the SDK typedefs behind the param gate's 79
  findings — 27 NSString-backed names and 4 CGFloat-backed (NSFontWeight & co.) — via a
  derived map. Regenerated; **PARAM_TYPING_OK (3599 methods)**, return and enum gates
  unchanged. `systemFontOfSizeWeight` now takes the float it always wanted.

## 2026-08-30
* **Gate**: `scripts/gates/verify-param-typing.mjs` — every projected DTO parameter type
  must be backed by the ext's `@zep` parameter type (return typing was gated, parameters
  were not). Structs expand by arity (NSRange components are ints). Currently **red by
  design: 79 real mismatches** — 68 parameters projected `int` where the ext takes
  `string` (typedef'd NSString names: NSRunLoopMode, NSUserInterfaceItemIdentifier,
  NSCollectionViewSupplementaryElementKind, NSColorName, …) and 11 projected `int` where
  the ext takes `double` (NSFontWeight, a CGFloat typedef). Root cause is
  `TypeName::canonicalize` not resolving those typedefs; every one raises TypeError under
  strict_types on first use. Not fixed in this pass.
* **Resolved**: the 24 NSDictionary/NSSet parameters projected `array` over ext `int`
  handles (first hit: `orderFrontStandardAboutPanelWithOptions`). ext-appkit now
  marshals PHP arrays for them (`var` params); regeneration confirmed the `array`
  projection is correct rather than accidental.

## 2026-08-29

- Bundle created at package finish (WP5), alongside `README.md` and
  `AGENTS.md`. Five concepts: projection rule, runtime, return typing, enums and
  values, generation. All `status: draft` pending human verification.
- `composer.json` carried `extra.planned-helpers` with **238 entries** — a
  scaffold inherited from the house pattern where a wrapper package ships global
  helper functions. This package ships none by design: AppKit has no C symbol
  names to mirror, so a helper layer would invent a third naming scheme matching
  neither Apple's documentation nor the extension. Removed, along with the empty
  `autoload.files` array that implied helpers were still coming.
- Three claims in the design document did not survive contact with the generated
  code, and the documentation now follows the code rather than the design:
  - **Object parameters are `int`, not DTOs.** The design argued that mirroring
    AppKit's inheritance chain would let `NSButton` satisfy a parameter typed
    `NSView`. Emitted parameters are plain `int` handles, so callers write
    `$content->addSubview($btn->handle)`. The hierarchy still pays off for
    `instanceof` on returns. Worth revisiting deliberately rather than by
    accident: accepting `ObjCObject|int` would be additive and non-breaking.
  - **Callback senders are boxed** — this one the design got right, and the
    smoke test proves it: `$sender === $btn` holds by identity, not equality.
    `Bridge` wraps `setAction`, `observeNotification` and `delegateOn` at the
    boundary rather than leaving it to each caller.
  - **`onAction` lives on `ObjCObject`, not `NSControl`.** The design placed it
    on `NSControl` (design doc line 145). WP3 moved it to the base class, which
    is the better call — target/action reaches `NSMenuItem` and other
    non-`NSControl` responders — but the move was never written down, so it is
    recorded here.
- Enum case naming is not uniform: most enums drop the type prefix
  (`NSWindowStyleMask::TITLED`, `NSBezelStyle::PUSH`), but some keep it
  (`NSBackingStoreType::NS_BACKING_STORE_BUFFERED`). Case *values* are verified
  against the SDK and correct either way. Normalizing names is a breaking change
  to a published API, so it lands before 0.8.0 ships or not at all.
- An independent validation pass caught five factual errors in the first draft
  of this bundle, all now corrected: `NSControlStateValue` and friends were
  described as having "no enumerators" when they are
  `NS_TYPED_EXTENSIBLE_ENUM` with `static const` values; `ClassMap` was listed
  as hand-written when it is generated; the `@zep-construct` spread was given as
  39 classes when 39 is the header count and 46 the class count; `--check` was
  called side-effect free when it writes `ClassMap` and three sidecars; and
  `--spine` was called nine classes when it is eight. Every documented count was
  independently recounted and the rest held. The lesson is narrow but real:
  prose written from a build log inherits the log's imprecision, and a knowledge
  bundle is only worth having if its numbers are re-measured rather than
  restated.
- Known stale artifacts left in place because they belong to other work
  packages, listed here so they are not rediscovered as bugs:
  `examples/smoke.php` still says Wave B "waits for WP4"; `Registry.php:84`
  still says `ClassMap` arrives "until WP1"; and the design document's Scale
  section still reports 86 NS classes, 1,033 handle returns and 311 struct
  returns, which the join has since corrected to 87 plus `CALayer`, 687 and 192.
- Design spec amended 2026-08-29: Scale counts now 87 NS + CALayer / 687
  handles / 192 structs; object params documented as `int`; `onAction` on
  `ObjCObject`; mixed enum case names accepted. N6 stamped against that amendment.
- Gate `F3` (ecosystem docs page at
  `venusian.projectsaturnstudios.com/ecosystem/jovian/appkit/0.8.x/overview`)
  could not be run: the host does not resolve. Abandoned with a handoff rather
  than removed, so the requirement stays visible.

### Carried forward from the build

- The `TypeName::isObject()` defect is recorded in
  [return-typing.md](/return-typing.md) rather than only in the commit history,
  because the lesson generalizes: the decision was made *after* canonicalization
  had already destroyed the pointer star it depended on. 1,010 methods boxed,
  including `NSView::tag()` and `NSWindow::level()`, and every gate then in
  existence passed, because parity counts methods and the smoke test never
  touched a scalar getter.
- Every extension-dependent test skips when `ext-appkit` is absent, so a green
  run off Darwin proves less than it appears to. `verify-darwin-control.mjs`
  exists to catch a skipped suite reporting success.
