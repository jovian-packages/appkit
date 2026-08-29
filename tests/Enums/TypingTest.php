<?php

declare(strict_types=1);

it('types NS_ENUM parameters as EnumName|int and unwraps the case value', function () {
    $window = dirname(__DIR__, 2) . '/src/NS/NSWindow.php';
    expect(is_file($window))->toBeTrue();

    $source = (string) file_get_contents($window);
    expect($source)->toContain('NSWindowTitleVisibility|int $titleVisibility')
        ->and($source)->toContain('$titleVisibility instanceof \\BackedEnum ? $titleVisibility->value : $titleVisibility');
});

it('keeps NS_OPTIONS parameters as int because PHP enums cannot be OR\'d', function () {
    $window = dirname(__DIR__, 2) . '/src/NS/NSWindow.php';
    expect(is_file($window))->toBeTrue();

    $source = (string) file_get_contents($window);
    expect($source)->toMatch('/function setStyleMask\(int \$styleMask/')
        ->and($source)->not->toContain('NSWindowStyleMask|int');
});

it('types the four named return controls from independently re-parsed SDK kinds', function () {
    $returns = appkitReturnTypesJoin();
    $kinds = appkitSdkEnumKinds(appkitRequireSdkFrameworks());

    expect($kinds['NSWindowTitleVisibility'] ?? null)->toBe('ENUM')
        ->and($returns['NSWindow.titleVisibility']['rawReturn'] ?? null)->toBe('NSWindowTitleVisibility')
        ->and($returns['NSWindow.titleVisibility']['phpReturn'] ?? null)->toBe('NSWindowTitleVisibility|int')
        ->and($returns['NSWindow.titleVisibility']['phpReturn'] ?? null)->not->toBe('?ObjCObject');

    expect($kinds['NSWindowStyleMask'] ?? null)->toBe('OPTIONS')
        ->and($returns['NSWindow.styleMask']['rawReturn'] ?? null)->toBe('NSWindowStyleMask')
        ->and($returns['NSWindow.styleMask']['phpReturn'] ?? null)->toBe('int')
        ->and($returns['NSWindow.styleMask']['phpReturn'] ?? null)->not->toContain('NSWindowStyleMask');

    expect($kinds)->not->toHaveKey('NSControlStateValue')
        ->and($returns['NSSwitch.state']['rawReturn'] ?? null)->toBe('NSControlStateValue')
        ->and($returns['NSSwitch.state']['phpReturn'] ?? null)->toBe('int')
        ->and($returns['NSSwitch.state']['phpReturn'] ?? null)->not->toBe('NSControlStateValue|int')
        ->and($returns['NSSwitch.state']['phpReturn'] ?? null)->not->toBe('?ObjCObject');

    expect($kinds)->not->toHaveKey('NSInteger')
        ->and($returns['NSView.tag']['rawReturn'] ?? null)->toBe('NSInteger')
        ->and($returns['NSView.tag']['phpReturn'] ?? null)->toBe('int')
        ->and($returns['NSView.tag']['phpReturn'] ?? null)->not->toBe('?ObjCObject');
});

it('never types an NS_OPTIONS return as Enum|int and never boxes an enum-typed return', function () {
    $returns = appkitReturnTypesJoin();
    $kinds = appkitSdkEnumKinds(appkitRequireSdkFrameworks());
    $leaked = [];
    $boxed = [];

    foreach ($returns as $key => $entry) {
        $raw = preg_replace('/\s*\*+\s*$/', '', trim((string) ($entry['rawReturn'] ?? ''))) ?? '';
        $php = (string) ($entry['phpReturn'] ?? '');
        $kind = $kinds[$raw] ?? null;
        if ($kind === 'OPTIONS' && (str_contains($php, $raw) || str_ends_with($php, '|int'))) {
            $leaked[] = $key;
        }
        if (($kind === 'ENUM' || $kind === 'OPTIONS' || str_ends_with($php, '|int')) && $php === '?ObjCObject') {
            $boxed[] = $key;
        }
    }

    expect($leaked)->toBe([])
        ->and($boxed)->toBe([]);
});

it('the enum typing oracle measures returns and rejects an NS_OPTIONS return typed as an enum', function () {
    $oracle = dirname(__DIR__, 2) . '/scripts/gates/verify-enum-typing.mjs';
    expect(is_file($oracle))->toBeTrue();

    $cmd = 'node ' . escapeshellarg($oracle);
    exec($cmd . ' 2>&1', $output, $status);
    $combined = implode("\n", $output);

    expect($status)->toBe(0)
        ->and($combined)->toContain('enum_returns=')
        ->and($combined)->toContain('enum_return_types=')
        ->and($combined)->toContain('ENUM_TYPING_CONTROL_FAILED_AS_REQUIRED')
        ->and($combined)->toContain('ENUM_TYPING_OK');
});

/**
 * @return array<string, array{phpReturn?: string, returnsHandle?: bool, rawReturn?: string}>
 */
function appkitReturnTypesJoin(): array
{
    $path = dirname(__DIR__, 2) . '/scripts/Generator/return-types.json';
    expect(is_file($path))->toBeTrue();
    $decoded = json_decode((string) file_get_contents($path), true);
    expect($decoded)->toBeArray()->not->toBeEmpty();

    return $decoded;
}

/**
 * Re-parse SDK headers at test time. Do not ask the generator for a kind sidecar.
 *
 * @return array<string, 'ENUM'|'OPTIONS'>
 */
function appkitSdkEnumKinds(string $sdk): array
{
    $kinds = [];
    $pattern = '/typedef\s+(?:NS|CF)_(CLOSED_ENUM|ENUM|OPTIONS)\s*\([^,]+,\s*(\w+)\s*\)/';
    foreach (['CoreGraphics', 'Foundation', 'AppKit', 'QuartzCore'] as $framework) {
        $dir = $sdk . '/' . $framework . '.framework/Headers';
        if (! is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.h') ?: [] as $file) {
            $text = (string) file_get_contents($file);
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }
            foreach ($matches as $match) {
                $kinds[$match[2]] = $match[1] === 'OPTIONS' ? 'OPTIONS' : 'ENUM';
            }
        }
    }

    return $kinds;
}
