<?php

declare(strict_types=1);

use Spora\Plugins\Office\OfficePlugin;

/**
 * The text of a skill and of the README.
 *
 * Both are read by a model or by an operator, so a claim in them is a
 * promise the plugin makes. The assertions below pin the claims a change in
 * behaviour would make false — the phrasing that has already been wrong once
 * must not come back.
 */
function officeSkillText(string $name): string
{
    $paths = (new OfficePlugin())->skillPaths();

    return (string) file_get_contents($paths[0] . '/' . $name . '/SKILL.md');
}

function officeReadmeText(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2) . '/README.md');
}

it('does not claim that a deck read back carries its pictures', function () {
    $skill  = officeSkillText('office-presentations');
    $readme = officeReadmeText();

    // The reverse direction names no media directory, so the library leaves
    // the picture out of the extracted deck: no reference to a file, and no
    // alt text standing in for it.
    expect($skill)->not->toContain('the pictures — as a Markdown deck')
        ->and($skill)->not->toContain('a reference to a file the extraction wrote')
        ->and($skill)->toContain('no pictures')
        ->and($readme)->not->toContain('their order and the pictures')
        ->and($readme)->toContain('The pictures do not travel');
});

it('tells the operator that the old package has to be removed', function () {
    $readme = officeReadmeText();

    // Two plugins claiming the same source MIME and format split the
    // derivative's natural key, so the loser of the race mints a duplicate.
    expect($readme)->toContain('composer remove spora-ai/spora-plugin-word')
        ->and($readme)->toContain('The two cannot both be installed');
});

it('says what a picture already inside a document comes back as', function () {
    $skill = officeSkillText('office-documents');

    // The Word half keeps the reference and the alt text; the file behind it
    // is never written.
    expect($skill)->toContain('a file that was never written')
        ->and($skill)->toContain('media/section_image1.png');
});

it('says the folder tool needs a core that carries the media-folders feature', function (string $name) {
    $skill = officeSkillText($name);

    // The tool and the table it reads arrive with core migration `0097`,
    // which no tagged core carries yet.
    expect($skill)->toContain('list_folders')
        ->and($skill)->toContain('media-folders feature')
        ->and($skill)->toContain('0097');
})->with(['office-documents', 'office-presentations']);

it('says media themes need the core that carries the folders', function () {
    $readme = officeReadmeText();

    expect($readme)->toContain('0097')
        ->and($readme)->toContain('v0.33.0');
});

it('says a theme is one file', function (string $name) {
    $skill = officeSkillText($name);

    // The old format's files are refused beside a theme's look, so a skill
    // describing the old layout would build a folder that no longer renders.
    expect($skill)->toContain('A theme is one file');
})->with(['office-documents', 'office-presentations']);

it('names the licence of the package underneath the library', function (string $name) {
    $skill = officeSkillText($name);

    // Both halves write through a PHPOffice package and both are LGPL-3.0; the
    // plugin and the library are MIT, so a claim about the package underneath
    // is the one that would be wrong.
    expect($skill)->toContain('LGPL-3.0');
})->with(['office-documents', 'office-presentations']);

it('lists the deck frontmatter keys the renderer reads', function () {
    $skill = officeSkillText('office-presentations');

    // The list is closed: a key missing from it is one the agent is told not
    // to write.
    expect($skill)->toContain('`deck-settings`')
        ->and($skill)->toContain('`template-settings`')
        ->and($skill)->toContain('`background-gradient`')
        ->and($skill)->toContain('`theme-variables`');
});

it('does not describe the quote loss as an indentation again', function () {
    // A quote's own line breaks are joined into one line — that is the loss.
    // "A quote written as plain indentation" was never it: four spaces of
    // indentation is a code block in Markdown.
    expect(officeSkillText('office-documents'))->not->toContain('a quote written as plain indentation')
        ->and(officeReadmeText())->not->toContain('a quote written as plain indentation');
});

it('counts the round-trip losses the same way in the README and in the skill', function () {
    // The named-style loss joined the five, so there are six. A count that
    // drifts between the two documents is a count a reader cannot rely on.
    // The whitespace is folded, because a line break in the middle of the
    // count is not a different count.
    $readme = (string) preg_replace('/\s+/', ' ', officeReadmeText());
    $skill  = (string) preg_replace('/\s+/', ' ', officeSkillText('office-documents'));

    expect($readme)->not->toContain('five documented round-trip losses')
        ->and($readme)->toContain('Six documented losses')
        ->and($skill)->toContain('Six losses');
});

it('tells the operator to rebuild the patch lock after an upgrade', function () {
    $readme = officeReadmeText();

    // The patches that are applied are the ones in the host's own lock, and a
    // lock written before the PHPWord patch names no PHPWord patch: the Word
    // half then writes a document that is not valid Office Open XML, and it
    // says nothing while it does.
    expect($readme)->toContain('composer patches-relock')
        ->and($readme)->toContain('composer patches-repatch');
});

it('does not claim that a derivative render needs an approval', function (string $name) {
    $skill = officeSkillText($name);

    // `create_derivative` is auto-approved by default: it writes a row derived
    // from a parent the agent already owns. `create_media` is the one that
    // asks the operator, because it has no natural key to make a retry safe.
    expect($skill)->not->toContain('Both `create_media` and `create_derivative` require operator approval')
        ->and($skill)->toMatch('/`create_derivative`\s+is auto-approved/');
})->with(['office-documents', 'office-presentations']);

it('does not claim that reading a document back is approval-gated', function () {
    $skill = officeSkillText('office-documents');

    // `get_source` is auto-approved by default. The 8 KB that was claimed for
    // it is the `get_media` preview cap; `get_source` inlines the whole
    // extracted text up to 64 KB.
    expect($skill)->not->toContain('always approval-gated')
        ->and($skill)->not->toContain('truncated to 8 KB')
        ->and($skill)->toContain('truncated to 64 KB');
});

it('does not document a download card core does not produce', function (string $name) {
    $skill = officeSkillText($name);

    // Core's card is Tailwind utilities with a glyph class and a span holding
    // the file size. The class names below were never emitted by any core
    // version, and the MIME type is not on the card at all.
    expect($skill)->not->toContain('spora-file-card__link')
        ->and($skill)->not->toContain('spora-file-card__name')
        ->and($skill)->not->toContain('spora-file-card__meta');
})->with(['office-documents', 'office-presentations']);

it('documents the download card core produces', function () {
    $skill = officeSkillText('office-documents');

    expect($skill)->toContain('spora-file-card__glyph')
        ->and($skill)->toContain('The card carries the filename and the size, and nothing else');
});

it('does not name a document theme as a deck theme', function () {
    $skill = officeSkillText('office-presentations');

    // The two halves ship their own built-ins. `corporate` is one of the
    // Word half's four, so a deck naming it fails with a message listing the
    // five the slides half has.
    expect($skill)->not->toMatch('/theme: corporate(?!-)/')
        ->and($skill)->not->toMatch('/"corporate"(?![\w-])/')
        ->and($skill)->toContain('`corporate` is a document theme, not a deck theme');
});

it('does not describe a column marker as the only way to have two columns', function () {
    $skill = officeSkillText('office-presentations');

    // The layout gives the slide its columns; the marker moves the blocks
    // between them. A two-column slide that writes no marker fills the first
    // column and leaves the rest empty.
    expect($skill)->not->toContain('the only way to have two columns')
        ->and($skill)->toContain('fills the first column and leaves the rest empty');
});

it('names the deck-level frontmatter keys the renderer reads', function () {
    $skill = officeSkillText('office-presentations');

    // `templates-file` belongs to the deck: on any slide but the first it is
    // a failure that says so, exactly as `theme` is.
    expect($skill)->toContain('`theme`, `author`, `description`, `templates-file` and `deck-settings`');
});
