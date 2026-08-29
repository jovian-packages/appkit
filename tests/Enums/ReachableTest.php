<?php

declare(strict_types=1);

it('generates exactly the enum types the SDK join marked reachable, with no extras', function () {
    $list = dirname(__DIR__, 2) . '/scripts/Generator/reachable-enums.json';
    expect(is_file($list))->toBeTrue();

    $reachable = json_decode((string) file_get_contents($list), true);
    expect($reachable)->toBeArray()->not->toBeEmpty();

    $generated = [];
    foreach (glob(dirname(__DIR__, 2) . '/src/Enums/*.php') ?: [] as $file) {
        $source = (string) file_get_contents($file);
        expect($source)->toMatch('/enum\s+(\w+)\s*:/');
        preg_match('/enum\s+(\w+)\s*:/', $source, $match);
        $generated[] = $match[1];
    }
    sort($generated);
    $expected = $reachable;
    sort($expected);

    expect($generated)->toBe($expected);
});
