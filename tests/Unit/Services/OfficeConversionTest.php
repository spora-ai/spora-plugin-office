<?php

declare(strict_types=1);

use MarkdownWord\Exception\UnreadableDocument;
use Spora\Plugins\Office\Exceptions\OfficeDocumentException;
use Spora\Plugins\Office\Services\OfficeConversion;
use Spora\Plugins\Office\Tests\Support\DeckFixtures;
use Spora\Plugins\Office\Tests\Support\DocxFixtures;
use Spora\Plugins\Office\Tests\Support\ErrorHandlerStack;
use Spora\Plugins\Office\Tests\Support\ErrorRecorder;
use Spora\Plugins\Office\Tests\Support\FakeMediaLibrary;
use Spora\Plugins\Office\Tests\Support\FixtureException;
use Spora\Plugins\Office\Tests\Support\PictureFixture;

/**
 * Raise one genuine engine-level `E_DEPRECATED` attributed to *this* file.
 *
 * PHP 8.4 deprecated implicitly-nullable parameter declarations, so `eval`ing
 * the snippet compiles a real deprecation whose `$errfile` is the calling
 * file. That is the only way to exercise the suppressor's `$errfile` conjunct:
 * `E_USER_*` cannot, because the handler's `E_DEPRECATED` mask never routes
 * those levels to it at all — a test using them would pass whether or not the
 * suppressor existed. Writing the same declaration into a file and including
 * it does not work either: the deprecation is a *compile-time* one, so it
 * fires before the handler is installed, and the same file cannot be included
 * twice without a redeclaration fatal.
 *
 * `$seq` is part of the function name so every call declares a fresh symbol
 * instead of fataling on a redeclaration.
 */
function raiseEngineDeprecation(int $seq): void
{
    $function = 'spora_word_deprecation_probe_' . $seq;
    // NOSONAR php:S1523 — dynamic execution is the test, not a way in: the
    // string is an int concatenated onto a fixed prefix, never input, and the
    // snippet is the only way to compile a deprecation under an installed
    // handler (see the docblock).
    eval("function {$function}(string \$value = null): string { return (string) \$value; }");
    $function();
}

/**
 * Invoke the private deprecation-suppressing wrapper. It is the plugin's
 * whole defence against PHPWord's notice flood and there is no public seam
 * onto it; since PHP 8.1 reflection reaches private methods without help.
 */
function invokeSuppressed(OfficeConversion $conversion, callable $callback): mixed
{
    $method = new ReflectionMethod(OfficeConversion::class, 'withoutUpstreamPhpWordNotices');

    return $method->invoke($conversion, $callback(...));
}

it('renders markdown into docx bytes', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $docx = $conversion->markdownToDocx(DocxFixtures::SAMPLE_MARKDOWN);

    expect(substr($docx, 0, 2))->toBe('PK');
    expect(strlen($docx))->toBeGreaterThan(1000);
});

it('reads a rendered document back as github-flavoured markdown', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->officeToMarkdown($conversion->markdownToDocx(DocxFixtures::SAMPLE_MARKDOWN));

    expect($markdown)->toContain('# Quarterly report');
    expect($markdown)->toContain('**12%**');
    // The round trip's documented header-row rewrite: the column alignment
    // survives, the emphasis does not.
    expect($markdown)->toContain('| :-- | --: |');
});

it('drops decoration when plain is requested', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $decorated = DocxFixtures::partOf($conversion->markdownToDocx(DocxFixtures::SAMPLE_MARKDOWN), DocxFixtures::STYLES_PART);
    $plain     = DocxFixtures::partOf($conversion->markdownToDocx(DocxFixtures::SAMPLE_MARKDOWN, plain: true), DocxFixtures::STYLES_PART);

    expect($decorated)->toContain('IntenseQuote');
    expect($plain)->not->toContain('IntenseQuote');
});

/**
 * The round trip's documented losses, one assertion each.
 *
 * The skill states these as a table, and a table nobody checks rots. Each
 * case is a whole round trip, so the assertion is about what the library
 * actually does rather than about the fixture.
 */
it('loses what the skill says a round trip loses', function (string $written, string $read) {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->officeToMarkdown($conversion->markdownToDocx($written));

    expect($markdown)->toBe($read);
})->with([
    'the header row returns bold' => [
        "| A | B |\n| --- | --- |\n| 1 | 2 |\n",
        "| **A** | **B** |\n| :-- | :-- |\n| 1 | 2 |",
    ],
    'a fenced block keeps the fence and loses the language' => [
        "```php\n\$x = 1;\n\$y = 2;\n```\n",
        "```\n\$x = 1;\n\$y = 2;\n```",
    ],
    'a fence of one line comes back as inline code' => [
        "```\n\$x = 1;\n```\n",
        '`$x = 1;`',
    ],
    "a quote's own line breaks are joined" => [
        "> First line\n> Second line.\n",
        '> First line Second line.',
    ],
    'the last line has no trailing newline' => [
        "# Title\n",
        '# Title',
    ],
    'adjacent fences merge into one block' => [
        "```php\n\$x = 1;\n\$y = 2;\n```\n```\n\$z = 3;\n```\n",
        "```\n\$x = 1;\n\$y = 2;\n\$z = 3;\n```",
    ],
]);

it('loses the quote and the fence when plain is requested', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->officeToMarkdown(
        $conversion->markdownToDocx("> A quoted line.\n\n```php\n\$x = 1;\n\$y = 2;\n```\n", plain: true),
    );

    // The quote's style is what told the reader it was a quote, and the code
    // font is what told the reader it was code: dropping both leaves prose.
    expect($markdown)->toBe("A quoted line.\n\n\$x = 1;\n\n\$y = 2;");
});

it('reads a word written in a named style back as the word', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->officeToMarkdown(
        $conversion->markdownToDocx("---\ntheme: corporate\n---\n\nResilience :brand[Bakery] is open.\n"),
    );

    // The style is written as direct formatting, and the reader reads the
    // weight of it and no colour: the word is there, the style is not.
    expect($markdown)->toBe('Resilience **Bakery** is open.');
});

it('writes a label holding markdown out as its own source text', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->officeToMarkdown(
        $conversion->markdownToDocx("---\ntheme: corporate\n---\n\nA :brand[**Bakery**] here.\n"),
    );

    // A label is plain text, so a complete Markdown construct in one is not
    // honoured — and a form the library cannot draw is drawn as its own
    // source text rather than dropped. The reader escapes the brackets of a
    // form it finds as text.
    expect(officeText($conversion->markdownToDocx("---\ntheme: corporate\n---\n\nA :brand[**Bakery**] here.\n"), 'docx'))
        ->toContain(':brand[**Bakery**]')
        ->and($markdown)->toContain(':brand\[\*\*Bakery\*\*\]');
});

it('refuses a highlight in a word written in a style', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $caught = null;

    try {
        $conversion->markdownToPresentation(
            "---\ntheme: corporate-dark\n---\n\nA :brand[Bakery]{highlight=FF0000} here.\n",
        );
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    // The slides half has no way to write a highlight, so the attribute is
    // refused by name rather than accepted and quietly dropped.
    expect($caught)->toBeInstanceOf(OfficeDocumentException::class)
        ->and($caught->getMessage())->toContain('highlight');
});

it('refuses a markdown construct in the label of a word written in a style', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $caught = null;

    try {
        $conversion->markdownToPresentation(
            "---\ntheme: corporate-dark\n---\n\nA :brand[**Bakery**] here.\n",
        );
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    // A deck that renders this shows something the author did not write, so
    // the render stops and says the two ways out.
    expect($caught)->toBeInstanceOf(OfficeDocumentException::class)
        ->and($caught->getMessage())->toContain('plain text');
});

/**
 * The readable text of a rendered document, for a format that is a package.
 *
 * A directive the format cannot carry is drawn as its own source text, so
 * the text of the package is where a missing one is seen.
 */
function officeText(string $bytes, string $format): string
{
    if ($format === 'rtf') {
        return $bytes;
    }

    $path = tempnam(sys_get_temp_dir(), 'spora-office-text-');

    if ($path === false) {
        throw new FixtureException('Could not stage a document file.');
    }

    try {
        file_put_contents($path, $bytes);

        $archive = new ZipArchive();

        if ($archive->open($path) !== true) {
            throw new FixtureException('Could not open the document package.');
        }

        $text = '';

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $text .= (string) $archive->getFromIndex($index);
        }

        $archive->close();

        return $text;
    } finally {
        @unlink($path);
    }
}

/**
 * The format table in the skill: which format carries which directive.
 *
 * A directive a format cannot carry is drawn as its own source text, so the
 * text is how a missing one is seen. The marker is the directive's name and
 * not the form that was written: the source form is rewritten as it is drawn
 * — the empty braces of an attribute list are not written, and a bare value
 * is re-quoted.
 */
it('carries only what the format can hold', function (string $written, string $marker, array $formats) {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = "# Report\n\n{$written}\n";

    foreach (['docx' => 'markdownToDocx', 'odt' => 'markdownToOdt', 'rtf' => 'markdownToRtf'] as $format => $method) {
        $bytes = $conversion->{$method}($markdown);

        $carried = in_array($format, $formats, true);

        // The directive is absent from the text when the format draws it, and
        // present in it when the format cannot carry it.
        expect(str_contains(officeText($bytes, $format), $marker))
            ->toBe(!$carried, sprintf(
                '"%s" in a %s is %s',
                $written,
                $format,
                $carried ? 'drawn' : 'drawn as its own source text',
            ));
    }
})->with([
    'a page break is carried by all three' => ['::pagebreak', '::pagebreak', ['docx', 'odt', 'rtf']],
    'a caption is carried by all three' => ['::caption[Figure 1]{}', '::caption', ['docx', 'odt', 'rtf']],
    'a section break is carried by all three' => ['::section{columns=2}', '::section', ['docx', 'odt', 'rtf']],
    'a table of contents is a docx alone' => ['::toc', '::toc', ['docx']],
    'a footnote is a docx alone' => [':footnote[Body.]', ':footnote[Body.]', ['docx']],
    'a text box is a docx alone' => ['::textbox[Hello]{left=1 top=1 width=5 height=3}', '::textbox', ['docx']],
    'a shape is a docx alone' => ['::shape{type=rect fill=FF0000}', '::shape', ['docx']],
    'a form field is a docx alone' => ['::field{name=who type=textinput}', '::field', ['docx']],
]);

it('carries the columns of a section in a docx and an odt, and not in an rtf', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = "# Report\n\n::section{columns=2}\n";

    expect(officeText($conversion->markdownToDocx($markdown), 'docx'))->toContain('w:num="2"')
        ->and(officeText($conversion->markdownToOdt($markdown), 'odt'))->toContain('fo:column-count="2"')
        ->and(officeText($conversion->markdownToRtf($markdown), 'rtf'))->not->toContain('\cols');
});

it('maps the package exception tree onto OfficeDocumentException', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    // `$caught` rather than `$this->fail()` inside try/catch: PHPUnit's
    // failure exception derives from AssertionFailedError, which would be
    // caught by the very `catch` block meant to inspect the real exception.
    $caught = null;

    try {
        $conversion->officeToMarkdown('this is not a zip archive');
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    // The upstream exception must survive as `$previous`, or the original
    // wording is lost from every log line the ingest pipeline writes.
    expect($caught)->toBeInstanceOf(OfficeDocumentException::class);
    expect($caught->getPrevious())->toBeInstanceOf(UnreadableDocument::class);
    expect($caught->getMessage())->toContain('Reading the Word document');
});

it('caps the forward direction at 8 MiB and tightens the reverse archive bounds', function () {
    // Tightened from the library's 256 MiB default. The entry and style-depth
    // caps stay at theirs on purpose — they are the zip-bomb and style-loop
    // defences, and the package's guidance is not to relax them.
    expect(OfficeConversion::MAX_MARKDOWN_BYTES)->toBe(8 * 1024 * 1024);
    expect(OfficeConversion::MAX_PART_BYTES)->toBe(64 * 1024 * 1024);
    expect(OfficeConversion::MAX_ARCHIVE_ENTRIES)->toBe(4096);
    expect(OfficeConversion::MAX_STYLE_DEPTH)->toBe(32);
});

it('refuses markdown beyond the ceiling without handing it to the renderer', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());
    $recorder   = new ErrorRecorder();
    set_error_handler($recorder, E_ALL);

    $caught = null;

    try {
        $conversion->markdownToDocx(str_repeat('a', OfficeConversion::MAX_MARKDOWN_BYTES + 1));
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    } finally {
        restore_error_handler();
    }

    expect($caught)->toBeInstanceOf(OfficeDocumentException::class);
    expect($caught->getMessage())->toContain((string) OfficeConversion::MAX_MARKDOWN_BYTES);
});

it('swallows the upstream PHPWord deprecation and re-raises everything else', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());
    $recorder   = new ErrorRecorder();
    set_error_handler($recorder, E_ALL);

    try {
        $docx = $conversion->markdownToDocx(DocxFixtures::SAMPLE_MARKDOWN);
    } finally {
        restore_error_handler();
    }

    expect(substr($docx, 0, 2))->toBe('PK');
    // Nothing may reach the kernel's handler: `Kernel::configureErrorHandling()`
    // logs `E_DEPRECATED` at `warning`, so a survivor is a `spora.log` line on
    // every agent turn that writes a document.
    expect($recorder->entries())->toBe([]);
    expect($recorder->phpwordNoticeCount())->toBe(0);
});

it('lets a deprecation from outside phpoffice/phpword through to the handler below', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());
    $recorder   = new ErrorRecorder();
    set_error_handler($recorder, E_ALL);

    try {
        invokeSuppressed($conversion, static function (): void {
            raiseEngineDeprecation(1);
        });
    } finally {
        restore_error_handler();
    }

    expect($recorder->entries())->toHaveCount(1);
    expect($recorder->entries()[0]['errno'])->toBe(E_DEPRECATED);
    expect($recorder->entries()[0]['file'])->not->toContain('/phpoffice/phpword/');
});

it('pops its error handler once the render is done', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());
    $sentinel   = new ErrorRecorder();
    set_error_handler($sentinel, E_ALL);

    try {
        $conversion->markdownToDocx(DocxFixtures::SAMPLE_MARKDOWN);

        // Stack depth, not whether a notice arrived. The earlier version of
        // this test counted PHPWord's own deprecations and asserted they
        // reached the recorder — which only works on PHP 8.5, where
        // `Style::getStyle()` passes null to an array offset. On 8.4 the
        // library is silent, the recorder sees nothing, and a correct
        // suppressor reads as a leak. The leak this guards against has
        // nothing to do with which PHP raises what: it is a frame left on
        // the stack, so that is what is measured.
        expect(ErrorHandlerStack::leakedFramesAbove($sentinel))->toBe(0);
    } finally {
        restore_error_handler();
    }
});

it('pops its error handler when the conversion throws', function () {
    // The `finally` in `withoutUpstreamPhpWordNotices()` has to fire on the
    // exception path too, and that is the path a plain happy-path render
    // never takes.
    $conversion = new OfficeConversion(new FakeMediaLibrary());
    $sentinel   = new ErrorRecorder();
    set_error_handler($sentinel, E_ALL);

    try {
        try {
            $conversion->officeToMarkdown('not a zip');
        } catch (OfficeDocumentException) {
            // The point is the handler stack afterwards, not the throw.
        }

        expect(ErrorHandlerStack::leakedFramesAbove($sentinel))->toBe(0);
    } finally {
        restore_error_handler();
    }
});

it('draws a document in the theme the media library holds', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'house', [
        'theme.yml' => "name: House\nstyles:\n  heading.1:\n    color: \"1A2B3C\"\n",
    ]);

    $conversion = new OfficeConversion($library);

    $markdown = "---\ntheme: house\n---\n\n# Quarterly report\n\nRevenue grew.\n";

    $styles = DocxFixtures::partOf(
        $conversion->markdownToDocx($markdown, 7),
        DocxFixtures::STYLES_PART,
    );

    // The theme's own colour, and not the one the built-in look uses.
    expect($styles)->toContain('1A2B3C');
});

it('draws a document in a theme whose manifest carries the slides half\'s keys', function () {
    $library = new FakeMediaLibrary();

    // One manifest for both halves: a theme file may carry a look for either
    // half, and each half is served the keys it reads. The Word loader
    // refuses a key it does not know, so the projection is what makes this
    // one folder serve both.
    $library->withTheme(7, 'house', [
        'theme.yml' => "name: House\ndescription: The house theme\nauthor: Ops\n"
            . "dark: true\ncolors:\n  accent1: \"#4C1D95\"\nfonts:\n  heading: Inter\n"
            . "styles:\n  heading.1:\n    color: \"1A2B3C\"\n",
    ]);

    $conversion = new OfficeConversion($library);

    $markdown = "---\ntheme: house\n---\n\n# Quarterly report\n\nRevenue grew.\n";

    $styles = DocxFixtures::partOf(
        $conversion->markdownToDocx($markdown, 7),
        DocxFixtures::STYLES_PART,
    );

    expect($styles)->toContain('1A2B3C');
});

it('draws a document and a deck in one theme folder', function () {
    $library = new FakeMediaLibrary();

    // One folder, one manifest, one theme: the look of a document and the look
    // of a deck are two sections of the same file, and each half reads its
    // own.
    $library->withTheme(7, 'house', [
        'theme.yml' => "name: House\nauthor: House Style Desk\n"
            . "colors:\n  accent1: \"#4C1D95\"\n"
            . "styles:\n  heading.1:\n    color: \"1A2B3C\"\n",
    ]);

    $conversion = new OfficeConversion($library);

    $styles = DocxFixtures::partOf(
        $conversion->markdownToDocx("---\ntheme: house\n---\n\n# Quarterly report\n", 7),
        DocxFixtures::STYLES_PART,
    );

    $deck = $conversion->markdownToPresentation("---\ntheme: house\n---\n\n# Quarterly report\n", 7);

    // The document is drawn in the word half's look and the deck in the
    // slides half's, and neither half refuses the other's section.
    expect($styles)->toContain('1A2B3C')
        ->and(substr($deck, 0, 2))->toBe('PK')
        ->and(DocxFixtures::partOf($deck, 'docProps/core.xml'))->toContain('House Style Desk');
});

it('draws a document in the theme the caller named, not the one in its frontmatter', function () {
    $library = new FakeMediaLibrary();
    $library->withTheme(7, 'house', [
        'theme.yml' => "name: House\nstyles:\n  heading.1:\n    color: \"1A2B3C\"\n",
    ]);
    $library->withTheme(7, 'corporate', [
        'theme.yml' => "name: Corporate\nstyles:\n  heading.1:\n    color: \"4C1D95\"\n",
    ]);

    $conversion = new OfficeConversion($library);

    $markdown = "---\ntheme: house\n---\n\n# Quarterly report\n\nRevenue grew.\n";

    $styles = DocxFixtures::partOf(
        $conversion->markdownToDocx($markdown, 7, 'corporate'),
        DocxFixtures::STYLES_PART,
    );

    // The caller's theme answers for the document: the frontmatter's is not
    // read at all, so the document is drawn in the theme that was named in code.
    expect($styles)->toContain('4C1D95')
        ->and($styles)->not->toContain('1A2B3C');
});

it('refuses a theme that does not resolve', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $caught = null;

    try {
        $conversion->markdownToDocx("# Report\n", 7, 'no-such-theme');
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(OfficeDocumentException::class)
        ->and($caught->getMessage())->toContain('no-such-theme');
});

it('draws a document with no principal in the theme the library ships', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $docx = $conversion->markdownToDocx("# Report\n", null, 'corporate');

    expect(substr($docx, 0, 2))->toBe('PK');
});

it('renders a deck as the bytes of a pptx', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $pptx = $conversion->markdownToPresentation("# Title\n\n---\n\n# Second slide\n");

    expect(substr($pptx, 0, 2))->toBe('PK')
        ->and(strlen($pptx))->toBeGreaterThan(1000);
});

it('reads a rendered deck back as the markdown it was written from', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->presentationToMarkdown(
        $conversion->markdownToPresentation("# Title\n\nSome words.\n\n---\n\n# Second slide\n"),
    );

    expect($markdown)->toContain('Title')
        ->and($markdown)->toContain('Some words.')
        ->and($markdown)->toContain('Second slide');
});

it('reads the theme and the background of a deck back', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $markdown = $conversion->presentationToMarkdown(
        $conversion->markdownToPresentation(
            "---\ntitle: A themed deck\ntheme: corporate-dark\n---\n\n"
            . "---\nlayout: blank\nbackground: accent1\n---\n\n# One\n",
        ),
    );

    // The deck records the theme it was drawn in, and a slide's background
    // comes back as the frontmatter that names it.
    expect($markdown)->toContain('theme: "corporate-dark"')
        ->and($markdown)->toContain('background: "#89B4FA"');
});

it('takes every frontmatter key the skill lists for a deck', function (string $frontmatter, string $body = "# One\n") {
    $library = new FakeMediaLibrary();
    $library->withAsset(7, 'templates.md', "---\ntemplate: page\n---\n\n::slide-number{left=92% top=90% width=6% height=6% color=subtle font-size=10}\n");

    $conversion = new OfficeConversion($library);

    $pptx = $conversion->markdownToPresentation("---\n{$frontmatter}\n---\n\n{$body}", 7);

    expect(substr($pptx, 0, 2))->toBe('PK');
})->with([
    'title' => ["title: A deck"],
    'name' => ['name: a-deck'],
    'level' => ['level: 2'],
    'layout' => ['layout: blank'],
    'author' => ['author: Ops'],
    'description' => ['description: A deck about a deck'],
    'theme' => ['theme: pitch'],
    'template' => ["template: brand\ntemplate-settings:\n  background: accent1", "\n::rectangle{left=0 top=0 width=100% height=0.7% fill=accent1}\n"],
    'templates-file' => ['templates-file: templates.md'],
    'background' => ['background: accent1'],
    'background-gradient' => ['background-gradient: "accent1,lt2"'],
    'background-angle' => ["background-gradient: \"accent1,lt2\"\nbackground-angle: 45"],
    'background-radial' => ["background-gradient: \"accent1,lt2\"\nbackground-radial: true"],
    'theme-variables' => ["theme-variables:\n  colors:\n    accent1: \"#7AA2F7\""],
    'deck-settings' => ["deck-settings:\n  background: \"#101014\""],
]);

it('refuses a frontmatter key the skill does not list for a deck', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $caught = null;

    try {
        $conversion->markdownToPresentation("---\ntransition: fade\n---\n\n# One\n");
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(OfficeDocumentException::class)
        ->and($caught->getMessage())->toContain('transition');
});

it('draws the shapes the skill shows', function (string $body) {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $pptx = $conversion->markdownToPresentation("---\nlayout: blank\n---\n\n{$body}");

    expect(substr($pptx, 0, 2))->toBe('PK');
})->with([
    'a placed rectangle, a circle and a content box' => <<<'MARKDOWN'
        ::rectangle{left=0 top=0 width=100% height=100% fill=panel layer=back}

        ::circle{left=8% top=10% width=6% height=6% fill=accent1}

        :::content{left=20% top=40% width=60% height=40% font-size=20}
        ## Q3 review
        - 4,182 signups
        :::
        MARKDOWN,
    'a gradient and a radial one' => <<<'MARKDOWN'
        ::rectangle{gradient="accent1,dk1@50,lt2" gradient-angle=45}

        ::circle{gradient="accent2,accent1" gradient-radial}
        MARKDOWN,
    'a shape that bleeds off the edge' => <<<'MARKDOWN'
        ::ellipse{left=-8% top=62% width=22% height=34% fill=panel bleed=true}
        MARKDOWN,
]);

it('refuses a deck directive on a slide other than the first', function (string $key, string $value) {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $caught = null;

    try {
        $conversion->markdownToPresentation(
            "---\ntitle: A deck\n---\n\n# One\n\n---\n{$key}: {$value}\n---\n\n# Two\n",
        );
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(OfficeDocumentException::class)
        ->and($caught->getMessage())->toContain('slide 2');
})->with([
    'theme' => ['theme', 'pitch'],
    'author' => ['author', 'Ops'],
    'description' => ['description', 'A deck'],
    'deck-settings' => ['deck-settings', "background: \"#101014\""],
]);

it('refuses a deck that is not a deck', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $caught = null;

    try {
        $conversion->presentationToMarkdown('this is not a zip archive');
    } catch (OfficeDocumentException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(OfficeDocumentException::class)
        ->and($caught->getMessage())->toContain('Reading the presentation');
});

it('leaves the pictures out of a deck read back', function () {
    $library = new FakeMediaLibrary();
    $library->withAsset(7, DeckFixtures::PICTURE_NAME, DeckFixtures::pictureBytes());

    $conversion = new OfficeConversion($library);

    $pptx = $conversion->markdownToPresentation(DeckFixtures::DECK_WITH_PICTURE, 7);

    // The deck really holds the picture, or the assertions below would pass
    // for the wrong reason.
    expect(DeckFixtures::mediaParts($pptx))->not->toBeEmpty();

    $markdown = $conversion->presentationToMarkdown($pptx);

    // The reverse direction names no media directory, so the library leaves
    // the picture out of the extracted deck altogether: no reference to a
    // file, and no alt text standing in for it.
    expect($markdown)->not->toContain('![')
        ->and($markdown)->not->toContain(PictureFixture::ALT)
        ->and($markdown)->toContain('Title slide');
});

it('reads a document\'s embedded picture back as a reference to no file', function () {
    $conversion = new OfficeConversion(new FakeMediaLibrary());

    $directory = sys_get_temp_dir() . '/spora-office-picture-' . bin2hex(random_bytes(4));
    $picture  = PictureFixture::writeInto($directory);

    try {
        // The document's own frontmatter names the base path, so the picture
        // is embedded the way a document written in Word carries one.
        $docx = $conversion->markdownToDocx(sprintf(
            "---\noptions:\n  imageBasePath: %s\n---\n\n# Quarterly report\n\n![%s](logo.png)\n",
            $directory,
            PictureFixture::ALT,
        ));
    } finally {
        @unlink($picture);
        @rmdir($directory);
    }

    $markdown = $conversion->officeToMarkdown($docx);

    // The Word half keeps the reference and the alt text; the file behind it
    // is never written, because the extraction names no media directory.
    expect($markdown)->toContain('![' . PictureFixture::ALT . ']')
        ->and(is_dir(getcwd() . '/media'))->toBeFalse();
});
