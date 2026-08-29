<?php

declare(strict_types=1);

use Jovian\Bindings\AppKit\Generator\Annotation;
use Jovian\Bindings\AppKit\Generator\EnumKind;
use Jovian\Bindings\AppKit\Generator\SdkMethod;
use Jovian\Bindings\AppKit\Generator\SdkParam;
use Jovian\Bindings\AppKit\Generator\TypeJoin;
use Jovian\Bindings\AppKit\Generator\TypeName;

it('joins setTitleVisibility positionally and recovers the NS_ENUM type', function () {
    $annotation = new Annotation(
        classPath: 'NS\\NSWindow',
        method: 'setTitleVisibility',
        params: [
            ['type' => 'int', 'name' => 'handle'],
            ['type' => 'int', 'name' => 'titleVisibility'],
        ],
        returnType: 'void',
        construct: false,
        header: 'src/ns-window.h',
        line: 1,
    );
    $declaration = new SdkMethod(
        selector: 'setTitleVisibility:',
        camel: 'setTitleVisibility',
        classMethod: false,
        returnType: 'void',
        params: [new SdkParam('NSWindowTitleVisibility', 'titleVisibility')],
    );

    $joined = TypeJoin::join($annotation, $declaration, [
        'NSWindowTitleVisibility' => EnumKind::ENUM,
        'NSWindowStyleMask' => EnumKind::OPTIONS,
    ]);

    expect($joined->dtoParams)->toHaveCount(1)
        ->and($joined->dtoParams[0]->objcType)->toBe('NSWindowTitleVisibility')
        ->and($joined->dtoParams[0]->phpType)->toBe('NSWindowTitleVisibility|int')
        ->and($joined->paramTypeNames())->toBe(['NSWindowTitleVisibility']);
});

it('keeps NS_OPTIONS parameters as int so values can be OR\'d', function () {
    $annotation = new Annotation(
        classPath: 'NS\\NSWindow',
        method: 'setStyleMask',
        params: [
            ['type' => 'int', 'name' => 'handle'],
            ['type' => 'int', 'name' => 'styleMask'],
        ],
        returnType: 'void',
        construct: false,
        header: 'src/ns-window.h',
        line: 1,
    );
    $declaration = new SdkMethod(
        selector: 'setStyleMask:',
        camel: 'setStyleMask',
        classMethod: false,
        returnType: 'void',
        params: [new SdkParam('NSWindowStyleMask', 'styleMask')],
    );

    $joined = TypeJoin::join($annotation, $declaration, [
        'NSWindowStyleMask' => EnumKind::OPTIONS,
    ]);

    expect($joined->dtoParams[0]->phpType)->toBe('int')
        ->and($joined->paramTypeNames())->toBe(['NSWindowStyleMask']);
});

it('collapses an NSRect ABI split into one DTO argument', function () {
    $annotation = new Annotation(
        classPath: 'NS\\NSWindow',
        method: 'setFrameDisplay',
        params: [
            ['type' => 'int', 'name' => 'handle'],
            ['type' => 'double', 'name' => 'x'],
            ['type' => 'double', 'name' => 'y'],
            ['type' => 'double', 'name' => 'width'],
            ['type' => 'double', 'name' => 'height'],
            ['type' => 'bool', 'name' => 'flag'],
        ],
        returnType: 'void',
        construct: false,
        header: 'src/ns-window.h',
        line: 1,
    );
    $declaration = new SdkMethod(
        selector: 'setFrame:display:',
        camel: 'setFrameDisplay',
        classMethod: false,
        returnType: 'void',
        params: [
            new SdkParam('NSRect', 'frame'),
            new SdkParam('BOOL', 'flag'),
        ],
    );

    $joined = TypeJoin::join($annotation, $declaration, []);

    expect($joined->dtoParams)->toHaveCount(2)
        ->and($joined->dtoParams[0]->phpType)->toBe('NSRect')
        ->and($joined->dtoParams[0]->objcType)->toBe('NSRect')
        ->and($joined->dtoParams[1]->phpType)->toBe('bool')
        ->and($joined->paramTypeNames())->toBe(['NSRect', 'BOOL']);
});

it('keeps NSArray of object pointers as an in-parameter', function () {
    $annotation = new Annotation(
        classPath: 'NS\\NSButton',
        method: 'compressWithPrioritizedCompressionOptions',
        params: [
            ['type' => 'int', 'name' => 'handle'],
            ['type' => 'array', 'name' => 'prioritizedOptions'],
        ],
        returnType: 'void',
        construct: false,
        header: 'src/ns-button.h',
        line: 1,
    );
    $declaration = new SdkMethod(
        selector: 'compressWithPrioritizedCompressionOptions:',
        camel: 'compressWithPrioritizedCompressionOptions',
        classMethod: false,
        returnType: 'void',
        params: [new SdkParam(
            'NSArray<NSUserInterfaceCompressionOptions *> *',
            'prioritizedOptions',
            TypeName::isOutPointer('NSArray<NSUserInterfaceCompressionOptions *> *'),
        )],
    );

    $joined = TypeJoin::join($annotation, $declaration, []);

    expect($joined->dtoParams)->toHaveCount(1)
        ->and($joined->dtoParams[0]->phpType)->toBe('array')
        ->and($joined->paramTypeNames())->toBe(['NSArray']);
});

it('does not box NSInteger tag, NSWindowLevel, or NSControlStateValue returns', function () {
    $tag = TypeJoin::join(
        instanceAnnotation('NS\\NSView', 'tag', 'int'),
        new SdkMethod('tag', 'tag', false, 'NSInteger', []),
        [],
    );
    $level = TypeJoin::join(
        instanceAnnotation('NS\\NSWindow', 'level', 'int'),
        new SdkMethod('level', 'level', false, 'NSWindowLevel', []),
        [],
    );
    $state = TypeJoin::join(
        instanceAnnotation('NS\\NSSwitch', 'state', 'int'),
        new SdkMethod('state', 'state', false, 'NSControlStateValue', []),
        ['NSControlStateValue' => EnumKind::ENUM],
    );

    expect($tag->returnsHandle)->toBeFalse()
        ->and($tag->phpReturn)->toBe('int')
        ->and($level->returnsHandle)->toBeFalse()
        ->and($level->phpReturn)->toBe('int')
        ->and($state->returnsHandle)->toBeFalse()
        ->and($state->phpReturn)->toBe('NSControlStateValue|int');
});

it('boxes id, instancetype, and a single star over an ObjC class', function () {
    $view = TypeJoin::join(
        instanceAnnotation('NS\\NSWindow', 'contentView', 'int'),
        new SdkMethod('contentView', 'contentView', false, 'NSView *', []),
        [],
    );
    $id = TypeJoin::join(
        instanceAnnotation('NS\\NSWindow', 'delegate', 'int'),
        new SdkMethod('delegate', 'delegate', false, 'id<NSWindowDelegate>', []),
        [],
    );
    $self = TypeJoin::join(
        instanceAnnotation('NS\\NSView', 'init', 'int'),
        new SdkMethod('init', 'init', false, 'instancetype', []),
        [],
    );

    expect($view->returnsHandle)->toBeTrue()
        ->and($view->phpReturn)->toBe('?ObjCObject')
        ->and($id->returnsHandle)->toBeTrue()
        ->and($id->phpReturn)->toBe('?ObjCObject')
        ->and($self->returnsHandle)->toBeTrue()
        ->and($self->phpReturn)->toBe('?ObjCObject');
});

it('types an NS_ENUM return before considering a handle', function () {
    $joined = TypeJoin::join(
        instanceAnnotation('NS\\NSWindow', 'titleVisibility', 'int'),
        new SdkMethod('titleVisibility', 'titleVisibility', false, 'NSWindowTitleVisibility', []),
        ['NSWindowTitleVisibility' => EnumKind::ENUM],
    );

    expect($joined->returnsHandle)->toBeFalse()
        ->and($joined->phpReturn)->toBe('NSWindowTitleVisibility|int');
});

it('does not flag an NSInteger parameter as a handle', function () {
    $joined = TypeJoin::join(
        instanceAnnotation('NS\\NSView', 'setTag', 'void', [
            ['type' => 'int', 'name' => 'tag'],
        ]),
        new SdkMethod('setTag:', 'setTag', false, 'void', [
            new SdkParam('NSInteger', 'tag'),
        ]),
        [],
    );

    expect($joined->dtoParams[0]->handle)->toBeFalse()
        ->and($joined->dtoParams[0]->phpType)->toBe('int')
        ->and($joined->dtoParams[0]->objcType)->toBe('NSInteger');
});

it('flags an NSView * parameter as a handle and keeps the PHP type int', function () {
    $joined = TypeJoin::join(
        instanceAnnotation('NS\\NSWindow', 'setContentView', 'void', [
            ['type' => 'int', 'name' => 'contentView'],
        ]),
        new SdkMethod('setContentView:', 'setContentView', false, 'void', [
            new SdkParam('NSView *', 'contentView'),
        ]),
        [],
    );

    expect($joined->dtoParams[0]->handle)->toBeTrue()
        ->and($joined->dtoParams[0]->phpType)->toBe('int')
        ->and($joined->dtoParams[0]->objcType)->toBe('NSView');
});

it('does not consult JoinedParam::$handle when emitting a scalar call', function () {
    $src = file_get_contents(dirname(__DIR__, 2) . '/scripts/Generator/Emitter.php');

    expect($src)->not->toContain('$param->handle')
        ->and($src)->toContain('$method->returnsHandle');
});

function instanceAnnotation(string $classPath, string $method, string $returnType, array $payload = []): Annotation
{
    return new Annotation(
        classPath: $classPath,
        method: $method,
        params: [
            ['type' => 'int', 'name' => 'handle'],
            ...$payload,
        ],
        returnType: $returnType,
        construct: false,
        header: 'src/test.h',
        line: 1,
    );
}
