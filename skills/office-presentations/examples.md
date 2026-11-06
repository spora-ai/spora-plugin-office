# Examples

Worked end-to-end examples. Read with
`skill(action: "read", name: "office-presentations", filename: "examples.md")`.

## Example 1 — a review deck, Markdown → `.pptx`

**User:** "Turn these notes into a deck for Monday's review."

The deck is one Markdown file. A line of three dashes ends a slide, and the
frontmatter above the first slide is the deck's.

```markdown
---
title: Q3 review
theme: corporate-dark
---

# Q3 review

Revenue grew **12%** against a flat market.

---

# What changed

- Pricing: +3 points
- Volume: +9 points

::note
Say the number out loud.
::

---

# What did not change

- Churn is flat at 4.1%
- Support load is flat at 900 tickets a week
```

### Call 1 — store the Markdown

```jsonc
{
  "action": "create_media",
  "content": "---\ntitle: Q3 review\ntheme: corporate-dark\n---\n\n# Q3 review\n\nRevenue grew **12%** against a flat market.\n\n---\n\n# What changed\n\n- Pricing: +3 points\n- Volume: +9 points\n\n::note\nSay the number out loud.\n::\n\n---\n\n# What did not change\n\n- Churn is flat at 4.1%\n- Support load is flat at 900 tickets a week",
  "filename": "q3-review.md",
  "mime_type": "text/markdown",
  "prompt": "Q3 review deck for Monday, from the user's chat message"
}
```

Response `data`:

```jsonc
{
  "asset_id": "0b8a7f31c1e24d5e9a7c2f0b1d3e4a56",
  "asset_url": "/api/v1/assets/0b8a7f31c1e24d5e9a7c2f0b1d3e4a56.md",
  "filename": "q3-review.md",
  "mime_type": "text/markdown",
  "byte_size": 486
}
```

### Call 2 — render it to PowerPoint

`asset_id` is copied verbatim out of call 1. Nothing is re-derived, nothing is
searched for.

```jsonc
{
  "action": "create_derivative",
  "asset_id": "0b8a7f31c1e24d5e9a7c2f0b1d3e4a56",
  "format": "pptx"
}
```

Response `data` (the keys that matter):

```jsonc
{
  "derivative_id": "1c4e9b02d7f84a6bb3e5c9d1a70f28be",
  "parent_id": "0b8a7f31c1e24d5e9a7c2f0b1d3e4a56",
  "format": "pptx",
  "mime_type": "application/vnd.openxmlformats-officedocument.presentationml.presentation",
  "byte_size": 24011,
  "asset_url": "/api/v1/assets/1c4e9b02d7f84a6bb3e5c9d1a70f28be.pptx"
}
```

### What the user sees

> Here's the deck for Monday:
>
> <div class="inline-flex max-w-120 my-[0.6rem] rounded-lg border border-foreground/10 bg-muted"><a class="flex min-w-0 items-center gap-2.5 rounded-lg px-3 py-2 text-inherit no-underline transition-colors hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-[-1px] focus-visible:outline-ring spora-file-card__glyph" href="/api/v1/assets/1c4e9b02d7f84a6bb3e5c9d1a70f28be.pptx"><span class="min-w-0 flex-auto truncate font-medium">q3-review.pptx</span><span class="shrink-0 text-xs text-muted-foreground">23.4 KB</span></a></div>
>
> The Markdown source is kept alongside it, so I can re-render an updated
> version without you re-pasting anything.

Four things to get right, because they are the common ones:

- **The reply does not contain `[q3-review.pptx](…)`.** A hand-rolled markdown
  link loses the card, and the card is what the user is used to.
- **`create_media` was called once.** The `asset_id` is reused for call 2, for
  any later `get_media`, and for a re-render after an edit. A second
  `create_media` is a duplicate asset in the user's library, and it would
  invalidate the URL already handed out.
- **The theme is a deck theme.** `corporate-dark` is one of the five the
  renderer ships. `corporate` is a *document* theme — a deck naming it fails
  with a message listing the five that do exist.
- **Only `create_media` asks the operator to approve.** `create_derivative` is
  auto-approved, so the render comes back without a prompt. Say nothing about
  the deck before the approval on the first call resolves.

## Example 2 — a slide with two columns

The layout gives the slide its columns; `::column` moves the blocks into the
next one. A slide with two columns needs a layout that has two, and **one**
marker — a second one is refused, because there is no third column to move
into.

```markdown
---
theme: corporate-dark
layout: two-column
---

# Two things changed

## Pricing

- +3 points, EU and US
- Renewals only

::column
## Volume

- +9 points
- New and existing
```

The marker takes no argument, and the `##` beside it is an ordinary heading:
the block is in the right column because the marker is above it, not because of
anything about the heading.

**A two-column slide that writes no `::column` fills the first column and
leaves the rest empty.** That is honest — it asked for a layout with columns
and wrote one of them — but it is rarely what the author meant.

## Example 3 — a slide you place yourself

A **free shape** is one you place yourself. Its attributes are separated by a
**space**, and a comma is not a separator: `width=40%, height=20%` is one
value of `40%,` and one attribute nobody reads.

```markdown
---
theme: corporate-dark
layout: blank
---

::rectangle{left=0 top=0 width=100% height=100% fill=panel layer=back}

::circle{left=8% top=10% width=6% height=6% fill=accent1}

:::content{left=20% top=40% width=60% height=40% font-size=20}
## Q3 review
- 4,182 signups
:::
```

Three rules that catch people out:

- **A box lies within the slide**, and one that does not is refused with the
  numbers rather than quietly clamped somewhere else. Full bleed is still
  reachable — `left=0 width=100%` is exactly the edge.
- **`fill` and `gradient` together are refused**, rather than one of them
  being dropped.
- **A shape is painted with a colour or with a gradient.** A gradient is
  written as its stops, and the angle is CSS's: 0° is to the top, 90° to the
  right.

## Example 4 — a `.pptx` attached to the chat, answered with no tool call

**User** attaches `q3-review.pptx` and asks: "what were the slides about?"

The archive extracted the upload into a Markdown derivative, and that text is
inlined into the model's context. **There is no tool call in this exchange at
all** — not `get_media`, not `get_source`.

```text
user: [attachment: q3-review.pptx]
      What were the slides about?
```

The answer is read straight out of the attached deck's extracted text:

> The deck had three slides: a title slide, one on what changed — pricing up
> three points and volume up nine — and one on what did not change, with churn
> flat at 4.1% and support load flat at 900 tickets a week.

Two things to get right here:

- **No `media` call, and no `create_media` / `create_derivative` round trip.**
  The text is already in context. A round trip would re-parse the deck and
  give you the same words back.
- **A deck read back carries no pictures at all** — not a reference, not the
  alt text. The picture is still inside the `.pptx`; the extracted Markdown is
  all a read-back gives you. Do not tell the user a slide showed a chart.

## Example 5 — editing an existing deck

**User:** "Take the deck I attached, drop the churn slide, and give me the
PowerPoint file back."

Do **not** convert the `.pptx` forward and re-render. Read it, edit the
Markdown, and render a new deck from that — the round trip is lossy, and
editing the `.pptx` means editing a lossy copy of the user's text.

```text
1. the attached deck's extracted Markdown is already in context
2. edit the Markdown — drop the third slide, nothing else
3. media(action: "create_media", content: "<edited deck markdown>",
          filename: "q3-review-v2.md",
          mime_type: "text/markdown", prompt: "Q3 review deck without the churn slide")
4. media(action: "create_derivative", asset_id: "<asset_id from step 3>", format: "pptx")
```

The edited Markdown is a *new* asset under a *new* filename, so both versions
coexist and the original is untouched. The derivative is idempotent on
`(parent_id, format, producer_plugin, producer_operation)`, so if step 4's
result was ever lost to an ambiguous failure, re-running step 4 is the correct
recovery.
