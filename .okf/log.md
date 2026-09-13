# Change log

## 2026-09-13 (windowed OpenGL — NSOpenGLPixelFormat / NSOpenGLContext / NSOpenGLView, 0.8.2)
* **Generation**: ext-appkit 0.8.2's three windowed-GL classes project without
  a hand-written line: `NSOpenGLPixelFormat` (5), `NSOpenGLContext` (25),
  `NSOpenGLView` (14), and the enum miner picked up
  `NSOpenGLContextParameter` (15 cases) on the way.
  `joined=3756 bridge=15 GEN_OK`, 97 generated classes. Those classes are
  `API_DEPRECATED` in their entirety and bound anyway under ext-appkit's
  `@audit deprecated-class` exemption; that is the extension's ruling to
  make, and this package simply projects what the annotations say.
* **Generator**: two SDK shapes the join had never met.
  (1) **C array parameters.** `initWithAttributes:`
  (`const NSOpenGLPixelFormatAttribute *`) and `setValues:forParameter:`
  (`const GLint *`) are plain C arrays the extension marshals from a PHP list
  of ints, not ObjC collections. A `GLint *` looks exactly like an
  out-parameter and would have been skipped; an NS-prefixed pointer would
  have been typed as a handle. `TypeJoin` now consults the annotation first:
  an aligned `@zep` parameter of type `array` whose SDK type is
  pointer-valued but not a collection (`TypeName::isCScalarArray`) is an
  inbound C array and types as `array`. The annotation is the only thing that
  can tell a C array from an out-parameter — the SDK spells them identically.
  (2) **GL scalar typedefs.** `GLint`, `GLuint`, `GLsizei`, `GLenum`,
  `GLbitfield`, `GLboolean`, `GLbyte`, `GLubyte`, `GLshort`, `GLushort`,
  `GLfloat`, `GLdouble` joined `TypeName::isPrimitive`, which is what makes
  `GLint *` read as an out-pointer and a `GLint` return not read as a handle.
  `CGLContextObj` / `CGLPixelFormatObj` needed nothing: they are
  pointer-valued typedefs written without a `*`, so they already projected as
  plain `int` pointer bits, never boxed — which is right, because that is the
  currency `OpenGL\CGL\CGL` speaks.
  The regeneration diff is the proof the change is scoped: only the four new
  files, `ClassMap`, and the three join sidecars moved. No existing generated
  method changed.
* **Gates**: `PARITY_OK` (files=97 written=97, BOXED=716 ENUM_RETURNS=153
  STRUCT_RETURNS=192), `RETURN_TYPING_OK` (716 handle returns),
  `ENUM_TYPING_OK` (enum_params=249 enum_returns=153 options_leaked=0),
  `REFLECTION_OK` (files=97 calls=3756, control mistype still caught),
  `DARWIN_CONTROL_CONSISTENT`, `STYLE_OK`. `vendor/bin/pest`: 72 passed,
  3,883 assertions, `SMOKE_OK`.
* **Proof**: `examples/proof_nsopengl_typed.php` →
  `PROOF_NSOPENGL_TYPED_OK`. The DTO port of ext-appkit's
  `examples/proof_nsopengl.php`: an `NSOpenGLView` on a 4.1 core pixel format
  inside a real `NSWindow`, the swap interval set through
  `NSOpenGLContextParameter::SWAP_INTERVAL`, the frame drawn by
  php-io-extensions/opengl and byte-checked out of `glReadPixels` (centre
  255,128,64,255; corner 0,0,0,255) before the swap, then 555 frames in 2.00s
  through `Bridge::pump` on an M1 Pro. `NSOpenGLContext::CGLContextObj()` and
  `CGL::CGLGetCurrentContext()` are asserted to be the same address.
  The OpenGL calls stay on the raw `OpenGL\*` extension classes rather than
  `jovian/ogx`: that package is a sibling checkout, not a dependency, and
  requiring its autoloader by relative path would couple two jovian packages
  that are deliberately independent.
* **Version**: 0.8.0 → 0.8.2, `ext-appkit` `^0.8.0` → `^0.8.2` (the wave needs
  the three classes, so the constraint has to say so). The fast-facts counts
  in this bundle had been stale at 687/145 since the NSDateFormatter wave and
  now quote what the gates measured.


## 2026-09-13 (Bridge pointerOf/adopt — ext-metal seam)
* **Runtime**: ext-appkit 0.8.1 adds `Bridge::pointerOf(int $handle): int` and
  `Bridge::adopt(string $className, int $pointerBits): int` — the only
  inter-extension currency, raw pointer bits, so a `CAMetalLayer` minted by
  ext-metal can be adopted here and a registry object here can be handed to
  ext-metal. Projected by hand in `src/Runtime/Bridge.php`, same shape as every
  other Bridge call: one extension call, same arguments, same order.
  `ObjCObject::pointerOf()` mirrors the query side on a boxed instance; `adopt`
  stays a bare `Bridge` call because pairing it with `box()` would be
  composition, out of scope here (see `.okf/projection-rule.md`). Bridge grows
  from 13 to 15 hand-projected annotations; the generated tree is untouched
  (`joined=3712 bridge=15 GEN_OK`). `vendor/bin/pest` green.

## 2026-09-12 (NSDateFormatter + NSIndexSet)
* **Generation**: `NSDateFormatter` (full Foundation join, `NSDateFormatterStyle` /
  `NSDateFormatterBehavior` / `NSFormattingContext` enums) and curated `NSIndexSet`
  (`indexSet`, `indexSetWithIndex:`, `containsIndex:`) emit from the new ext-appkit
  annotations. `Amsymbol` / `Pmsymbol` sit in the selector-exceptions table (Zephir
  all-caps rule). The enum miner now walks CoreFoundation before Foundation so
  `NSDateFormatterStyle` can resolve `kCFDateFormatter*` aliases (No/Short/Medium/
  Long/Full). `joined=3712 bridge=13 GEN_OK`. `vendor/bin/pest` green.

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
