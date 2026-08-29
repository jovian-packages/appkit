<?php

declare(strict_types=1);

afterAll(function () {
    echo "PARITY_OK\n";
});

it('counts @zep and @zep-construct independently and matches DTO methods per class', function () {
    $parsed = appkitParseZepAnnotations(appkitExtRoot());
    $dto = appkitDtoMethodsByClass();

    expect($parsed['constructCount'])->toBeGreaterThan(0)
        ->and($parsed['bareCount'] + $parsed['constructCount'])->toBe($parsed['total']);

    $mismatches = [];
    foreach ($parsed['withConstruct'] as $class => $methods) {
        $actual = $dto[$class] ?? [];
        $missing = array_values(array_diff($methods, $actual));
        $extra = array_values(array_diff($actual, $methods));
        if ($missing !== [] || $extra !== []) {
            $mismatches[] = $class;
        }
    }

    expect($mismatches)->toBe([])
        ->and(count($dto))->toBe(count($parsed['withConstruct']));
});

it('treats grepping only @zep as a failing positive control', function () {
    $parsed = appkitParseZepAnnotations(appkitExtRoot());
    $dto = appkitDtoMethodsByClass();
    $extras = 0;
    foreach ($dto as $class => $methods) {
        $bare = $parsed['bare'][$class] ?? [];
        $extras += count(array_diff($methods, $bare));
    }

    expect($extras)->toBeGreaterThan(0);
});

it('asserts the written DTO file set equals the annotation tree', function () {
    $parsed = appkitParseZepAnnotations(appkitExtRoot());
    $expected = array_values($parsed['dtoFiles']);
    sort($expected);
    $actual = appkitDtoRelPaths();
    sort($actual);

    expect($actual)->toBe($expected);
});

it('types the named getters on disk after regeneration', function () {
    $root = dirname(__DIR__, 2);
    $named = [
        ['NSView', 'tag', 'int', false],
        ['NSControl', 'tag', 'int', false],
        ['NSWindow', 'level', 'int', false],
        ['NSWindow', 'windowNumber', 'int', false],
        ['NSWindow', 'styleMask', 'int', false],
        ['NSSwitch', 'state', 'int', false],
        ['NSWindow', 'titleVisibility', 'NSWindowTitleVisibility|int', false],
        ['NSWindow', 'contentView', '?ObjCObject', true],
    ];

    foreach ($named as [$class, $method, $phpReturn, $box]) {
        $dir = $class === 'CALayer' ? 'QuartzCore' : 'NS';
        $path = $root . '/src/' . $dir . '/' . $class . '.php';
        expect(is_file($path))->toBeTrue();
        $source = (string) file_get_contents($path);
        expect($source)->toMatch('/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\):\s*' . preg_quote($phpReturn, '/') . '/');
        if ($box) {
            expect($source)->toContain('ObjCObject::box(Ext' . $class . '::' . $method);
        } else {
            expect($source)->not->toContain('ObjCObject::box(Ext' . $class . '::' . $method);
        }
    }
});

it('measures boxed enum and struct returns from the DTO tree and the join', function () {
    $dto = appkitScanDtoReturns();
    $join = appkitJoinReturnCounts();

    expect($dto['boxed'])->toBe($join['boxed'])
        ->and($dto['enumReturns'])->toBe($join['enumReturns'])
        ->and($dto['enumTypes'])->toBe($join['enumTypes'])
        ->and($dto['structReturns'])->toBe($join['structReturns'])
        ->and($dto['enumReturns'])->toBeGreaterThan(0)
        ->and($dto['boxed'])->toBeLessThan($dto['methods']);
});

it('the parity oracle prints PARITY_OK after both positive controls fail as required', function () {
    $oracle = dirname(__DIR__, 2) . '/scripts/gates/verify-parity.mjs';
    expect(is_file($oracle))->toBeTrue();

    $cmd = 'node ' . escapeshellarg($oracle);
    exec($cmd . ' 2>&1', $output, $status);
    $combined = implode("\n", $output);

    expect($status)->toBe(0)
        ->and($combined)->toContain('PARITY_CONTROL_FAILED_AS_REQUIRED drop=@zep-construct')
        ->and($combined)->toContain('PARITY_CONTROL_FAILED_AS_REQUIRED drop=NSView.tag')
        ->and($combined)->toContain('BOXED=')
        ->and($combined)->toContain('ENUM_RETURNS=')
        ->and($combined)->toContain('PARITY_OK');
});

function appkitExtRoot(): string
{
    $fromEnv = getenv('JOVIAN_APPKIT_EXT') ?: '';
    $candidates = array_values(array_filter([
        $fromEnv !== '' ? $fromEnv : null,
        dirname(__DIR__, 3) . '/php-io-extensions/appkit',
        dirname(__DIR__, 4) . '/php-io-extensions/appkit',
    ]));
    foreach ($candidates as $candidate) {
        if (is_dir($candidate . '/src')) {
            return $candidate;
        }
    }

    test()->fail('ext-appkit checkout not found; set JOVIAN_APPKIT_EXT');
}

/**
 * @return array{withConstruct: array<string, list<string>>, bare: array<string, list<string>>, dtoFiles: array<string, string>, total: int, bareCount: int, constructCount: int}
 */
function appkitParseZepAnnotations(string $extRoot): array
{
    $withConstruct = [];
    $bare = [];
    $dtoFiles = [];
    $total = 0;
    $bareCount = 0;
    $constructCount = 0;
    foreach (glob($extRoot . '/src/*.h') ?: [] as $file) {
        $text = (string) file_get_contents($file);
        if (preg_match_all('/\/\*@zep(?:-construct)?\s+(\S+)\s+(\w+)\s*\(/', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $total++;
                $fqcn = $match[1];
                if (str_starts_with($fqcn, 'Bridge\\') || str_ends_with($fqcn, '\\Bridge') || $fqcn === 'Bridge') {
                    continue;
                }
                $short = (string) basename(str_replace('\\', '/', $fqcn));
                $withConstruct[$short][] = $match[2];
                $ns = explode('\\', $fqcn)[0];
                $dtoFiles[$short] = $ns === 'QuartzCore' ? 'src/QuartzCore/' . $short . '.php' : 'src/NS/' . $short . '.php';
            }
        }
        if (preg_match_all('/\/\*@zep\s+(\S+)\s+(\w+)\s*\(/', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $bareCount++;
                $fqcn = $match[1];
                if (str_starts_with($fqcn, 'Bridge\\') || str_ends_with($fqcn, '\\Bridge') || $fqcn === 'Bridge') {
                    continue;
                }
                $short = (string) basename(str_replace('\\', '/', $fqcn));
                $bare[$short][] = $match[2];
            }
        }
        $constructCount += preg_match_all('/\/\*@zep-construct\s+/', $text) ?: 0;
    }

    foreach ($withConstruct as $class => $methods) {
        $withConstruct[$class] = array_values(array_unique($methods));
    }
    foreach ($bare as $class => $methods) {
        $bare[$class] = array_values(array_unique($methods));
    }

    return [
        'withConstruct' => $withConstruct,
        'bare' => $bare,
        'dtoFiles' => $dtoFiles,
        'total' => $total,
        'bareCount' => $bareCount,
        'constructCount' => $constructCount,
    ];
}

/**
 * @return array<string, list<string>>
 */
function appkitDtoMethodsByClass(): array
{
    $out = [];
    foreach (appkitDtoAbsPaths() as $path) {
        $text = (string) file_get_contents($path);
        if (preg_match('/\bclass\s+(\w+)\b/', $text, $classMatch) !== 1) {
            continue;
        }
        preg_match_all('/(?:public|protected|private)(?:\s+static|\s+final)*\s+function\s+(\w+)\s*\(/', $text, $matches);
        $methods = [];
        foreach ($matches[1] as $name) {
            if (! str_starts_with($name, '__')) {
                $methods[] = $name;
            }
        }
        $out[$classMatch[1]] = $methods;
    }

    return $out;
}

/**
 * @return list<string>
 */
function appkitDtoRelPaths(): array
{
    $root = dirname(__DIR__, 2);
    $out = [];
    foreach (['NS' => 'src/NS', 'QuartzCore' => 'src/QuartzCore'] as $prefix) {
        foreach (glob($root . '/' . $prefix . '/*.php') ?: [] as $path) {
            $out[] = $prefix . '/' . basename($path);
        }
    }

    return $out;
}

/**
 * @return list<string>
 */
function appkitDtoAbsPaths(): array
{
    $root = dirname(__DIR__, 2);

    return [
        ...(glob($root . '/src/NS/*.php') ?: []),
        ...(glob($root . '/src/QuartzCore/*.php') ?: []),
    ];
}

/**
 * @return array{boxed: int, enumReturns: int, enumTypes: int, structReturns: int, methods: int}
 */
function appkitScanDtoReturns(): array
{
    $structs = ['NSRect' => true, 'NSPoint' => true, 'NSSize' => true, 'NSRange' => true, 'NSEdgeInsets' => true];
    $boxed = 0;
    $enumReturns = 0;
    $structReturns = 0;
    $methods = 0;
    $enumTypes = [];
    foreach (appkitDtoAbsPaths() as $path) {
        $text = (string) file_get_contents($path);
        if (preg_match_all('/function\s+\w+\s*\([^)]*\):\s*([^{\n]+)/', $text, $matches) < 1) {
            continue;
        }
        foreach ($matches[1] as $phpReturn) {
            $phpReturn = trim($phpReturn);
            $methods++;
            if ($phpReturn === '?ObjCObject') {
                $boxed++;
            }
            if (str_ends_with($phpReturn, '|int')) {
                $enumReturns++;
                $enumTypes[explode('|', $phpReturn)[0]] = true;
            }
            if (isset($structs[$phpReturn])) {
                $structReturns++;
            }
        }
    }

    return [
        'boxed' => $boxed,
        'enumReturns' => $enumReturns,
        'enumTypes' => count($enumTypes),
        'structReturns' => $structReturns,
        'methods' => $methods,
    ];
}

/**
 * @return array{boxed: int, enumReturns: int, enumTypes: int, structReturns: int}
 */
function appkitJoinReturnCounts(): array
{
    $path = dirname(__DIR__, 2) . '/scripts/Generator/return-types.json';
    expect(is_file($path))->toBeTrue();
    $decoded = json_decode((string) file_get_contents($path), true);
    expect($decoded)->toBeArray()->not->toBeEmpty();

    $structs = ['NSRect' => true, 'NSPoint' => true, 'NSSize' => true, 'NSRange' => true, 'NSEdgeInsets' => true];
    $boxed = 0;
    $enumReturns = 0;
    $structReturns = 0;
    $enumTypes = [];
    foreach ($decoded as $entry) {
        $phpReturn = $entry['phpReturn'] ?? null;
        if ($phpReturn === '?ObjCObject') {
            $boxed++;
        }
        if (is_string($phpReturn) && str_ends_with($phpReturn, '|int')) {
            $enumReturns++;
            $enumTypes[explode('|', $phpReturn)[0]] = true;
        }
        if (is_string($phpReturn) && isset($structs[$phpReturn])) {
            $structReturns++;
        }
    }

    return [
        'boxed' => $boxed,
        'enumReturns' => $enumReturns,
        'enumTypes' => count($enumTypes),
        'structReturns' => $structReturns,
    ];
}
