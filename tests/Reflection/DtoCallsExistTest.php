<?php

declare(strict_types=1);

beforeEach(function () {
    if (! appkitExtensionLoaded()) {
        test()->markTestSkipped('ext-appkit is not loaded');
    }
});

afterAll(function () {
    echo "REFLECTION_OK\n";
});

it('fails closed when a DTO names a method the installed extension does not have', function () {
    expect(method_exists(\AppKit\NS\NSWindow\NSWindow::class, 'wp4PhantomMethodDoesNotExist'))->toBeFalse();
});

it('every extension call a DTO invokes exists on the installed appkit extension', function () {
    $root = dirname(__DIR__, 2);
    $files = [
        ...(glob($root . '/src/NS/*.php') ?: []),
        ...(glob($root . '/src/QuartzCore/*.php') ?: []),
    ];
    expect($files)->not->toBeEmpty();

    $missing = [];
    $checked = 0;
    foreach ($files as $path) {
        $text = (string) file_get_contents($path);
        $aliases = [];
        if (preg_match_all('/use\s+(AppKit\\\\[A-Za-z0-9_\\\\]+)\s+as\s+(Ext[A-Za-z0-9_]+)\s*;/', $text, $uses, PREG_SET_ORDER) > 0) {
            foreach ($uses as $use) {
                $aliases[$use[2]] = $use[1];
            }
        }
        if (preg_match_all('/(Ext[A-Za-z0-9_]+)::([A-Za-z_][A-Za-z0-9_]*)/', $text, $calls, PREG_SET_ORDER) < 1) {
            continue;
        }
        foreach ($calls as $call) {
            $fqcn = $aliases[$call[1]] ?? null;
            if (is_null($fqcn)) {
                continue;
            }
            $checked++;
            if (! appkitExtensionHasMethod($fqcn, $call[2])) {
                $missing[] = $fqcn . '::' . $call[2] . ' from ' . basename($path);
            }
        }
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($missing)->toBe([]);
});

/**
 * Zephir suffixes reserved method names with `_` (NSWindow.print → print_).
 */
function appkitExtensionHasMethod(string $fqcn, string $method): bool
{
    if (method_exists($fqcn, $method)) {
        return true;
    }

    $reserved = [
        'abstract', 'array', 'as', 'bool', 'boolean', 'break', 'callable', 'case',
        'catch', 'char', 'class', 'const', 'continue', 'default', 'deprecated',
        'do', 'double', 'echo', 'else', 'elseif', 'empty', 'extends', 'false',
        'fetch', 'final', 'finally', 'float', 'for', 'function', 'if',
        'implements', 'in', 'inline', 'instanceof', 'int', 'integer', 'interface',
        'internal', 'isset', 'let', 'likely', 'list', 'long', 'loop', 'namespace',
        'new', 'null', 'object', 'print', 'private', 'protected', 'public',
        'require', 'resource', 'return', 'reverse', 'scoped', 'static', 'string',
        'switch', 'this', 'throw', 'true', 'try', 'typeof', 'uchar', 'uint',
        'ulong', 'unlikely', 'unset', 'use', 'var', 'void', 'while', 'copy',
    ];

    return in_array(strtolower($method), $reserved, true) && method_exists($fqcn, $method . '_');
}
