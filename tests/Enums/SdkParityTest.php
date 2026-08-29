<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\Emitter;
use Jovian\Bindings\AppKit\Generator\EnumMiner;

it('re-parses the SDK at test time and matches every generated enum case value', function () {
    $files = glob(dirname(__DIR__, 2) . '/src/Enums/*.php') ?: [];
    expect($files)->not->toBeEmpty();

    $defs = EnumMiner::definitions(appkitRequireSdkFrameworks());
    $compared = 0;

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        expect($source)->toMatch('/enum\s+(\w+)\s*:\s*int/');
        preg_match('/enum\s+(\w+)\s*:\s*int/', $source, $header);
        $type = $header[1];
        expect($defs)->toHaveKey($type);

        preg_match_all('/case\s+([A-Z][A-Z0-9_]*)\s*=\s*(-?\d+);/', $source, $matches, PREG_SET_ORDER);
        expect($matches)->not->toBeEmpty();

        $byCase = [];
        foreach ($matches as $match) {
            $byCase[$match[1]] = (int) $match[2];
        }

        foreach ($byCase as $case => $value) {
            $sdkValue = null;
            foreach ($defs[$type]['cases'] as $ident => $objcValue) {
                if (Emitter::enumCaseName($type, $ident) === $case) {
                    $sdkValue = $objcValue;
                    break;
                }
            }
            expect($sdkValue)->not->toBeNull("{$type}::{$case} has no SDK counterpart")
                ->and($value)->toBe($sdkValue);
            $compared++;
        }
    }

    expect($compared)->toBeGreaterThan(0);
    echo "ENUM_OK\n";
});
