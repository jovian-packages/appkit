<?php
/*
 * proof_nsopengl_typed.php — the windowed OpenGL path on the typed surface.
 *
 * The DTO port of ext-appkit's examples/proof_nsopengl.php: an NSApplication,
 * an NSWindow, and an NSOpenGLView on a 4.1 core NSOpenGLPixelFormat, with
 * the frame drawn by php-io-extensions/opengl into the view's default
 * framebuffer, read back with glReadPixels and byte-checked, then animated
 * for ~2s through Bridge::pump.
 *
 * Every AppKit call here is a typed DTO method, which is exactly one
 * extension call: handles are boxed on the way out, passed as `$obj->handle`
 * on the way in, and NSOpenGLContextParameter is the freshly mined enum for
 * setValues:forParameter:. There is no create() and no composition.
 *
 * The OpenGL calls stay on the raw `OpenGL\*` extension classes rather than
 * jovian/ogx: that package is a sibling checkout, not a dependency of this
 * one, and requiring its autoloader by relative path would couple two jovian
 * packages that are deliberately independent. The subject of this proof is
 * the typed AppKit surface; the GL side is the same recipe either way.
 *
 * NSOpenGLPixelFormat, NSOpenGLContext and NSOpenGLView are API_DEPRECATED
 * in their entirety since 10.14 and bound anyway, under the
 * `@audit deprecated-class` exemption in ext-appkit's .okf/binding-rules.md:
 * they remain the only OS path from an NSWindow to OpenGL.
 *
 * Run: php examples/proof_nsopengl_typed.php
 * Exit codes: 0 = PROOF_NSOPENGL_TYPED_OK, 1 = failure.
 */

declare(strict_types=1);

use Jovian\Bindings\AppKit\Enums\NSApplicationActivationPolicy;
use Jovian\Bindings\AppKit\Enums\NSBackingStoreType;
use Jovian\Bindings\AppKit\Enums\NSOpenGLContextParameter;
use Jovian\Bindings\AppKit\Enums\NSWindowStyleMask;
use Jovian\Bindings\AppKit\NS\NSApplication;
use Jovian\Bindings\AppKit\NS\NSOpenGLContext;
use Jovian\Bindings\AppKit\NS\NSOpenGLPixelFormat;
use Jovian\Bindings\AppKit\NS\NSOpenGLView;
use Jovian\Bindings\AppKit\NS\NSWindow;
use Jovian\Bindings\AppKit\Runtime\Bridge;
use Jovian\Bindings\AppKit\Values\NSRect;
use OpenGL\Bridge\Bridge as GLBridge;
use OpenGL\CGL\CGL;
use OpenGL\GL\GL10\GL10;
use OpenGL\GL\GL11\GL11;
use OpenGL\GL\GL15\GL15;
use OpenGL\GL\GL20\GL20;
use OpenGL\GL\GL30\GL30;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "proof_nsopengl_typed: run composer install first\n");
    exit(1);
}
require $autoload;

/* ---- AppKit/NSOpenGL.h: pixel-format attributes have no NS_ENUM, so they
 * are inline ints with a header:line citation, exactly as ext-appkit's own
 * proof writes them. NSOpenGLContextParameter does have one, and is used as
 * the enum below. ---- */
const NSOpenGLPFADoubleBuffer = 5;               // NSOpenGL.h:62
const NSOpenGLPFAColorSize = 8;                  // NSOpenGL.h:64
const NSOpenGLPFAAlphaSize = 11;                 // NSOpenGL.h:65
const NSOpenGLPFADepthSize = 12;                 // NSOpenGL.h:66
const NSOpenGLPFAAccelerated = 73;               // NSOpenGL.h:80
const NSOpenGLPFAOpenGLProfile = 99;             // NSOpenGL.h:86
const NSOpenGLProfileVersion4_1Core = 0x4100;    // NSOpenGL.h:107

/* ---- khronos/glcorearb.h (php-io-extensions/opengl scripts/khronos) ---- */
const GL_COLOR_BUFFER_BIT = 0x00004000;          // glcorearb.h:74
const GL_TRIANGLES = 0x0004;                     // glcorearb.h:81
const GL_NO_ERROR = 0;                           // glcorearb.h:114
const GL_UNSIGNED_BYTE = 0x1401;                 // glcorearb.h:187
const GL_FLOAT = 0x1406;                         // glcorearb.h:192
const GL_RGBA = 0x1908;                          // glcorearb.h:222
const GL_RENDERER = 0x1F01;                      // glcorearb.h:231
const GL_VERSION = 0x1F02;                       // glcorearb.h:232
const GL_ARRAY_BUFFER = 0x8892;                  // glcorearb.h:606
const GL_STATIC_DRAW = 0x88E4;                   // glcorearb.h:620
const GL_FRAGMENT_SHADER = 0x8B30;               // glcorearb.h:709
const GL_VERTEX_SHADER = 0x8B31;                 // glcorearb.h:710
const GL_COMPILE_STATUS = 0x8B81;                // glcorearb.h:737
const GL_LINK_STATUS = 0x8B82;                   // glcorearb.h:738
const GL_INFO_LOG_LENGTH = 0x8B84;               // glcorearb.h:740

const VIEW_W = 480;
const VIEW_H = 320;
const ANIMATE_SECONDS = 2.0;

$allocated = [];

function fail(string $why): never
{
    fwrite(STDERR, "proof_nsopengl_typed: {$why}\n");
    fwrite(STDERR, "PROOF_NSOPENGL_TYPED_FAILED\n");
    exit(1);
}

function step(string $what): void
{
    echo "  {$what}\n";
}

function buffer(int $bytes): int
{
    global $allocated;

    $ptr = GLBridge::alloc($bytes);
    if ($ptr === 0) {
        fail("OpenGL Bridge::alloc({$bytes}) failed");
    }
    $allocated[] = $ptr;

    return $ptr;
}

function readInt(int $ptr, int $offset = 0): int
{
    return unpack('l', GLBridge::read($ptr, $offset, 4))[1];
}

function compileShader(int $stage, string $source, string $label): int
{
    $shader = GL20::glCreateShader($stage);
    if ($shader === 0) {
        fail("glCreateShader({$label}) returned 0");
    }
    GL20::glShaderSource($shader, 1, [$source], 0);
    GL20::glCompileShader($shader);

    $status = buffer(4);
    GL20::glGetShaderiv($shader, GL_COMPILE_STATUS, $status);
    if (readInt($status) === 1) {
        return $shader;
    }

    GL20::glGetShaderiv($shader, GL_INFO_LOG_LENGTH, $status);
    $len = max(1, readInt($status));
    $log = buffer($len);
    GL20::glGetShaderInfoLog($shader, $len, 0, $log);
    fail("{$label} shader did not compile:\n" . rtrim(GLBridge::read($log, 0, $len), "\0"));
}

// ---------------------------------------------------------------- main

echo "proof_nsopengl_typed — " . PHP_OS_FAMILY . ' / ' . php_uname('m') . "\n\n";

foreach (['appkit', 'opengl'] as $ext) {
    if (! extension_loaded($ext)) {
        fail("the {$ext} extension is not loaded");
    }
}

echo "1. application\n";
$app = NSApplication::sharedApplication();
if (is_null($app)) {
    fail('NSApplication::sharedApplication() returned null');
}
$app->setActivationPolicy(NSApplicationActivationPolicy::REGULAR);
$app->finishLaunching();
$app->activate();
step('NSApplication is a regular, launched app — ' . $app->className());

echo "\n2. pixel format (4.1 core, double buffered)\n";
$pixelFormat = NSOpenGLPixelFormat::initWithAttributes([
    NSOpenGLPFAOpenGLProfile, NSOpenGLProfileVersion4_1Core,
    NSOpenGLPFADoubleBuffer,
    NSOpenGLPFAAccelerated,
    NSOpenGLPFAColorSize, 24,
    NSOpenGLPFAAlphaSize, 8,
    NSOpenGLPFADepthSize, 24,
]);
if (is_null($pixelFormat)) {
    fail('NSOpenGLPixelFormat::initWithAttributes returned null for a 4.1 core format');
}
$profile = $pixelFormat->getValuesForAttributeForVirtualScreen(NSOpenGLPFAOpenGLProfile, 0);
step($pixelFormat->className() . ', virtual screens ' . $pixelFormat->numberOfVirtualScreens());
step(sprintf('getValues(NSOpenGLPFAOpenGLProfile) = 0x%04X', $profile['vals'][0]));
if ($profile['vals'][0] !== NSOpenGLProfileVersion4_1Core) {
    fail(sprintf('the pixel format is not 4.1 core (0x%04X)', $profile['vals'][0]));
}
step(sprintf('CGLPixelFormatObj = 0x%X (pointer bits, never a boxed handle)', $pixelFormat->CGLPixelFormatObj()));

echo "\n3. window + NSOpenGLView\n";
$view = NSOpenGLView::initWithFramePixelFormat(
    new NSRect(0.0, 0.0, (float) VIEW_W, (float) VIEW_H),
    $pixelFormat->handle
);
if (is_null($view)) {
    fail('NSOpenGLView::initWithFramePixelFormat returned null');
}
$window = NSWindow::initWithContentRectStyleMaskBackingDefer(
    new NSRect(200.0, 200.0, (float) VIEW_W, (float) VIEW_H),
    NSWindowStyleMask::TITLED->value
        | NSWindowStyleMask::CLOSABLE->value
        | NSWindowStyleMask::MINIATURIZABLE->value
        | NSWindowStyleMask::RESIZABLE->value,
    NSBackingStoreType::NS_BACKING_STORE_BUFFERED,
    false
);
if (is_null($window)) {
    fail('NSWindow::initWithContentRectStyleMaskBackingDefer returned null');
}
// PHP's refcount owns the handle; AppKit must not also release on close.
$window->setReleasedWhenClosed(false);
$window->setTitle('jovian/appkit + opengl — NSOpenGLView');
$window->setContentView($view->handle);
$window->makeKeyAndOrderFront(0);
Bridge::pump(0.05);
step($view->className() . ' is the content view of ' . $window->className());

echo "\n4. context\n";
$context = $view->openGLContext();
if (is_null($context)) {
    fail('NSOpenGLView::openGLContext() returned null');
}
$attached = $context->view();
if (is_null($attached) || $attached->handle !== $view->handle) {
    $context->setView($view->handle);
    Bridge::pump(0.05);
    $attached = $context->view();
}
if (is_null($attached) || $attached->handle !== $view->handle) {
    fail('the context has no view: it has no drawable to render into');
}
step($context->className() . ' is attached to ' . $attached->className());

$context->makeCurrentContext();

// The GLint array crosses as a PHP list of ints; the parameter is the enum.
$context->setValuesForParameter([1], NSOpenGLContextParameter::SWAP_INTERVAL);
$swap = $context->getValuesForParameter(NSOpenGLContextParameter::SWAP_INTERVAL);
step("swap interval set to 1 through NSOpenGLContextParameter::SWAP_INTERVAL, read back as {$swap['vals'][0]}");
if ($swap['vals'][0] !== 1) {
    fail('the swap interval did not read back as it was set');
}

if (! GLBridge::load()) {
    fail('OpenGL Bridge::load() could not open an OpenGL library');
}
$cglFromAppKit = $context->CGLContextObj();
$cglFromGL = CGL::CGLGetCurrentContext();
step(sprintf('NSOpenGLContext::CGLContextObj = 0x%X', $cglFromAppKit));
step(sprintf('CGL::CGLGetCurrentContext      = 0x%X', $cglFromGL));
if ($cglFromAppKit === 0 || $cglFromAppKit !== $cglFromGL) {
    fail('the context AppKit made current is not the one ext-opengl sees');
}

echo "\n5. ext-opengl on the window's framebuffer\n";
GLBridge::load();
$version = GLBridge::contextVersion();
step("Bridge::contextVersion() = {$version['major']}.{$version['minor']}");
if ($version['major'] < 4 || ($version['major'] === 4 && $version['minor'] < 1)) {
    fail("this proof asked for a 4.1 core context; GL reports {$version['major']}.{$version['minor']}");
}
step('GL_VERSION  = ' . GL10::glGetString(GL_VERSION));
step('GL_RENDERER = ' . GL10::glGetString(GL_RENDERER));

$vaoOut = buffer(4);
GL30::glGenVertexArrays(1, $vaoOut);
GL30::glBindVertexArray(readInt($vaoOut));

$vertices = pack('f*', 0.0, 0.8, -0.8, -0.8, 0.8, -0.8);
$vboOut = buffer(4);
GL15::glGenBuffers(1, $vboOut);
GL15::glBindBuffer(GL_ARRAY_BUFFER, readInt($vboOut));
$vertexData = buffer(strlen($vertices));
GLBridge::write($vertexData, 0, $vertices);
GL15::glBufferData(GL_ARRAY_BUFFER, strlen($vertices), $vertexData, GL_STATIC_DRAW);

$vs = compileShader(GL_VERTEX_SHADER, <<<'GLSL'
#version 150 core
in vec2 aPos;
void main()
{
    gl_Position = vec4(aPos, 0.0, 1.0);
}
GLSL, 'vertex');

$fs = compileShader(GL_FRAGMENT_SHADER, <<<'GLSL'
#version 150 core
out vec4 fragColour;
void main()
{
    fragColour = vec4(1.0, 0.5, 0.25, 1.0);
}
GLSL, 'fragment');

$program = GL20::glCreateProgram();
GL20::glAttachShader($program, $vs);
GL20::glAttachShader($program, $fs);
GL20::glBindAttribLocation($program, 0, 'aPos');
GL20::glLinkProgram($program);
$status = buffer(4);
GL20::glGetProgramiv($program, GL_LINK_STATUS, $status);
if (readInt($status) !== 1) {
    GL20::glGetProgramiv($program, GL_INFO_LOG_LENGTH, $status);
    $len = max(1, readInt($status));
    $log = buffer($len);
    GL20::glGetProgramInfoLog($program, $len, 0, $log);
    fail("program did not link:\n" . rtrim(GLBridge::read($log, 0, $len), "\0"));
}
GL20::glUseProgram($program);
GL20::glEnableVertexAttribArray(0);
GL20::glVertexAttribPointer(0, 2, GL_FLOAT, false, 0, 0);
step("program {$program} linked (vs {$vs}, fs {$fs})");

GL10::glViewport(0, 0, VIEW_W, VIEW_H);
GL10::glClearColor(0.0, 0.0, 0.0, 1.0);
GL10::glClear(GL_COLOR_BUFFER_BIT);
GL11::glDrawArrays(GL_TRIANGLES, 0, 3);
GL10::glFinish();
$err = GL10::glGetError();
if ($err !== GL_NO_ERROR) {
    fail(sprintf('glGetError() = 0x%X after the draw', $err));
}
step('cleared and drew 3 vertices into the view, glGetError() = GL_NO_ERROR');

echo "\n6. read back and byte-check (before the swap: the back buffer is the frame)\n";
$pixels = buffer(VIEW_W * VIEW_H * 4);
GL10::glReadPixels(0, 0, VIEW_W, VIEW_H, GL_RGBA, GL_UNSIGNED_BYTE, $pixels);
$centre = GLBridge::read($pixels, (intdiv(VIEW_H, 2) * VIEW_W + intdiv(VIEW_W, 2)) * 4, 4);
$corner = GLBridge::read($pixels, 0, 4);
if (is_null($centre) || is_null($corner)) {
    fail('Bridge::read of the pixel buffer returned null');
}
$c = array_values(unpack('C4', $centre));
$k = array_values(unpack('C4', $corner));
step(sprintf('centre RGBA = %d,%d,%d,%d', $c[0], $c[1], $c[2], $c[3]));
step(sprintf('corner RGBA = %d,%d,%d,%d', $k[0], $k[1], $k[2], $k[3]));
if ($c[0] < 240 || $c[1] < 100 || $c[1] > 155 || $c[2] < 48 || $c[2] > 80 || $c[3] !== 255) {
    fail('the centre pixel is not the shader colour — nothing was drawn into the window');
}
if ($k[0] !== 0 || $k[1] !== 0 || $k[2] !== 0 || $k[3] !== 255) {
    fail('the corner pixel is not the clear colour');
}
step('centre is the shader colour and the corner is the clear colour');

$context->flushBuffer();
step('flushBuffer: the frame is on screen');

echo "\n7. animate for ~" . ANIMATE_SECONDS . "s through the AppKit pump\n";
$start = microtime(true);
$frames = 0;
while (($elapsed = microtime(true) - $start) < ANIMATE_SECONDS) {
    $context->makeCurrentContext();
    $t = $elapsed / ANIMATE_SECONDS;
    GL10::glClearColor(0.10 + 0.25 * sin($t * 6.283), 0.12, 0.35 - 0.25 * sin($t * 6.283), 1.0);
    GL10::glClear(GL_COLOR_BUFFER_BIT);
    GL11::glDrawArrays(GL_TRIANGLES, 0, 3);
    $context->flushBuffer();
    Bridge::pump(0.001);
    $frames++;
}
step(sprintf('%d frames in %.2fs', $frames, microtime(true) - $start));
if ($frames < 10) {
    fail('the animation did not produce frames');
}
$err = GL10::glGetError();
if ($err !== GL_NO_ERROR) {
    fail(sprintf('glGetError() = 0x%X after the animation', $err));
}

echo "\n8. teardown\n";
foreach ($allocated as $ptr) {
    GLBridge::free($ptr);
}
NSOpenGLContext::clearCurrentContext();
if (! is_null(NSOpenGLContext::currentContext())) {
    fail('clearCurrentContext left a current context');
}
$view->clearGLContext();
$window->orderOut(0);
$window->close();
Bridge::pump(0.05);
step('context cleared, window closed');

echo "\nPROOF_NSOPENGL_TYPED_OK\n";
exit(0);
