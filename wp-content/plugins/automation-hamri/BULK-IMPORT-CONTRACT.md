# Bulk-import contract — Automation Hamri (build-v9)

The single source of truth for the JSON the **content-writing session** produces and the
**plugin** consumes. Follow this and every post lands with correct categories, per-type schema,
and full on-page SEO — automatically, with no manual editor step.

Version of this contract: **1.2** (verified against plugin 9.48.0, `includes/publishing.php`
`wpap_publish_article`; first written for ≥ 9.22.0). Fields are tagged with the plugin version that introduced
them only where it matters.

> **This is the build-v9 (kepoli-family) contract. build-final (panrecipe network) has a DIFFERENT one** —
> `build-final/POST-CONTRACT.md`. The two share many field names but differ in places that bite: item `slug`,
> `canonical`, `recipe{}` and `publishAt` are **build-final only** (`id` is read by both builds as the dedup key
> since build-v9 **9.48.0**); `type`, flat recipe fields, `fbImage`,
> `related`, `image_alt` and `course` are **build-v9 only**; and `[nextpage]` is converted by build-final and,
> since **9.47.0**, by build-v9 too (see §2.4). Never send one build's batch to the other site unchanged.

---

## 1. How it's submitted

Four routes, all funnelling each item into the same `wpap_publish_article()` (no AI is called at import time):

| Route | Entry | Notes |
|---|---|---|
| **Direct Publish** (Dashboard) | `admin-ajax.php?action=wpap_bulk_publish_posts` | JSON `items`; ≤ 2 MB and ≤ 300 items per request (`wpap_bulk_max_bytes` / `wpap_bulk_max_items`, filterable). `schedule_window` accepts hours **or `drip:N`** (since 9.47.0 — through 9.46 it was cast to float, so `drip:N` published immediately). Ordered even spread across the window. Ends with the internal-link bake + cache purge. |
| **Bulk ZIP** (`wp-automator-pro-bundle`) | `action=wpap_bulk_publish_zip` | `posts.json` + image files; ≤ 300 items; accepts hours **or `drip:N`**. Images (`image`/`imageUrl`/`image_url`, `fbImage…`) may be paths inside the zip; `<img src="images/…">` in `content` is imported too (9.42, ≤ 30 per post). A `pinImage` path inside the zip is **not** resolved — use a URL. Zip limits: ≤ 64 MB upload, ≤ 3000 entries, ≤ 512 MB uncompressed, and since 9.48.0 ≤ 64 MB per file, no compression ratio > 200:1 on a file > 1 MB, no symlink entries (any violation refuses the whole zip). |
| **Google Sheet** (Settings → Automation) | hourly cron | Reads only these columns: `title`, `content`/`body`, `imageUrl`/`image_url`/`image`, `hook`/`comment`/`caption` (all three feed the *hook*), `category`, `id`, `metaDescription`/`meta_description`/`description`, `metaTitle`/`meta_title`/`seo_title`, `focusKeyword`/`focus_keyword`/`keyword`, `tags`. Nothing else (no `type`, recipe, `keywords`, `slug`, pins). 24 MB fetch cap with truncation guard (9.47.0+; 5 MB before), hours-only schedule. |
| **REST** `POST /wp-json/wpap/v1/publish` | Application Password, `manage_options` | `{items, num_parts?, schedule_window? (hours or "drip:N"), category?}`; ≤ 2000 items. Does not run the link bake or cache purge. |

```
POST admin-ajax.php
  action = wpap_bulk_publish_posts
  nonce  = <wpap_nonce>
  items  = <JSON string: an array of item objects, or {"items":[ ... ]}>
  num_parts        = 1        (optional; default pages per post, 1–10)
  schedule_window  = 0        (optional; hours (0–168) to spread publish times over, 0 = immediate, or "drip:N" ≈ N/day — 9.47.0+)
  default_category = ""       (optional; applied to any item with no `category`)
```

Each item is **fatal-isolated**: one bad row is skipped, the batch continues. Direct Publish and Bulk ZIP hold an
atomic run lock (a second concurrent run is refused).

---

## 2. Item shape

Only the keys below are read; anything else in an item is ignored. The first alias that is **present** wins, even
if empty.

### 2.1 Common fields (every type)

| Field | Type | Required | Notes |
|---|---|---|---|
| `type` | string | rec. | `recipe` \| `guide` \| `story` \| `article` (alias key `kind`). Decides schema. Omit and it defaults to `article` — **unless** `ingredients`+`steps` are both present, which auto-promotes to `recipe`. An explicit non-recipe type (`article`/`guide`/`story`/`post`/`page`) is authoritative. |
| `title` | string | ✅ | The H1 / post title. **≤ 60 characters** for SEO. Alias: `caption`. If both title and body are empty the item is skipped (`wpap_empty`); an empty title with a body is derived from the first 8 words. |
| `content` | string (HTML) | ✅ | The article body (aliases `post_content`, `body`). `<p>`, headings, lists allowed (kses-sanitized unless the user has `unfiltered_html`). **≥ 400 words** of original text (AdSense floor). For **recipes**, this is the narrative/intro prose only — do **not** repeat the ingredient/step lists here; they render from the structured fields below. Page breaks: see §2.4. |
| `category` | string | ✅ | `Recipes`, `Tips`, or `Stories` (see §3). May be a hierarchy path `Recipes > Soups & Stews`, or an existing numeric term id. Created if missing. |
| `tags` | string[] \| "a, b" | rec. | 3–8 specific tags. Capped at 15. |
| `metaDescription` | string | rec. | **150–160 characters.** Aliases: `description`, `excerpt`. Stored as the excerpt + written to the SEO plugin; auto-derived from `content` if omitted. ⚠ **`meta_description` (snake case) is NOT read from JSON** — it is only a *Sheet column* alias. Earlier versions of this document listed it as the primary key; it was never read by `wpap_publish_article`. |
| `metaTitle` | string | opt. | SEO `<title>` if you want it different from `title`. **≤ 60 chars.** Alias: `seo_title`. |
| `focusKeyword` | string | opt. | Primary keyword (fed to Yoast/Rank Math/SEOPress/SEO Framework if active). Alias: `keyword`. |
| `noindex` | bool | opt. | Keep this post OUT of search (thin / utility / near-duplicate content). Evaluated with PHP `FILTER_VALIDATE_BOOLEAN` (`true`/`"1"`/`"yes"`/`"on"` → true; `false`/`"0"` → explicitly re-indexable). Enforced via WordPress core's robots API even with **no SEO plugin**, mirrored into a supported SEO plugin's flag, and dropped from the core sitemap (9.31). Omit to leave the post as-is (indexable). |
| `imageUrl` | string (URL) | ✅ | **Blog** featured image; sideloaded + set as featured. Aliases: `image_url`, `image`. (Bulk ZIP: a path inside the zip is also accepted; the first non-empty of `image`, `imageUrl`, `image_url` is used.) A failed download publishes the post without a featured image (reported as a warning) — build-v9 has no "require image" guard. |
| `fbImage` | string | opt. | **Facebook** image — the image the Distribution Hub exports for FB posting, when it should differ from the blog image. URL, or a path inside the bundle ZIP (e.g. `fb-images/x.jpg`). Aliases: `fbImageUrl`, `facebook_image`, `fb_image`. **Omit it and the blog image is used for both.** A local (zip) FB image is hosted as an attachment; a remote URL is stored as-is. Stored in `_wpap_fb_image_url`; the Hub export's `imageUrl` resolves to the FB image → blog image → featured. |
| `image_alt` | string | rec. | Descriptive alt text for the featured image (Google Images / a11y). Defaults to `title`. Aliases: `imageAlt`, `alt`. |
| `hook` | string | opt. | Facebook caption for the Distribution Hub. Aliases: `fb_text`; if the key is absent it falls back to `comment`, then the title. |
| `comment` | string | opt. | First-comment template with `{{link}}` where the post URL goes. Alias: `fb_comment`. A bare URL is treated as a link, not a template (not stored), and a `comment` identical to the caption is not stored either. |
| `pinImage`, `pinTitle`, `pinDescription` | string | opt. | Pinterest overrides (9.44.0): `pinImage` = an http(s) URL used as-is (not downloaded; a non-URL is silently ignored); title ≤ 100 / description ≤ 500 are trimmed when the feed/CSV is built. |
| `related` | string[] | opt. | Curated internal links — slugs of sibling posts to link first in the related block (auto-by-category fills any remaining slots). Array or comma/newline string; up to 10. Alias: `related_articles`. |
| `keywords` | string[] | opt. | Phrases this post should be the internal-link **TARGET** for. The auto-link pass links the first mention of each phrase in **other** posts to this one (bounded, tag-aware, idempotent). Array or comma/newline string; up to 12. Alias: `link_keywords`. Distinct from `focusKeyword` (which feeds the SEO plugin). |
| `parts` | int | opt. | Split the body into N paginated pages (1–10) at paragraph/heading boundaries. Ignored if the body already contains `<!--nextpage-->`. |

**Not read by build-v9** (silently ignored — these are build-final fields): `slug` / `post_name`, `canonical`,
`recipe{…}` (nested object), `publishAt`. In particular:
- **`slug` is ignored** — the permalink is whatever WordPress derives from the title (`sanitize_title(title)`, made
  unique). Since `[[link:slug]]` targets are resolved by post slug, sibling links only work when the slug WordPress
  derives from the target's `title` equals the slug you wrote. Pin permalinks afterwards if they must differ.
- **`id`** (string, recommended) is read since **9.48.0** only as the dedup key — stored as `_wpap_source_key`;
  re-sending the same `id` is skipped on every route (§6). It does not set the slug. Through 9.47 it was ignored.

### 2.2 Inline internal-link tokens (in `content`)

Anywhere in the body you may write `[[link:some-slug]]` or `[[link:some-slug|anchor text]]`. On publish it becomes
a real link to the post whose slug is `some-slug` (default anchor = that post's title). If the target isn't
published yet (e.g. it's later in the same batch), it is stored as an invisible marker and auto-upgraded once the
target is live — the Direct Publish / Bulk ZIP run does this itself at the end of the batch (the "link bake"), so you
can cross-link freely within a batch without worrying about order. (REST and Sheet publishes do not run the bake —
use the Settings → Internal linking buttons.) A token pointing at the item's own title-derived slug is left as
plain text. This is the writer-driven counterpart to the `keywords` field's automatic linking.

### 2.3 Recipe-only fields (`type: "recipe"`) — flat, top-level

Required for a recipe to earn a valid **schema.org/Recipe** rich result. (build-v9 does **not** read a nested
`recipe{…}` object — that is the build-final shape.)

| Field | Type | Notes |
|---|---|---|
| `ingredients` | string[] | One ingredient per element. e.g. `["1 large cabbage", "500g ground pork"]`. Up to 60 lines kept. |
| `steps` | string[] | Ordered instructions, one step per element. Up to 60 lines kept. |
| `prep` | string\|int | Prep time. `"40 min"`, `"1 hr 30 min"`, `"PT40M"`, or minutes as an int. Alias: `prepTime`. |
| `cook` | string\|int | Cook time (same formats). Alias: `cookTime`. |
| `servings` | string | Yield, e.g. `"6 servings"`. Alias: `yield`. |
| `course` | string | schema.org `recipeCategory` — the **course / meal type**, NOT the blog category. e.g. `"Main course"`, `"Dessert"`, `"Soup"`, `"Home remedy"`. Optional (recommended). Alias: `recipeCategory`. |

`total` time is computed from `prep + cook`, or supply an explicit `total`/`totalTime` to override.
`ingredients`/`steps` may be an array **or** a newline-delimited string. A recipe needs **both** lists to survive —
a recipe with missing/partial data publishes as a valid Article instead of a broken Recipe. An explicit non-recipe
`type` (`article`/`guide`/`story`) is authoritative: such an item never gets Recipe markup even if it carries these
fields.

### 2.4 Page breaks — `[nextpage]` (converted since 9.47.0)

How the code handles pagination today (`wpap_publish_article` → `wpap_split_content_into_parts`,
`includes/scheduling.php`):
- A literal **`<!--nextpage-->`** in the body is respected as-is (and survives kses: the plugin re-writes
  `post_content` with a direct DB update when markers are present).
- The `parts` field (or the "Pages per post" / `num_parts` option, default 1) splits an un-paginated body into N
  pages at `</p>` / heading boundaries; a body that already contains `<!--nextpage-->` is returned untouched.
- A **`[nextpage]` text token** (also `[next-page]`, case-insensitive) is converted to `<!--nextpage-->` by
  `wpap_apply_content_pagebreaks` (`includes/scheduling.php`, ported unchanged from build-final), which runs first in
  `wpap_publish_article` — so every route (Direct Publish, Bulk ZIP, Sheet, REST) handles it, and an explicit token
  wins over `parts`. **Through 9.46.x it was NOT converted** and the literal `[nextpage]` was published; posts made
  then are not rewritten retroactively. Batches from `batch-enrich/repaginate.mjs` now work on both builds.

---

## 3. Taxonomy (kepoli)

Flat three-pillar, hierarchy-ready. Assign exactly one of:

| `category` | `type` | Schema | What goes here |
|---|---|---|---|
| **Recipes** | `recipe` | Recipe + Breadcrumb | A dish with ingredients + steps. |
| **Tips** | `guide` | Article + Breadcrumb | How-to guides, techniques, storage, pantry, ingredient guides. |
| **Stories** | `story` | Article + Breadcrumb | Narrative / personal / editorial food writing. |

(Per-site pillar lists live in `docs/sites/`; kepoli's later structure differs from this original three.)

**Growing later (no plugin change):** when a pillar gets deep enough to split, send a path instead
of a bare name — the leaf is created under its parent and assigned:

```
"category": "Recipes > Soups & Stews"
"category": "Recipes > Baking & Desserts"
"category": "Tips > Storage & Pantry"
```

Do **not** introduce sub-categories until a pillar has enough posts to justify one (thin
categories hurt SEO). Start flat.

---

## 4. What the plugin does for you (so SEO is "automatic")

For every item, on publish:
- Creates/assigns the **category** (and any parent levels).
- Sideloads the **featured image** with your `image_alt`.
- Writes the **meta description** (`post_excerpt` + SEO plugin) and `metaTitle`/keyword.
- Sets **tags**.
- **type = recipe** → writes `_wpap_recipe_*` → the recipe card (the theme's `vr_recipe_card`, else the plugin's own)
  and **Recipe** JSON-LD (ingredients, instructions, `prepTime`/`cookTime`/`totalTime`, yield, image, author).
  **Otherwise** → **Article** JSON-LD. Both get **BreadcrumbList**, canonical, and Open Graph/Twitter with real image
  dimensions — unless a real SEO plugin is active, in which case the plugin's `<head>` output stays silent and the
  SEO plugin's fields (filled by the plugin) are used. (Site-level Organization + WebSite come from the site's own
  mu-plugin.)
- Registers a Distribution Hub row for Facebook workflows.

You do **not** emit any schema yourself. You only supply the structured fields above.

---

## 5. SEO rules the content must satisfy

1. `title` ≤ 60 chars; `metaDescription` 150–160 chars.
2. `content` ≥ 400 words of original text; recipes keep ingredient/step lists **out** of `content`.
3. Every item has an `imageUrl` + a descriptive `image_alt`.
4. Every recipe supplies `ingredients`, `steps`, `prep`, `cook`, `servings` (or the Recipe rich
   result is incomplete).
5. 3–8 specific `tags`.
6. One `category` from §3.

---

## 6. Idempotency

- **Item `id` = durable dedup key (since 9.48.0, same as build-final).** When the caller passes no key (Direct
  Publish, Bulk ZIP, REST), the publisher stores the item's `id` as `_wpap_source_key` and **skips** any later item
  with the same `id` while a non-trashed post carries it (`skipped: already published (id/key exists as post #N).`,
  code `wpap_duplicate`). Always on, independent of the title guard; the same `id` twice inside one batch also
  yields one post. Items **without** an `id` are not deduplicated by this guard. A trashed original does not block
  a re-publish. Posts published by ≤ 9.47 carry no key, so a batch first sent before 9.48.0 can still duplicate
  once if re-sent. → **Always send a stable `id`** (the slug is a good choice).
- **`skip_dupe_titles`** (Settings → Content options) is **OFF by default** in build-v9 (build-final: ON) and only
  applies when the caller supplies no `source_key` (i.e. Direct Publish / ZIP / REST — not the Sheet cron; an
  adopted item `id` does not switch it off). Turn it on for sites fed by batches that may overlap the catalogue
  under different ids. It matches an exact title among non-trash `publish/future/draft/pending/private` posts.
- The **Sheet automation** de-duplicates on its row key (`id` column or a title+content hash), the stored
  `_wpap_source_key` / `_wpap_source_alt_key`, a "seen" cache, tombstones for deleted posts and a 3-strike give-up.
- After the fact: Settings → Duplicate cleanup finds *reworded* duplicates (near-duplicate title tokens) and trashes
  the newer ones (recoverable); `batch-enrich/check-against-live.mjs` is the pre-flight check.

---

## 7. Examples

Recipe:
```json
{
  "type": "recipe",
  "title": "Classic Cabbage Rolls",
  "category": "Recipes",
  "content": "<p>Tender cabbage leaves around a savory pork-and-rice filling, slow-simmered in tomato until everything turns meltingly soft. (400+ words of narrative…)</p>",
  "metaDescription": "Cabbage rolls made the slow way: tender leaves around a savory pork-and-rice filling, simmered in tomato until soft.",
  "metaTitle": "Cabbage Rolls: The Slow-Simmered Way",
  "tags": ["cabbage rolls", "comfort food", "make ahead"],
  "imageUrl": "https://cdn.example.com/cabbage-rolls.jpg",
  "image_alt": "Cabbage rolls in a pot with tomato sauce",
  "servings": "6 servings",
  "course": "Main course",
  "prep": "40 min",
  "cook": "2 hr",
  "ingredients": ["1 large cabbage", "500g ground pork", "1 cup rice", "2 cups tomato sauce"],
  "steps": ["Blanch the cabbage leaves", "Mix pork and rice", "Roll the filling", "Simmer in tomato until soft"],
  "keywords": ["cabbage rolls"],
  "hook": "The slow way to cabbage rolls, and why the tomato goes in last.",
  "comment": "{{link}}"
}
```

Guide:
```json
{
  "type": "guide",
  "title": "How to Stock a Practical Pantry",
  "category": "Tips",
  "content": "<p>(400+ words of practical guidance…)</p>",
  "metaDescription": "A practical pantry checklist for weeknight cooking — the staples that turn into dinner on a slow night.",
  "tags": ["pantry", "meal planning", "kitchen basics"],
  "imageUrl": "https://cdn.example.com/pantry.jpg",
  "image_alt": "A stocked pantry shelf of staples"
}
```
