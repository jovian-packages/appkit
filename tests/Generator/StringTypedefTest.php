<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\Annotation;
use Jovian\Bindings\AppKit\Generator\EnumKind;
use Jovian\Bindings\AppKit\Generator\SdkMethod;
use Jovian\Bindings\AppKit\Generator\SdkParam;
use Jovian\Bindings\AppKit\Generator\StringTypedefs;
use Jovian\Bindings\AppKit\Generator\TypeJoin;
use Jovian\Bindings\AppKit\NS\NSNotificationCenter;
use Jovian\Bindings\AppKit\NS\NSTableColumn;
use Jovian\Bindings\AppKit\NS\NSTableView;
use Jovian\Bindings\AppKit\NS\NSToolbar;
use Jovian\Bindings\AppKit\NS\NSWindow;
use Jovian\Bindings\AppKit\QuartzCore\CALayer;
use Jovian\Bindings\AppKit\Runtime\Bridge;
use Jovian\Bindings\AppKit\Runtime\ObjCObject;

/**
 * @return list<string>
 */
function stringTypedefParamTypes(string $class, string $method): array
{
    return array_map(
        static fn (ReflectionParameter $param): string => (string) $param->getType(),
        (new ReflectionMethod($class, $method))->getParameters(),
    );
}

function stringTypedefReturnType(string $class, string $method): string
{
    return (string) (new ReflectionMethod($class, $method))->getReturnType();
}

it('mines NSString-backed typedefs from the SDK and leaves integer typedefs out', function () {
    $names = StringTypedefs::names(appkitRequireSdkFrameworks());

    foreach ([
        'NSNotificationName',            // NS_TYPED_EXTENSIBLE_ENUM, `NSString *Name` spelling
        'NSAppearanceName',
        'NSPasteboardType',
        'NSUserInterfaceItemIdentifier', // NS_SWIFT_BRIDGED_TYPEDEF
        'NSWindowFrameAutosaveName',
        'NSToolbarItemIdentifier',
        'CALayerContentsGravity',        // NS_TYPED_ENUM + availability
        'AVLayerVideoGravity',           // NS_STRING_ENUM
    ] as $name) {
        expect($names)->toHaveKey($name);
    }
    foreach (['NSWindowLevel', 'NSControlStateValue', 'NSInteger', 'NSString', 'NSModalResponse'] as $name) {
        expect($names)->not->toHaveKey($name);
    }
});

it('reads typedef spellings but not block typedefs returning NSString', function () {
    $src = "typedef NSString *NSNotificationName NS_TYPED_EXTENSIBLE_ENUM;\n"
        . "typedef NSString * NSColorName NS_SWIFT_BRIDGED_TYPEDEF;\n"
        . "typedef NSString * _Nonnull NSFooKey;\n"
        . "typedef NSString * (^NSBlockReturningString)(void);\n"
        . "typedef NSString * _Nullable (^NSOtherBlock)(id);\n";

    expect(StringTypedefs::direct($src))->toBe(['NSNotificationName', 'NSColorName', 'NSFooKey']);
});

it('projects a string typedef as string only when the annotation says string', function () {
    $declaration = new SdkMethod(
        selector: 'postNotificationName:object:',
        camel: 'postNotificationNameObject',
        classMethod: false,
        returnType: 'void',
        params: [
            new SdkParam('NSNotificationName', 'aName'),
            new SdkParam('nullable id', 'anObject'),
        ],
    );
    $annotated = static fn (string $nameType): Annotation => new Annotation(
        classPath: 'NS\\NSNotificationCenter',
        method: 'postNotificationNameObject',
        params: [
            ['type' => 'int', 'name' => 'handle'],
            ['type' => $nameType, 'name' => 'name'],
            ['type' => 'int', 'name' => 'object_'],
        ],
        returnType: 'void',
        construct: false,
        header: 'src/ns-notificationcenter.h',
        line: 55,
    );
    $typedefs = ['NSNotificationName' => true];
    /** @var array<string, EnumKind> $kinds */
    $kinds = [];

    $asString = TypeJoin::join($annotated('string'), $declaration, $kinds, $typedefs);
    $asInt = TypeJoin::join($annotated('int'), $declaration, $kinds, $typedefs);
    $unresolved = TypeJoin::join($annotated('string'), $declaration, $kinds);

    expect($asString->dtoParams[0]->phpType)->toBe('string')
        ->and($asString->dtoParams[0]->objcType)->toBe('NSNotificationName')
        ->and($asString->dtoParams[1]->phpType)->toBe('int')
        ->and($asInt->dtoParams[0]->phpType)->toBe('int')
        ->and($unresolved->dtoParams[0]->phpType)->toBe('int')
        ->and(TypeJoin::isStringTypedef('nullable NSNotificationName', $typedefs))->toBeTrue()
        ->and(TypeJoin::isStringTypedef('NSNotificationName *', $typedefs))->toBeFalse();
});

it('never boxes a string typedef return', function () {
    $declaration = new SdkMethod(
        selector: 'frameAutosaveName',
        camel: 'frameAutosaveName',
        classMethod: false,
        returnType: 'NSWindowFrameAutosaveName',
        params: [],
    );
    $annotation = new Annotation(
        classPath: 'NS\\NSWindow',
        method: 'frameAutosaveName',
        params: [['type' => 'int', 'name' => 'handle']],
        returnType: 'string',
        construct: false,
        header: 'src/ns-window.h',
        line: 1,
    );

    $joined = TypeJoin::join($annotation, $declaration, [], ['NSWindowFrameAutosaveName' => true]);

    expect($joined->returnsHandle)->toBeFalse()
        ->and($joined->phpReturn)->toBe('?string');
});

it('projects NSString-backed typedef parameters and returns as strings', function (string $class, string $method, array $params, string $return) {
    expect(stringTypedefParamTypes($class, $method))->toBe($params)
        ->and(stringTypedefReturnType($class, $method))->toBe($return);
})->with([
    'NSNotificationName post' => [NSNotificationCenter::class, 'postNotificationNameObject', ['string', 'int'], 'static'],
    'NSNotificationName post + userInfo' => [NSNotificationCenter::class, 'postNotificationNameObjectUserInfo', ['string', 'int', 'array'], 'static'],
    'NSWindowFrameAutosaveName set' => [NSWindow::class, 'setFrameAutosaveName', ['string'], 'bool'],
    'NSWindowFrameAutosaveName get' => [NSWindow::class, 'frameAutosaveName', [], '?string'],
    'NSUserInterfaceItemIdentifier make' => [NSTableView::class, 'makeViewWithIdentifierOwner', ['string', 'int'], '?' . ObjCObject::class],
    'NSUserInterfaceItemIdentifier init' => [NSTableColumn::class, 'initWithIdentifier', ['string'], '?' . ObjCObject::class],
    'NSUserInterfaceItemIdentifier get' => [NSTableColumn::class, 'identifier', [], '?string'],
    'NSToolbarItemIdentifier' => [NSToolbar::class, 'insertItemWithItemIdentifierAtIndex', ['string', 'int'], 'static'],
    'CALayerContentsGravity set' => [CALayer::class, 'setContentsGravity', ['string'], 'static'],
    'CALayerContentsGravity get' => [CALayer::class, 'contentsGravity', [], '?string'],
]);

it('posts a notification by string name end to end', function () {
    $center = NSNotificationCenter::defaultCenter();
    expect($center)->toBeInstanceOf(NSNotificationCenter::class);
    assert($center instanceof NSNotificationCenter);

    $name = 'JovianAppKitStringTypedefProbe' . bin2hex(random_bytes(4));
    /** @var list<array{object: ?ObjCObject, name: string}> $seen */
    $seen = [];
    $token = Bridge::observeNotification(0, $name, static function (?ObjCObject $object, string $posted) use (&$seen): void {
        $seen[] = ['object' => $object, 'name' => $posted];
    });
    expect($token)->toBeGreaterThan(0);

    try {
        expect($center->postNotificationNameObject($name, 0))->toBe($center);
        Bridge::pump(0.05);
    } finally {
        Bridge::removeObserver($token);
    }

    expect($seen)->toHaveCount(1)
        ->and($seen[0]['name'])->toBe($name)
        ->and($seen[0]['object'])->toBeNull();
})->skip(! extension_loaded('appkit'), 'needs ext-appkit');
