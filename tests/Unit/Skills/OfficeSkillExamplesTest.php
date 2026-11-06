<?php

declare(strict_types=1);

use Spora\Plugins\Office\OfficePlugin;
use Spora\Plugins\Office\Tests\Support\OfficeMediaArchive;

/**
 * The skill's examples are executable documentation.
 *
 * An example that does not render is worse than no example: the agent copies
 * it, the render fails, and the failure message is about the deck rather than
 * about the example. Every `markdown` block the skills show is therefore
 * rendered here, through the same producer the plugin calls.
 *
 * The blocks are rendered **as written**, with no theme passed as an option.
 * An option would override the frontmatter, and the frontmatter is exactly
 * what a broken example gets wrong: a deck naming `corporate` — a document
 * theme — renders nothing the slides half has.
 */
function officeSkillMarkdownBlocks(string $skill, string $file = 'SKILL.md'): array
{
    $paths = (new OfficePlugin())->skillPaths();
    $path  = $paths[0] . '/' . $skill . '/' . $file;

    if (! is_file($path)) {
        return [];
    }

    preg_match_all('/^```markdown\n(.*?)^```$/ms', (string) file_get_contents($path), $matches);

    return $matches[1];
}

it('renders every markdown block the document skill shows', function (string $markdown) {
    $producer = OfficeMediaArchive::producer(OfficeMediaArchive::container());

    $output = $producer->produce(
        OfficeMediaArchive::dataUrlAsset($markdown),
        'docx',
    );

    // `PK` is the zip local-file header: proof the renderer produced an
    // archive rather than a string that merely claims to be one.
    expect(substr($output->bytes, 0, 2))->toBe('PK')
        ->and(strlen($output->bytes))->toBeGreaterThan(1000);
})->with(officeSkillMarkdownBlocks('office-documents'));

it('renders every markdown block the presentation skill shows', function (string $markdown) {
    $producer = OfficeMediaArchive::deckProducer(OfficeMediaArchive::container());

    $output = $producer->produce(
        OfficeMediaArchive::dataUrlAsset($markdown),
        'pptx',
    );

    expect(substr($output->bytes, 0, 2))->toBe('PK')
        ->and(strlen($output->bytes))->toBeGreaterThan(1000);
})->with(officeSkillMarkdownBlocks('office-presentations'));

it('renders every markdown block the presentation examples show', function (string $markdown) {
    $producer = OfficeMediaArchive::deckProducer(OfficeMediaArchive::container());

    $output = $producer->produce(
        OfficeMediaArchive::dataUrlAsset($markdown),
        'pptx',
    );

    expect(substr($output->bytes, 0, 2))->toBe('PK')
        ->and(strlen($output->bytes))->toBeGreaterThan(1000);
})->with(officeSkillMarkdownBlocks('office-presentations', 'examples.md'));

it('ships at least one deck example', function () {
    expect(officeSkillMarkdownBlocks('office-presentations', 'examples.md'))->not->toBe([]);
});
