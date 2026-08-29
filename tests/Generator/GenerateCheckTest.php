<?php

declare(strict_types=1);

it('prints GEN_OK after every annotation matches a declaration', function () {
    $generate = dirname(__DIR__, 2) . '/scripts/generate.php';
    expect(is_file($generate))->toBeTrue();

    $ext = getenv('JOVIAN_APPKIT_EXT') ?: dirname(__DIR__, 3) . '/php-io-extensions/appkit';
    if (! is_dir($ext . '/src')) {
        $ext = dirname(__DIR__, 4) . '/php-io-extensions/appkit';
    }
    expect(is_dir($ext . '/src'))->toBeTrue();

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($generate)
        . ' --check --ext=' . escapeshellarg($ext);
    exec($cmd . ' 2>&1', $output, $status);
    $combined = implode("\n", $output);

    $root = dirname(__DIR__, 2);

    expect($status)->toBe(0)
        ->and($combined)->toContain('GEN_OK')
        ->and($combined)->not->toContain('unmatched annotation')
        ->and(is_file($root . '/scripts/Generator/return-types.json'))->toBeTrue();
});
