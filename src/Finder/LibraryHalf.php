<?php

declare(strict_types=1);

namespace Spora\Plugins\Office\Finder;

use MarkdownPresentation\Theme\ThemeLoader as SlidesThemeLoader;
use MarkdownPresentation\Theme\ThemeMapping as SlidesThemeMapping;
use MarkdownWord\Theme\Theme as WordTheme;

/**
 * Which half of `markdown-office` a finder serves.
 *
 * The two halves read the same manifest and part at the look: the Word half's
 * own is `styles:` and the slides half's is `dark:`, `colors:`, `fonts:`,
 * `style:` and `layouts:`. What they share is a theme's name, its
 * description, its author and its named text styles.
 */
enum LibraryHalf
{
    /** The Word half: Markdown in, `.docx` / `.odt` / `.rtf` out. */
    case Word;

    /** The slides half: Markdown in, `.pptx` out. */
    case Slides;

    /**
     * The keys a `theme.yml` may carry for this half.
     *
     * Read from the libraries rather than copied out here, because a key the
     * half does not read is one it refuses — and a copy of the list would
     * drift from the library that has to agree with it.
     *
     * @return list<string>
     */
    public function themeKeys(): array
    {
        return match ($this) {
            self::Word => WordTheme::KEYS,
            self::Slides => [...SlidesThemeMapping::KEYS, ...SlidesThemeLoader::SECTIONS],
        };
    }
}
