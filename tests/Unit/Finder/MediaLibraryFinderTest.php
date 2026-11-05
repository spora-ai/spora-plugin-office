<?php

declare(strict_types=1);

use MarkdownPresentation\Files\FileFinder as PresentationFileFinder;
use MarkdownWord\Files\FileFinder as WordFileFinder;
use Spora\Plugins\Office\Finder\LibraryHalf;
use Spora\Plugins\Office\Finder\MediaLibraryFinder;
use Spora\Plugins\Office\Tests\Support\FakeMediaLibrary;
use Symfony\Component\Yaml\Yaml;

/**
 * The finder is a trust boundary: it answers with bytes for a name a document
 * wrote. These are the rules that keep the answer inside the principal that
 * asked, and the shape of the answer the two halves of the library expect.
 */
it('answers both halves of the library', function () {
    $library = new FakeMediaLibrary();

    expect(new MediaLibraryFinder($library, 7, LibraryHalf::Word))
        ->toBeInstanceOf(WordFileFinder::class)
        ->and(new MediaLibraryFinder($library, 7, LibraryHalf::Slides))
        ->toBeInstanceOf(PresentationFileFinder::class);
});

it('answers a theme manifest by the folder name', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => "name: Corporate\n"]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    expect($finder->read('corporate/theme.yml', 'theme'))->toBe("name: Corporate\n");
});

it('serves the word half a projection of the slides manifest', function () {
    $manifest = "name: Corporate\n"
        . "description: The house theme\n"
        . "author: Ops\n"
        . "dark: true\n"
        . "colors:\n"
        . "  accent1: '#4C1D95'\n";

    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => $manifest]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);
    $projected = $finder->read('corporate/theme.yml', 'theme');

    // The word half refuses a key it does not know, so the manifest it is
    // handed carries the keys it reads and nothing else.
    expect($projected)->not->toBeNull()
        ->and(Yaml::parse((string) $projected))->toBe([
            'name' => 'Corporate',
            'description' => 'The house theme',
            'author' => 'Ops',
        ]);
});

it('serves the word half the look its own theme section carries', function () {
    $manifest = "name: Corporate\n"
        . "styles:\n"
        . "  heading.1:\n"
        . "    color: '1A2B3C'\n"
        . "textStyles:\n"
        . "  brand:\n"
        . "    color: '0B5CAB'\n";

    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => $manifest]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    // The Word half's look is a section of the file and the slides half's is
    // another — the projection keeps the one this half reads.
    expect(Yaml::parse((string) $finder->read('corporate/theme.yml', 'theme')))->toBe([
        'name' => 'Corporate',
        'styles' => ['heading.1' => ['color' => '1A2B3C']],
        'textStyles' => ['brand' => ['color' => '0B5CAB']],
    ]);
});

it('serves the slides half a projection without the word half\'s look', function () {
    $manifest = "name: Corporate\n"
        . "colors:\n"
        . "  accent1: '#4C1D95'\n"
        . "styles:\n"
        . "  heading.1:\n"
        . "    color: '1A2B3C'\n";

    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => $manifest]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    // The slides half refuses a key it does not know, and `styles:` names the
    // word half's slots, which a deck of slides has no use for.
    expect(Yaml::parse((string) $finder->read('corporate/theme.yml', 'theme')))->toBe([
        'name' => 'Corporate',
        'colors' => ['accent1' => '#4C1D95'],
    ]);
});

it('keeps a key neither half reads, so the library refuses it', function () {
    $manifest = "name: Corporate\ncolour: '#4C1D95'\n";

    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => $manifest]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    // A manifest that is quietly rewritten is a theme that looks like the one
    // its author wrote and is not, so a key nobody claims is left for the
    // library to refuse with a sentence naming the whole vocabulary.
    expect(Yaml::parse((string) $finder->read('corporate/theme.yml', 'theme')))->toBe([
        'name' => 'Corporate',
        'colour' => '#4C1D95',
    ]);
});

it('serves the slides half the manifest as it is written', function () {
    $manifest = "name: Corporate\ndark: true\ncolors:\n  accent1: '#4C1D95'\n";

    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => $manifest]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    expect($finder->read('corporate/theme.yml', 'theme'))->toBe($manifest);
});

it('answers a theme named by the folder id as well as by its name', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, '3f7c1d2e-4a5b-4c6d-8e9f-0a1b2c3d4e5f', ['theme.yml' => "name: Pinned\n"]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    // The folder's id is a name too: a task can pin a theme by a value that
    // survives the operator renaming the folder.
    expect($finder->read('3f7c1d2e-4a5b-4c6d-8e9f-0a1b2c3d4e5f/theme.yml', 'theme'))
        ->toBe("name: Pinned\n");
});

it('serves a manifest it cannot read as it is, so the library complains', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'broken', ['theme.yml' => "name: [unclosed\n"]);
    $library->withTheme(7, 'list', ['theme.yml' => "- one\n- two\n"]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    // A manifest that is not a mapping, or is not YAML at all, is the
    // library's to complain about — and it complains with better words than
    // this plugin has. The raw bytes are served so that the complaint is
    // about the manifest and not about the projection.
    expect($finder->read('broken/theme.yml', 'theme'))->toBe("name: [unclosed\n")
        ->and($finder->read('list/theme.yml', 'theme'))->toBe("- one\n- two\n");
});

it('never answers for another principal', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(8, 'corporate', ['theme.yml' => "name: Other tenant\n"]);
    $library->withAsset(8, 'logo.png', 'THEIRS');

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    expect($finder->read('corporate/theme.yml', 'theme'))->toBeNull()
        ->and($finder->read('logo.png', 'picture'))->toBeNull()
        ->and($finder->themes())->toBe([])
        ->and($finder->files('corporate'))->toBe([]);
});

it('refuses a theme name that is a path', function () {
    $library = new FakeMediaLibrary();

    // A folder named `.` or `..` is a name the database allows and a
    // document must not reach: they are the directories above the one the
    // theme was written from, not folders of the principal's own.
    $library->withTheme(7, '..', ['theme.yml' => "name: Sneaky\n"]);
    $library->withTheme(7, '.', ['theme.yml' => "name: Sneaky\n"]);
    $library->withTheme(7, 'corporate', ['theme.yml' => "name: Corporate\n"]);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    expect($finder->read('../theme.yml', 'theme'))->toBeNull()
        ->and($finder->read('./theme.yml', 'theme'))->toBeNull()
        ->and($finder->files('..'))->toBe([])
        ->and($finder->files('.'))->toBe([])
        // The name is refused before the library is asked about it, so a
        // path-shaped name never reaches the storage at all.
        ->and($library->askedThemes())->toBe([]);
});

it('answers a picture and a document by the asset filename', function () {
    $library = new FakeMediaLibrary();
    $library->withAsset(7, 'logo.png', 'THE PICTURE');
    $library->withAsset(7, 'report.dotx', 'THE TEMPLATE');

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    expect($finder->read('logo.png', 'picture'))->toBe('THE PICTURE')
        ->and($finder->read('report.dotx', 'document'))->toBe('THE TEMPLATE')
        // The slides half asks for its templates file with no role at all.
        ->and($finder->read('slides.md'))->toBeNull();
});

it('answers null for a file that is not there', function () {
    $library = new FakeMediaLibrary();
    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    expect($finder->read('nothing/theme.yml', 'theme'))->toBeNull()
        ->and($finder->read('nothing.png', 'picture'))->toBeNull()
        ->and($finder->read('nothing', 'document'))->toBeNull()
        ->and($finder->files('nothing'))->toBe([]);
});

it('answers null for a name no role resolves', function () {
    $library = new FakeMediaLibrary();
    $library->withAsset(7, 'logo.png', 'THE PICTURE');

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    // A role the finder does not know is a world it was not given.
    expect($finder->read('logo.png', 'something-else'))->toBeNull()
        // An asset is named by its filename, and a filename has no separator
        // in it.
        ->and($finder->read('images/logo.png', 'picture'))->toBeNull();
});

it('never answers a path', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => "name: Corporate\n"]);
    $library->withAsset(7, 'logo.png', 'THE PICTURE');

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Word);

    // The media library holds bytes and no files, so the library stages what
    // it cannot be handed as bytes into a file of its own.
    expect($finder->path('corporate/theme.yml', 'theme'))->toBeNull()
        ->and($finder->path('logo.png', 'picture'))->toBeNull();
});

it('lists the themes of the principal and the files of a theme', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'corporate', ['theme.yml' => "name: Corporate\n", 'styles.yml' => "paragraph: {}\n"]);
    // A folder without a manifest is not a theme.
    $library->withTheme(7, 'not-a-theme', ['notes.txt' => 'hello']);

    $finder = new MediaLibraryFinder($library, 7, LibraryHalf::Slides);

    expect($finder->themes())->toBe(['corporate'])
        ->and($finder->files('corporate'))->toBe(['styles.yml', 'theme.yml']);
});
