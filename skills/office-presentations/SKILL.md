---
name: office-presentations
description: "Produce and read PowerPoint decks (.pptx) through the Media Archive. Trigger on any of: 'make this a slide deck', 'turn this into a presentation', 'build me a pitch deck', 'attach a PowerPoint', 'read this deck', 'what were the slides about', or any request naming a .pptx file. Covers the deck dialect, the themes the media library holds, and what a deck does and does not survive."
license: MIT
compatibility: "Designed for Spora agents with the `media` tool enabled."
metadata:
  author: spora-ai
  version: "1.0"
allowed-tools: media
---

# Office presentations

This plugin ships **no tool of its own**. It registers two PPTX *producers* with
the Media Archive — one that renders a Markdown deck into a `.pptx` derivative,
one that extracts a `.pptx` back into an `md` derivative — so a deck becomes a
format the existing `media` tool can write and read. Producing one costs **two
`media` calls**. Reading one the user attached costs **none at all** — the
extracted text is already in your context.

A deck is **not** a long document. It is a sequence of slides, and the Markdown
that describes one is a different dialect from the Markdown that describes a
report. Read [The dialect](#the-dialect) before you write the first deck.

## The chain

Markdown in, `.pptx` out, in two calls on the `media` tool.

```jsonc
// 1. store the markdown as a media asset
{
  "action": "create_media",
  "content": "# Q3 review\n\nRevenue grew **12%**.\n\n---\n\n# What changed\n\nThree things.",
  "filename": "q3-review.md",
  "mime_type": "text/markdown"
}
// → data.asset_id          e.g. "0b8a…f31c"

// 2. render that same asset to PowerPoint
{
  "action": "create_derivative",
  "asset_id": "0b8a…f31c",   // ← the id step 1 returned, verbatim
  "format": "pptx"
}
// → data.derivative_id, data.asset_url
```

**`data.asset_id` from step 1 is the only input step 2 takes.** Copy it
character-for-character. Do not invent a UUID, do not `search` for the file you
just wrote, and do not call `create_media` again to recover the id — it is not
idempotent, and the second row is a duplicate the user will see in their
library. (Step 2 *is* idempotent; see [Non-idempotency](#non-idempotency).)

- `filename` is what the user sees on the download card. Name the deck the way
  the user would (`q3-review`), not `deck.md`.
- `mime_type` is a **hint**, default `text/markdown`. The archive re-sniffs the
  bytes, so `data.mime_type` on the response is the authoritative type.
- `content` is capped at 1 MiB.
- The one producer option is `{"theme": "<name>"}` — see [Themes](#themes).
- `prompt` is optional provenance: the brief the deck was written from. Not the
  deck body.

The result is a **download card**, exactly as for a document: echo the `div`
the response carries, do not hand-roll a markdown link in its place. The `href`
is session-authenticated, so use `media(action: "get_public_url")` when the
user wants a share link.

## The dialect

A deck is Markdown. **Slides are separated by a line of three dashes**, and
each slide may carry its own frontmatter. The first slide's frontmatter is the
deck's.

```markdown
---
title: Q3 review
theme: corporate
---

# Q3 review

Revenue grew **12%** against a flat market.

---

# What changed

- Pricing
- Volume

::note
Say the number out loud.
::
```

- `---` on a line of its own **closes the slide being read**. A `---` followed
  by a block and another `---` is a frontmatter block for the next slide, and
  **the block has to start on the line right after the `---`** — a blank line
  in between is not a frontmatter block, and the block is then read as the next
  slide's own content.
- A thematic break inside a slide is `***`, not `---`. There is no other way to
  write one.
- A `---` inside a fenced code block or an HTML comment does **not** split a
  slide.
- The **first heading of a slide is its title**. A `##` after it is ordinary
  content.
- `::note` … `::` is the speaker note, and it has to be the last thing on the
  slide.
- `::column` on a line of its own moves everything below it into the next
  column — the only way to have two columns, and there is no way back: the
  marker means "the next column". **The slide's layout has to have more than
  one column** — `two-column` or `image-text`; on a `content` slide it is a
  failure that says so. The marker takes no argument.
- `layout:` names the slide's shape: `title`, `content`, `two-column`,
  `section`, `blank`, `image-text`, `image-title`, `image-slide`. It is the
  deck's choice, and the theme decides what each of those looks like.
- The keys a deck's frontmatter may carry are `title`, `name`, `level`,
  `layout`, `theme`, `author`, `description`, `template`, `templates-file`,
  `template-settings`, `deck-settings`, `background`, `background-gradient`,
  `background-angle`, `background-radial` and `theme-variables`.
  **A key outside that list fails the render** — so do not invent one, and do
  not carry a document's frontmatter over into a deck.
- `theme`, `author`, `description` and `deck-settings` describe the **deck**,
  so they belong in slide 1's frontmatter. On any other slide they are a
  failure that says so, because two slides naming a theme is a deck that
  renders one of them and says nothing about the other.

The rules are the ones Slidev uses, so a dialect a model already writes is the
dialect worth writing.

## Themes

A theme is the look a deck is drawn in: its colours, its type scale, its
layouts. A deck names one in its **frontmatter**:

```markdown
---
theme: corporate
---
```

**The theme names are two lists, and the media library's wins.**

The ones that ship with the renderer, always available:

| Theme | The look |
| --- | --- |
| `default` | Office's colours and type, for a deck that says nothing about its look |
| `corporate-dark` | a dark corporate deck for technical briefings |
| `minimal` | dense text-first decks, with as little on a slide as the text needs |
| `dense` | small type and tight gaps, for a deck that has to hold a page |
| `pitch` | black, gold and one blue, for a pitch or a proposal |

And the ones the media library holds. A theme is a **folder** in the media
library whose name is the theme's name; list them with

```jsonc
{ "action": "list_folders" }   // the media tool: the principal's folders
```

**`list_folders` needs a core that carries the media-folders feature**
(migration `0097`). On a core without it the `media` tool has no folder
operation to call and the media library holds no folders, so the built-in
table above is the whole list of themes.

A folder that holds a `theme.yml` is a theme. **A media theme shadows a
built-in of the same name**, so the list you fetched is the list to choose
from. The folder's id resolves as well, so `theme:` may name either the
folder's name or its id.

A theme name is a **folder name, not a path**. `theme: ../something` names no
folder of yours and the render fails saying so.

**A theme named in the call outranks the frontmatter.** `options: {"theme":
"corporate"}` answers for the deck: the frontmatter's `theme:` is not read. A
theme that does not exist fails the render with a message naming it.

**A theme is one file.** The folder holds a `theme.yml` and the palette, the
layout roles and the named text styles are sections of it:

```yaml
name: Corporate
colors:                      # the palette
  accent1: "#4C1D95"
layouts:                     # which layout of the master's each role gets
  content: Title and Content
textStyles:                  # the styles a word may be written in
  brand:
    color: accent1
    bold: true
```

**A `layouts.yml` or a `text-styles.yml` beside the theme is refused**, and
the render fails saying so: the content belongs in the `layouts:` and
`textStyles:` sections of the `theme.yml`, and the files beside it have to be
deleted as well. A folder left over from the older multi-file format needs
that fix before it renders at all.

A theme with **no** `layouts:` section is drawn with the renderer's own
mapping of the eight roles onto the Office layout names — `title` is "Title
Slide", `content` is "Title and Content", `two-column` is "Two Content",
`section` is "Section Header", `blank` is "Blank", `image-text` is "Picture
Left", `image-title` is "Picture with Caption" and `image-slide` is "Full
Bleed Picture". A theme **with** a `layouts:` section has the whole mapping: a
role it does not name is a role the theme does not map, and the deck that asks
for one is told which roles it does map.

The same file may carry the document half's look as well — a `styles:` section
— so **one folder can serve a deck and a document**: each half reads the
section that is its own and leaves the other's alone.

### Writing a word in a style

A theme's `textStyles:` section names the styles a deck may write a word in.
The built-in `corporate-dark` theme brings two of them — `brand` and `muted`:

```markdown
Resilience :brand[Bakery] is open.
```

The attributes are a closed set: `color`, `size`, `font`, `bold`, `italic`,
`underline` and `strike`. One may be written over a named style:
`:brand[Bakery]{color=FF0000}` is that word in red. The reserved name `style`
writes a word in a style of its own, with no theme behind it —
`:style[teal]{color=00B4D8}`.

**`highlight` is not one of them.** A highlight is a colour behind the word,
and this half has no way to write one, so the attribute is refused by name
rather than accepted and quietly dropped — in a deck and in a theme's
`textStyles:` section alike. The Word half does draw a highlight; this one
does not.

A label is plain text, and **a label holding Markdown is refused** — a deck
that renders `:brand[**Bakery**]` shows something the author did not write, so
the render fails with a message naming the two ways out: give the style the
weight in the theme's `textStyles:` section, or write the emphasis in the
paragraph around it. A bare `*` or `_` is a character and not an intent, so
`2 * 3` and `snake_case` are drawn as they are.

A name the theme does not define is **refused**, with a message naming the
alternative — write it in the theme's `textStyles:` section, or write the word
in plain text.

## The look of a slide

A slide may name its own background and its own values of the theme. The
settings of a slide are laid over the settings of the template it draws, those
over the deck's, and those over the theme's — and one value laid over another
leaves the rest alone, so a slide that names one colour leaves the other
eleven as they were.

```markdown
---
layout: blank
background: accent1
---
```

```markdown
---
layout: blank
background-gradient: "accent1,dk1@50,lt2"
background-angle: 45
---
```

```markdown
---
layout: blank
theme-variables:
  dark: true
  colors:
    accent1: "#7AA2F7"
---
```

| Key | |
| --- | --- |
| `background` | the slide's background, one colour |
| `background-gradient` | the background's stops, as a shape's are |
| `background-angle` | the angle in degrees — 0° is to the top, 90° to the right |
| `background-radial` | the background runs from the middle outwards |
| `theme-variables` | the theme's own values for this slide: `dark:`, `colors:`, `fonts:`, `style:` |

**A background is one colour or one gradient, and the two together are
refused.** A background of a colour has neither an angle nor a radial flag, so
naming one with it is refused too.

**A slide's background does not change the colour its text is drawn in.** The
renderer does not judge, and a dark slide with dark text is a slide the author
wrote. `theme-variables:` is the way to change the text's colour with it.

Two more keys hold the same settings for somebody else:

- **`deck-settings:`** holds them for the **deck**, and is written in slide 1's
  frontmatter — which is the deck's own. Every slide of the deck is drawn with
  it.
- **`template-settings:`** holds them for the **template** a definition names,
  and every slide that draws that template gets them.

## Placing shapes yourself

Everything the layout places is placed for you. A **free shape** is one you
place yourself, in pixels or in fractions of the slide, written in the body of
a slide. Its attributes are separated by a **space**, and a comma is not a
separator: `width=40%, height=20%` is one value of `40%,` and one attribute
nobody reads.

```markdown
---
layout: blank
---

::rectangle{left=0 top=0 width=100% height=100% fill=panel layer=back}

::circle{left=8% top=10% width=6% height=6% fill=accent1}

:::content{left=20% top=40% width=60% height=40% font-size=20}
## Q3 review
- 4,182 signups
:::
```

| Directive | Draws |
| --- | --- |
| `::rectangle` `::roundrect` `::ellipse` `::circle` | a rectangle, a rounded one, an ellipse, a circle |
| `::triangle` `::diamond` `::chevron` `::hexagon` `::arrow` | the shape it names |
| `::line` | a hairline rule, as thick as its `stroke-width` |
| `::image` | a picture |
| `::slide-number` | the number of the slide, and with `of=true` the number of the deck after it |
| `:::content` … `:::` | Markdown, in a box you sized |

| Attribute | Default | |
| --- | --- | --- |
| `left`, `top` | `0` | where the box starts |
| `width`, `height` | `100%` | its size, so a shape with no attributes is full bleed |
| `anchor` | `nw` | which corner `left` and `top` name: `nw` `n` `ne` `e` `se` `s` `sw` `w` `center` |
| `layer` | `front` | `back` writes the shape before the layout's own, `front` after |
| `rotate` | `0` | degrees clockwise, about the middle of the box |
| `bleed` | `false` | whether the shape may run off the edge of the slide |
| `fill`, `stroke` | | the colours, six hexadecimal digits or a theme slot |
| `gradient`, `gradient-angle`, `gradient-radial` | | a gradient instead of a colour |

**A box lies within the slide**, and one that does not is refused with the
numbers rather than clamped — a shape quietly drawn somewhere other than where
it was asked for is a deck that renders and shows something the author did not
write. Full bleed is still reachable, because `left=0 width=100%` is exactly
the edge. A shape that is *meant* to run off the edge says so:
`bleed=true` is the permission, and the shape still has to be **on** the
slide.

**A shape may be painted with a gradient** instead of a colour, and the
gradient is written as its stops:

```markdown
::rectangle{gradient="accent1,dk1@50,lt2" gradient-angle=45}

::circle{gradient="accent2,accent1" gradient-radial}
```

A stop is a colour, and it may carry the place it is at as a percentage of the
gradient — `accent1@50` is that stop at half of the way. The angle is CSS's:
**0° is to the top, 90° to the right, 180° to the bottom**. A shape is filled
with a colour **or** with a gradient, and `fill` together with `gradient` is
refused rather than one of them being dropped.

## What a deck does and does not survive

A `.pptx` is a package of positioned boxes. A deck is text. **The text and the
structure travel; the design does not.**

Reading a deck back gives you the words and the order they were in, as a
Markdown deck you can render again in another design. What does not travel is
the type sizes, the colours of the text and the geometry: those live in the
theme and the master, and neither is in the extracted Markdown.

**What does come back is the theme and the background.** A deck records the
theme it was drawn in, and a deck read back names it — `theme: "pitch"` — so
it is drawn in the theme it came from rather than in the renderer's default. A
slide's background comes back as the frontmatter that names it: a colour, or a
gradient with its angle.

**The rule that follows: never use a round trip to edit a deck.** Read the deck
back, edit the Markdown, and render a new one. The Markdown you stored in step
1 is the source of truth.

**A picture does not come back at all.** The extraction writes no media
directory, so a picture in the deck is left out of the extracted Markdown — not
as a reference to a file, not as its alt text, not at all. A deck read back
carries its words and its structure and **no pictures**. The picture is still
inside the `.pptx`; the Markdown is all the read-back gives you.

If a picture matters, keep the deck rather than its extracted Markdown.

## Slides that are too full

A slide whose content does not fit is **clamped** to the room left and the
render carries on — nothing is lost silently in the file, but something is. The
report of what was clamped is on the renderer, not in the deck, so the practical
rule is the one a designer would give anyway: **one idea per slide**.

A slide that is too full is a slide the reader cannot read. Split it. A deck of
forty slides of dense prose is a document; render it as one with `format:
"docx"` instead.

## Limits

- **Slides are separated by `---`, so a `---` in your prose splits the deck.**
  A horizontal rule in a slide has to be `***`.
- **Remote images are never fetched.** The library has no HTTP client, so
  `![Chart](https://example.com/chart.png)` is not downloaded — the alt text
  stands in.
- **A deck may have at most 1000 slides.** No deck a person writes reaches it.
- **Only `pptx` is rendered.** There is no PDF, no `.odp` and no `.potx` output;
  asking for one fails with "No derivative producer supports format …".
- **`md` is a format identifier this plugin registers too**, in the other
  direction: asking for `format: "md"` on a PPTX parent produces the extracted
  deck as Markdown.

## Approval

Both `create_media` and `create_derivative` require operator approval per call.

**Do not tell the user the deck exists before the approval resolves.** No
`asset_id`, no download card, no "here's your file". Wait for the response,
then report what it returned.

## Reading a deck back

**Reading a `.pptx` the user attached needs no tool call.** The archive extracts
the upload into an `md` derivative and inlines that text into your context. So
"what were the slides about" and "summarise the attached deck" are answered
from text you can already see.

```jsonc
{ "action": "get_media", "asset_id": "<uuid>" }   // metadata, filename, mime, byte size, derivatives[]
{ "action": "get_source", "asset_id": "<uuid>" }  // extracted markdown preview for a binary mime
{ "action": "search", "mime_type": "application/" } // every document in scope
```

A corrupt or hostile `.pptx` degrades quietly: the upload still succeeds, the
extraction is skipped, and all you get is a metadata block. Say the text could
not be extracted rather than guessing at the contents.

## Non-idempotency

- `create_media` **duplicates on every call.** Keep the `asset_id` it returned
  and reuse it; never call it again to "check whether it worked".
- `create_derivative` **is idempotent** on
  `(parent_id, format, producer_plugin, producer_operation)`. Re-calling it with
  the same parent and format returns the existing `derivative_id`. **It does
  rewrite the stored bytes**, though: the existing derivative is re-rendered and
  replaced in place, and its `updated_at` moves — so a second call does pick up
  an edit to the parent, and does change the file the user already downloaded.

A *different* deck means a new `create_media` under a new filename, and
therefore a new parent id and a new derivative row.

## Notes

`allowed-tools` in this skill's frontmatter is **spec-experimental and not
enforced** by Spora. It records intent — this skill expects the `media` tool —
and nothing more.

The dialect, the directives and the themes are documented by the underlying
library at [markdown-office/markdown-presentation](https://github.com/markdown-office/markdown-presentation)
(MIT; `phpoffice/phppresentation` underneath is LGPL-3.0).
