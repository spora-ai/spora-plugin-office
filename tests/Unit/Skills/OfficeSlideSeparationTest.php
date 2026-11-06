<?php

declare(strict_types=1);

use MarkdownPresentation\Document\DeckParser;
use Spora\Plugins\Office\OfficePlugin;

/**
 * A slide's frontmatter and its separator are the same `---`.
 *
 * The rule is easy to get wrong, because the two look like different things:
 * a separator is a line of dashes between slides, and a frontmatter block is a
 * block of keys between dashes. Writing both — a separator, and then a block
 * that opens with its own dashes — leaves a slide with nothing in it between
 * the two, and the deck renders with a blank in the middle.
 *
 * The skill says so, and the example it gives has to keep saying it: an
 * example that drifts back into the broken form would teach the mistake.
 */
function officeDeck(string $markdown): array
{
    return (new DeckParser())->parse($markdown)->slides();
}

/**
 * The two decks the skill's "Separating slides" section shows: the one that
 * is right and the one that leaves an empty slide.
 *
 * They are picked by how many dashes they carry rather than by their place in
 * the section — the right one has two, the wrong one has three — so that an
 * example added beside them does not move the pair.
 *
 * @return array{right: string, wrong: string}
 */
function officeSeparationExamples(): array
{
    $paths = (new OfficePlugin())->skillPaths();
    $path  = $paths[0] . '/office-presentations/SKILL.md';

    $text = (string) file_get_contents($path);

    preg_match(
        '/### Separating slides(.*?)^## /ms',
        $text,
        $section,
    );

    preg_match_all('/^```markdown\n(.*?)^```$/ms', $section[1] ?? '', $blocks);

    $right = '';
    $wrong = '';

    foreach ($blocks[1] as $block) {
        $dashes = preg_match_all('/^---$/m', $block);

        if ($dashes === 2) {
            $right = $block;
        }

        if ($dashes === 3) {
            $wrong = $block;
        }
    }

    return ['right' => $right, 'wrong' => $wrong];
}

it('shows a slide with its own frontmatter as one separator, not two', function () {
    $slides = officeDeck(officeSeparationExamples()['right']);

    expect($slides)->toHaveCount(2)
        ->and($slides[0]->title())->toBe('Slide A')
        ->and($slides[1]->title())->toBe('Slide B')
        ->and($slides[1]->frontmatter())->toBe(['layout' => 'section']);
});

it('leaves an empty slide when the separator is written twice', function () {
    // The failure the rule exists for, pinned so the wording cannot be
    // softened into something that reads as permission.
    $slides = officeDeck(officeSeparationExamples()['wrong']);

    expect($slides)->toHaveCount(3)
        ->and($slides[0]->title())->toBe('Slide A')
        ->and($slides[1]->content())->toBe('')
        ->and($slides[1]->title())->toBeNull()
        ->and($slides[2]->title())->toBe('Slide B');
});

it('tells the agent that the frontmatter carries its own separator', function () {
    $paths = (new OfficePlugin())->skillPaths();
    $skill = (string) file_get_contents($paths[0] . '/office-presentations/SKILL.md');

    expect($skill)->toContain('The `---` that opens a slide\'s frontmatter is that slide\'s separator')
        ->and($skill)->toContain('empty slide between the two');
});
