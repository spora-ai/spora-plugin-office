# The deck vocabulary

Every name the renderer accepts, in one place. Read with
`skill(action: "read", name: "office-presentations", filename: "reference.md")`.

**These lists are closed.** A name outside one of them fails the render, and
the failure names what was wrong. The rules that go with them are in
`SKILL.md`; this file is the spelling.

## The frontmatter of a slide

The keys a slide's frontmatter may carry.

| Key | Carries | Belongs to |
| --- | --- | --- |
| `title` | the title, when the slide has no heading of its own | slide 1 is the deck's |
| `name` | the slide's own name, where the deck's `title:` is spoken for | the slide |
| `level` | the depth the title is drawn at | the slide |
| `layout` | the shape of the slide — see the layouts below | the slide |
| `theme` | the look the deck is drawn in | **the deck** |
| `author` | | **the deck** |
| `description` | | **the deck** |
| `template` | the template this slide draws | the slide |
| `templates-file` | a file of template definitions | **the deck** |
| `template-settings` | the settings of a template definition | **not on a slide at all** |
| `deck-settings` | the settings every slide in the deck is drawn with | **the deck** |
| `background` | the slide's background, one colour | the slide |
| `background-gradient` | the background's stops | the slide |
| `background-angle` | the gradient's angle | the slide |
| `background-radial` | the background runs from the middle outwards | the slide |
| `theme-variables` | the theme's own values for this slide | the slide |

A **deck-level** key on any slide but the first is a failure that says so.
`template-settings` is a failure on *every* slide — it belongs to a template
definition, and a definition lives in a templates file, not on a slide.

## The eight layouts

`layout:` names the slide's shape, and the theme decides what each looks like.

| Layout | What it is for | Columns | Title box | Office layout |
| --- | --- | --- | --- | --- |
| `title` | the opening slide | one | yes | "Title Slide" |
| `content` | a title and a body | one | yes | "Title and Content" |
| `two-column` | a title and two bodies | two | yes | "Two Content" |
| `section` | a break between two parts | one | yes | "Section Header" |
| `blank` | nothing but what you place | one | no | "Blank" |
| `image-text` | a picture beside a block of text | two, the picture in the wider one | yes | "Picture Left" |
| `image-title` | a large picture under a headline | one | yes | "Picture with Caption" |
| `image-slide` | the picture alone, full bleed | one, the whole page | no | "Full Bleed Picture" |

The last column is the mapping a theme with no `layouts:` section is drawn
with. A theme **with** one has the whole mapping, and a role it does not name
is a role the theme does not map.

**`blank` has no title box**, so a slide using it carries its title as a
heading: the one in its body if it wrote one, and the one its frontmatter
named if it did not.

A theme with no `layouts:` section is drawn with the renderer's own mapping of
these eight onto the Office layout names. A theme **with** a `layouts:`
section has the whole mapping: a role it does not name is a role the theme
does not map, and the deck that asks for one is told which roles it does map.

## The markers

Two colons mark a step or a column; three open a region that closes with
three.

| Written | Means |
| --- | --- |
| `::note` … `::` | a speaker note, taken off the slide. It has to be the last thing on the slide |
| `::click` | everything after it is the next step |
| `::click=3` | everything after it is step three |
| `:::click` … `:::` | everything between them is one step of its own |
| `::column` | everything after it is in the next column |

The markers are recognised in either case: `::Click`, `::Column`, `:::Click` and
`::Note` are the markers they look like. A marker written with an argument it
does not take — `::column=2` — is a failure naming the marker that would have
worked.

**There must be fewer `::column` markers than the layout has columns.** A
`content` slide has one column and takes no marker; `two-column` and
`image-text` have two and take one; a second marker on a two-column slide is
refused the same way a first one on a content slide is.

## The free shapes

Everything the layout places is placed for you. A **free shape** is one you
place yourself, in pixels or in fractions of the slide, written in the body of a
slide.

| Directive | Draws |
| --- | --- |
| `::rectangle` | a rectangle |
| `::roundrect` | a rounded one, with `border-radius` |
| `::ellipse` | an ellipse |
| `::circle` | an ellipse that is as wide as it is tall |
| `::triangle` | an isosceles triangle |
| `::diamond` | a diamond |
| `::chevron` | a chevron |
| `::hexagon` | a hexagon |
| `::arrow` | a right-pointing arrow |
| `::line` | a hairline rule, as thick as its `stroke-width` |
| `::image` | a picture |
| `::slide-number` | the number of the slide, and with `of=true` the number of the deck after it |
| `:::content` … `:::` | Markdown, in a box you sized |

Its attributes are separated by a **space**, and a comma is not a separator:
`width=40%, height=20%` is one value of `40%,` and one attribute nobody reads.

### Where the box is

| Attribute | Default | |
| --- | --- | --- |
| `left`, `top` | `0` | where the box starts |
| `width`, `height` | `100%` | its size, so a shape with no attributes is full bleed |
| `anchor` | `nw` | which corner `left` and `top` name: `nw` `n` `ne` `e` `se` `s` `sw` `w` `center` |
| `layer` | `front` | `back` writes the shape before the layout's own, `front` after |
| `rotate` | `0` | degrees clockwise, about the middle of the box: `45`, `-30`, `45deg` |
| `bleed` | `false` | whether the shape may run off the edge of the slide |

A size is read as pixels, as a percentage of the slide, or in points: `320`,
`320px`, `40%`, `2pt`.

### How the shape is painted

| Attribute | On | |
| --- | --- | --- |
| `fill`, `stroke` | the ten ink shapes, `::content`, `::slide-number` | |
| `gradient`, `gradient-angle`, `gradient-radial` | the ten ink shapes, `::content`, `::slide-number` | the stops, one after another with a comma between them |
| `alpha` | the ten ink shapes, `::content`, `::slide-number` | 0–100 for the whole shape |
| `stroke-width` | the ten ink shapes, `::content`, `::slide-number` | in points; `0` is a hairline |
| `border-radius` | `::roundrect` only | in pixels |
| `label` | the ten ink shapes | the shape's own text |
| `src`, `alt`, `fit` | `::image` | `fit` is `contain` or `stretch` |
| `color`, `font-size`, `font-family`, `align`, `vertical-align`, `padding` | `::content`, `::slide-number` | |
| `of` | `::slide-number` only | the number of the deck after the number of the slide |
| `shadow-color`, `shadow-distance`, `shadow-blur`, `shadow-direction`, `shadow-alpha` | all thirteen | |

A shape is filled with a colour **or** with a gradient, and `fill` together
with `gradient` is refused rather than one of them being dropped. A gradient
has at most **100 stops**, and at most **8192 characters** of them. The angle
is CSS's: **0° is to the top, 90° to the right, 180° to the bottom**, and a
radial gradient has no angle, so `gradient-angle` with `gradient-radial` is
refused.

### The colours

A colour is `none` or `transparent`, one of the four names a shape *plays*,
one of the twelve theme *slots* under either of their two spellings, or a hex
value.

| Written | Means |
| --- | --- |
| `none` `transparent` | nothing — written `a:noFill`, not inherited |
| `page` `ink` `subtle` `panel` | what the page behind the slide, the body text, a quote and a code block's panel are each drawn in |
| `accent1`–`accent6`, `dk1` `lt1` `dk2` `lt2`, `hlink` `folHlink` | the theme's own slots |
| `dark1` `light1` `dark2` `light2` `hyperlink` `followedHyperlink` | the same slots, under the names an earlier draft of the concept used |
| `#4C1D95` | that hex, with or without the `#` |

The four role names come first because they are what the rest of the library
paints with: a free shape and a laid-out one are the same colour on the same
theme. A deck written in them survives a theme swap and one written in hex
does not.

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

A background is one colour or one gradient, and the two together are refused. A
background of a colour has neither an angle nor a radial flag, so naming one
with it is refused too.

A slide's background does not change the colour its text is drawn in. The
renderer does not judge, and a dark slide with dark text is a slide the author
wrote. `theme-variables:` is the way to change the text's colour with it.

Two more keys hold the same settings for somebody else:

- **`deck-settings:`** holds them for the **deck**, and is written in slide 1's
  frontmatter — which is the deck's own. Every slide of the deck is drawn with
  it.
- **`template-settings:`** holds them for the **template** a definition names,
  and every slide that draws that template gets them.

## The named text styles

A theme's `textStyles:` section names the styles a deck may write a word in.

```markdown
---
theme: corporate-dark
---

Resilience :brand[Bakery] is open.
```

One attribute may be written over a named style — `:brand[Bakery]{color=FF0000}`
is that word in red — and the reserved name `style` writes a word in a style of
its own, with no theme behind it: `:style[teal]{color=00B4D8}`.

| Attribute | |
| --- | --- |
| `color` | the ink: a role, a theme slot or a hex value |
| `size` | the size of the type, in points |
| `font` | the family: one of the theme's own font slots or a literal |
| `bold` | the weight: `true` or `false` |
| `italic` | the slant: `true` or `false` |
| `underline` | the underline: `true` or `false` |
| `strike` | the strike: `true` or `false` |
| `highlight` | a colour behind the word — **refused by name in this half** |

A label is plain text, and a label holding Markdown is refused. A name the
theme does not define is refused, with a message naming the alternative — write
it in the theme's `textStyles:` section, or write the word in plain text.

## The theme's own file

A theme is one file. The folder holds a `theme.yml`, and each section of it is
one half's look.

| Section | |
| --- | --- |
| `name` | the theme's name |
| `description` | |
| `author` | |
| `dark` | whether the theme is a dark one |
| `colors` | the palette — the slots above |
| `fonts` | the type |
| `style` | the type scale |
| `layouts` | which layout of the master's each role gets |
| `textStyles` | the named styles a word may be written in |

The Word half reads `styles:` and the slides half reads `dark`, `colors`,
`fonts` and `style`; each is handed the keys it reads. A key *neither* half
reads is kept, so the library refuses it with a sentence naming the whole
vocabulary.
