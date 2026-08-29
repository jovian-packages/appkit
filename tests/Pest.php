<?php

declare(strict_types=1);

/*
| Pest bootstrap for jovian/appkit.
|
| Extension-dependent suites skip when ext-appkit is absent. Construction
| is one initWith* / buttonWith* extension call when a test needs a handle.
*/

use AppKit\NS\NSApplication\NSApplication as ExtNSApplication;
use AppKit\NS\NSButton\NSButton as ExtNSButton;
use AppKit\NS\NSView\NSView as ExtNSView;

function appkitExtensionLoaded(): bool
{
    return extension_loaded('appkit');
}

function appkitSharedApplication(): int
{
    return ExtNSApplication::sharedApplication();
}

function appkitViewHandle(): int
{
    return ExtNSView::initWithFrame(0.0, 0.0, 8.0, 8.0);
}

function appkitButtonHandle(): int
{
    return ExtNSButton::buttonWithTitleTargetAction('Identity', 0, '');
}

function appkitSdkFrameworks(): ?string
{
    $fromEnv = getenv('JOVIAN_APPKIT_SDK') ?: '';
    $candidates = array_values(array_filter([
        $fromEnv !== '' ? $fromEnv : null,
        '/Library/Developer/CommandLineTools/SDKs/MacOSX.sdk/System/Library/Frameworks',
        '/Applications/Xcode.app/Contents/Developer/Platforms/MacOSX.platform/Developer/SDKs/MacOSX.sdk/System/Library/Frameworks',
    ]));
    foreach ($candidates as $candidate) {
        if (is_dir($candidate . '/AppKit.framework/Headers')) {
            return $candidate;
        }
    }

    return null;
}

function appkitRequireSdkFrameworks(): string
{
    $sdk = appkitSdkFrameworks();
    if (is_null($sdk)) {
        test()->skip('macOS SDK headers are not present; set JOVIAN_APPKIT_SDK');
    }

    return $sdk;
}
