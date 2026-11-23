<?php

declare(strict_types=1);

use Spora\Plugins\Office\Tests\Support\FixtureException;
use Spora\Plugins\Office\Tests\Support\PictureFixture;

/**
 * Invoke the private decoder. There is no public seam onto it, and the guard
 * it carries is the one that keeps a corrupt fixture from reaching a render
 * as a `false`.
 */
function decodePicturePayload(string $base64): mixed
{
    $method = new ReflectionMethod(PictureFixture::class, 'decode');

    return $method->invoke(null, $base64);
}

it('hands back the picture the fixture carries', function () {
    $bytes = PictureFixture::bytes();

    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(strlen($bytes))->toBeGreaterThan(0);
});

it('refuses a payload that is not base64', function () {
    expect(fn() => decodePicturePayload('this is not base64!'))
        ->toThrow(FixtureException::class, 'The picture fixture is not valid base64.');
});
