<?php

declare(strict_types=1);

use MarkdownPresentation\Document\Settings;
use MarkdownPresentation\Render\Free\ShapeKind;
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Theme\LayoutRole;
use MarkdownPresentation\Theme\TextStyle;
use MarkdownWord\Configuration\TextStyles;
use MarkdownWord\Render\Directive\DocxOnly;
use MarkdownWord\Render\Directives;
use MarkdownWord\Theme\Theme;
use Spora\Plugins\Office\OfficePlugin;
use Spora\Skills\SkillProviderInterface;

/**
 * The reference is the vocabulary, and the vocabulary is the library's.
 *
 * The library draws a deck from a closed set of names and refuses anything
 * outside it, with a message naming what was wrong. A reference that lists a
 * name the library does not have is worse than no reference: it sends the
 * agent to write a directive that fails. So the lists in `reference.md` are
 * checked here against the constants the library itself exposes, and a
 * vocabulary that moves fails the suite rather than the render.
 */
function officeReferenceText(string $skill = 'office-presentations'): string
{
    $paths = (new OfficePlugin())->skillPaths();
    $path  = $paths[0] . '/' . $skill . '/reference.md';

    if (! is_file($path)) {
        return '';
    }

    return (string) file_get_contents($path);
}

/**
 * The names a table row of the reference mentions, in order.
 *
 * @return list<string>
 */
function officeReferenceNames(string $text, string $pattern): array
{
    preg_match_all($pattern, $text, $matches);

    return $matches[1] ?? [];
}

it('ships a reference the agent can read', function () {
    $paths = (new OfficePlugin())->skillPaths();
    $path  = $paths[0] . '/office-presentations/reference.md';

    expect(is_file($path))->toBeTrue();

    // Core reads a skill's sidecars through a provider, and the provider
    // refuses a file past this cap — so a reference the agent cannot read is
    // a reference that does not exist.
    expect(filesize($path))->toBeLessThanOrEqual(SkillProviderInterface::MAX_FILE_BYTES);
});

it('lists the reference beside the skill, so the agent can find it', function (string $skill) {
    $scanner = new Spora\Skills\SkillScanner(
        array_map(
            static fn(string $path): array => ['path' => $path, 'source' => 'office'],
            (new OfficePlugin())->skillPaths(),
        ),
    );

    $files = [];

    foreach ($scanner->scan() as $scanned) {
        if ($scanned->name() !== $skill) {
            continue;
        }

        foreach ($scanned->files() as $file) {
            $files[] = $file['path'];
        }
    }

    expect($files)->toContain('SKILL.md')
        ->and($files)->toContain('examples.md')
        ->and($files)->toContain('reference.md');
})->with(['office-documents', 'office-presentations']);

it('names every frontmatter key the renderer reads', function () {
    $text = officeReferenceText();

    foreach (PresentationRenderer::DIRECTIVES as $key) {
        expect($text)->toContain('`' . $key . '`');
    }
});

it('names every deck-level key the renderer reads', function () {
    $text = officeReferenceText();

    foreach (PresentationRenderer::DECK_DIRECTIVES as $key) {
        expect($text)->toContain('`' . $key . '`');
    }
});

it('names every free shape the renderer draws', function () {
    $text = officeReferenceText();

    foreach (ShapeKind::cases() as $kind) {
        expect($text)->toContain('::' . $kind->value);
    }
});

it('names every setting a slide may carry', function () {
    $text = officeReferenceText();

    foreach (Settings::KEYS as $key) {
        expect($text)->toContain('`' . $key . '`');
    }
});

it('names every layout role and the Office layout it maps to', function () {
    $text = officeReferenceText();

    foreach (LayoutRole::defaults() as $role => $office) {
        expect($text)->toContain('`' . $role . '`')
            ->and($text)->toContain('"' . $office . '"');
    }
});

it('names every attribute a named text style takes', function () {
    $text = officeReferenceText();

    foreach (TextStyle::ATTRIBUTES as $attribute) {
        expect($text)->toContain('`' . $attribute . '`');
    }
});

it('names the attribute the slides half refuses', function () {
    // The Word half draws a highlight and this one does not, so the refusal
    // is by name — an attribute quietly dropped is an attribute the author
    // thinks was applied.
    expect(officeReferenceText())->toContain('`' . TextStyle::UNSUPPORTED . '`');
});

it('names every directive the Word half draws', function () {
    $text = officeReferenceText('office-documents');

    $directives = (new ReflectionClass(Directives::class))->getConstants();

    foreach ($directives as $name) {
        if (! is_string($name) || $name === '') {
            continue;
        }

        // The footnote is an inline directive and is written with one colon.
        $written = $name === 'footnote' ? ':' . $name : '::' . $name;

        expect($text)->toContain($written);
    }
});

it('names every shape a docx can draw', function () {
    $text = officeReferenceText('office-documents');

    $constants = (new ReflectionClass(DocxOnly::class))->getConstants();

    foreach ($constants['SHAPES'] ?? [] as $shape) {
        expect($text)->toContain('`' . $shape . '`');
    }
});

it('names every kind of form field', function () {
    $text = officeReferenceText('office-documents');

    $constants = (new ReflectionClass(DocxOnly::class))->getConstants();

    foreach ($constants['FIELDS'] ?? [] as $field) {
        expect($text)->toContain('`' . $field . '`');
    }
});

it('names every attribute a Word text style takes', function () {
    $text = officeReferenceText('office-documents');

    foreach (array_keys(TextStyles::ATTRIBUTES) as $attribute) {
        expect($text)->toContain('`' . $attribute . '`');
    }
});

it('names every section a theme file may carry', function () {
    $text = officeReferenceText('office-documents');

    foreach (Theme::KEYS as $key) {
        expect($text)->toContain('`' . $key . '`');
    }
});

it('ships a document reference the agent can read', function () {
    $paths = (new OfficePlugin())->skillPaths();
    $path  = $paths[0] . '/office-documents/reference.md';

    expect(is_file($path))->toBeTrue()
        ->and(filesize($path))->toBeLessThanOrEqual(SkillProviderInterface::MAX_FILE_BYTES);
});
