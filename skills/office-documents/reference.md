# The document vocabulary

Every name the renderer accepts, in one place. Read with
`skill(action: "read", name: "office-documents", filename: "reference.md")`.

**These lists are closed.** A name outside one of them fails the render, and
the failure names what was wrong. The rules that go with them are in
`SKILL.md`; this file is the spelling.

## The directives

A document is more than paragraphs, and these are the names that place the
rest of it. The last column is the one that matters when choosing a format.

| Directive | Carries | Carried by |
| --- | --- | --- |
| `::image{src=… width=… height=… align=…}` | a picture | `.docx`, `.odt`, `.rtf` |
| `::caption[Figure 1]{}` | a caption | `.docx`, `.odt`, `.rtf` |
| `::pagebreak` | a page break | `.docx`, `.odt`, `.rtf` |
| `::section{columns=2}` | a section break, and its columns | `.docx`, `.odt` (the break alone in `.rtf`) |
| `::toc` | a table of contents | `.docx` |
| `:footnote[…]` | a footnote | `.docx` |
| `::textbox[…]{width=… height=…}` | a box with text in it | `.docx` |
| `::shape{type=rect …}` | a shape | `.docx` |
| `::field{name=…}` | a form field | `.docx` |

**A format that cannot carry one is given the characters the author wrote**,
and the loss is reported — so the directive is safe to write, and the reader of
the `.rtf` sees it as text rather than not at all.

### The attributes of each

| Directive | Attributes |
| --- | --- |
| `::image` | `src` — a file read from the machine the render runs on, not a URL; `width` and `height` — in centimetres; `align` — `left`, `center` or `right` |
| `::caption` | the label, which is the caption's text |
| `::section` | `columns` — how many columns the section is set in |
| `::textbox` | `width` and `height` — in centimetres; `left` and `top` — in centimetres, from the left margin and from the top of the paragraph it stands in; `border` — six hexadecimal digits; `bleed` — `true` or `false` |
| `::shape` | `type` — one of the six below; `width` and `height` — in centimetres; `fill` and `line` — six hexadecimal digits without a `#` |
| `::field` | `name`; `type` — one of the three below; `value` — a text field's default; `entries` — a dropdown's choices, separated by commas; `checked` — a checkbox's default |

The **shapes** a `.docx` can draw are `arc`, `curve`, `line`, `polyline`,
`rect` and `oval`. A type outside the six is written out as its own source
text rather than drawn.

The **form fields** are `textinput` — the default — `checkbox` and `dropdown`.
A dropdown records the chosen entry as its *position* in the list, so a
`value` on a dropdown is refused: write the choices with `entries` and let the
reader pick one.

## The named text styles

A theme's `textStyles:` section names the styles a document may write a word
in.

```markdown
---
theme: corporate
---

Resilience :brand[Bakery] is open.
```

One attribute may be written over a named style — `:brand[Bakery]{color=FF0000}`
is that word in red — and the reserved name `style` writes a word in a style of
its own, with no theme behind it: `:style[teal]{color=00B4D8}`.

| Attribute | |
| --- | --- |
| `color` | the ink: a hex value or a theme slot |
| `size` | the size of the type, in points |
| `font` | the family: one of the theme's own font slots or a literal |
| `bold` | the weight: `true` or `false` |
| `italic` | the slant: `true` or `false` |
| `underline` | the underline: `true` or `false` |
| `strike` | the strike: `true` or `false` |
| `highlight` | a colour behind the word |

A label is plain text, so a complete Markdown construct inside one is not
honoured, and such a label is written out as its own source text. A bare `*`
or `_` is not Markdown, so `2 * 3` and `snake_case` are ordinary text. A name
the theme does not define is written out as its own source text too.

## The theme's own file

A theme is one file. The folder holds a `theme.yml`, and each section of it is
one half's look.

| Section | |
| --- | --- |
| `name` | the theme's name |
| `description` | |
| `author` | |
| `styles` | the slots a document is drawn from — `heading.1`, `paragraph`, and the rest |
| `textStyles` | the named styles a word may be written in |

The Word half reads `styles:` and `textStyles:`. The slides half reads
`dark`, `colors`, `fonts`, `style` and `layouts` — so **one folder can serve a
document and a deck**: each half reads the section that is its own and leaves
the other's alone.
