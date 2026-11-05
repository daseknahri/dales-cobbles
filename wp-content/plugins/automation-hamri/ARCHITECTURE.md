# Automation Hamri (build-v9) — architecture

*Verified against build-v9 9.46.x. The cited-by-line reference (data model, every AJAX action, flows, gotchas,
release procedure) is [`docs/components/build-v9.md`](../docs/components/build-v9.md); this file is the short module map.*

`wp-automator-pro.php` was historically a single ~6,900-line file. Since **9.18.0** it is a slim bootstrap
(~890 lines, most of it the dead licence block and the HTTP/SSRF layer) plus per-concern modules under
`includes/` — 19 of them as of 9.46. The 9.18 split was a verbatim, order-preserving extraction; later modules
(9.4x social posting, admin UI kit) were added the same way.

## Load model

`wp-automator-pro.php` defines the plugin header, all `WPAP_*` constants (`WPAP_VERSION`, `WPAP_TABLE`,
`WPAP_TOMB_TABLE`, `WPAP_DB_VERSION`), the (dead) licensing block, the HTTP/SSRF transport layer,
activation/DB-upgrade, and the admin-menu registration — then `require_once`s each module in this order:
`admin-ui` → `admin` → `scheduling` → `media` → `publishing` → `internal-links` → `apply-rewrites` → `distribution` →
`automation` → `ai-content` → `seo-schema` → `ads` → `settings-io` → `editor-tools` → `facebook-page` (which loads
`facebook-page-admin`) → `social-full` → `instagram` → `pinterest`. Every module **self-registers its own hooks**
(`add_action`/`add_filter`/`add_shortcode`) next to the handler, so load order only matters for functions called at
load time (`admin-ui.php` first so `wpap_help_tip()` exists) — never for hook callbacks, which fire after the full
plugin is loaded. The plugin shares the table `wpap_generated_posts`, the options `wpap_settings` / `wpap_db_version`
and the `wpap_` prefix with the `build-final` product, so the two must never be active on the same site and the DB
version gate is monotonic (`>=`).

## Modules

| File | Concern |
|------|---------|
| `wp-automator-pro.php` | Bootstrap: header, constants, **licensing (dead — `wpap_is_licensed()` always true)**, HTTP/SSRF transport and TLS pinning for the AI hosts, AI rate limit, `wpap_bulk_max_bytes`/`items` (2 MB / 300), `wpap_create_table` / `wpap_ensure_meta_dedup_index` / `wpap_maybe_upgrade_db` / `wpap_activate`, admin-menu + asset registration, and the module `require` block. |
| `includes/admin-ui.php` | Settings-page UX kit (9.46): `wpap_help_tip()` hover help, collapsible `<details class="wpap-group">` groups, sticky "On this page" nav, shared CSS and the help copy. |
| `includes/admin.php` | Admin UI — dashboard shell, the full settings page + its single save handler (with the inline ad preview/estimator JS), and the Bulk-ZIP page. |
| `includes/scheduling.php` | Ordered / random / **`drip:N`** scheduling (`wpap_compute_schedule`, `wpap_parse_schedule_window`), public (pretty) permalink for non-published posts, content→pages splitting (`parts`; respects an existing `<!--nextpage-->`, does **not** convert a `[nextpage]` token). |
| `includes/media.php` | Image upload fallback, WebP conversion (memory pre-flight), **SSRF-guarded** remote image import + dedup, local-file import, clean-media (SEO filename + EXIF scrub), per-hop-safe download. |
| `includes/publishing.php` | The **bulk-import publishing pipeline** (the product's main tool): `wpap_publish_article` (one ready-made item → post, with per-type schema routing, `Parent > Child` category resolution, featured + Facebook image, SEO meta, curated links, Hub row), the JSON bulk handler (`wpap_ajax_bulk_publish_posts`), the **Bulk-ZIP** handler (`wpap_ajax_bulk_publish_zip` — upload→extract→publish with path-traversal / extension / entry-count / uncompressed-size guards, a concurrency lock and in-article image import), the Hub "Bulk Import Rows" box, JSON export, the REST route `/wpap/v1/publish`, near-duplicate cleanup, and `wpap_ajax_process_title`. Spec: `BULK-IMPORT-CONTRACT.md`. ⚠️ **`process_title` nests the AI hook/title generators (`wpap_generate_hook_via_ai`, `wpap_build_clean_hook`) — do NOT edit the AI portions.** |
| `includes/internal-links.php` | In-content internal linking: `[[link:slug\|anchor]]` writer-token resolution (`wpap_resolve_internal_links`) with a self-healing forward-ref marker + kses allow-list guard, auto keyword cross-linking (`_wpap_keywords` → `wpap_keyword_index` / `wpap_autolink_content` / `wpap_auto_keyword_link_run`), backfill from tags, nested-link repair. Baked at publish via `wpap_internal_links_bake()` (auto-run after Direct Publish, Bulk ZIP and Apply Rewrites — not after REST/Sheet) and exposed as Settings buttons. NOT the AI link injector. |
| `includes/apply-rewrites.php` | "Apply Rewrites": in-place title/body rewrite of live posts from an uploaded JSON, with per-post backup and one-click revert (admin session only). |
| `includes/distribution.php` | Distribution Hub list/stats queries, first-comment templates, mark-posted, delete/bulk-delete/cleanup, row lifecycle on trash/untrash/delete, tombstones, cache purge, image proxy. |
| `includes/automation.php` | Google-Sheet CSV automation cron (SSRF-pinned fetch, parse, dedup, run, give-up, alert), atomic lock helpers, content-key. |
| `includes/ai-content.php` | ⚠️ **AI content + image generation pipeline — do NOT edit.** `wpap_generate_content(_gemini)`, markdown/cleanup, `wpap_generate_image_*`, `wpap_save_image_to_library`. |
| `includes/seo-schema.php` | Front-end `wp_head`: OG/Twitter + meta description, and a **connected schema `@graph`** (WebPage → Article → Person author → Organization publisher, plus a primary ImageObject + BreadcrumbList, all `@id`-linked). Recipe card + Recipe schema when a post carries `_wpap_recipe_*`. Opt-in FAQPage. Related-posts block (curated `related` first, else auto-by-category). noindex enforcement + sitemap exclusion, Facebook identifiers, `/llms.txt` + `/ai.txt`. Defers to a dedicated SEO plugin, and defers the recipe card / related UI to the companion theme, when present. |
| `includes/ads.php` | `ads.txt`, IndexNow (opt-in), ad zones (shortcode + block), AdSense head loader, in-content ad injection with density guard + storage-time length cap. |
| `includes/settings-io.php` | Settings export/import (secrets excluded) + the admin dashboard health widget. |
| `includes/editor-tools.php` | Gutenberg/Classic "Author Tools" — meta box + derived-field save (`wpap_ed_*`). |
| `includes/facebook-page.php` (+ `facebook-page-admin.php`) | Facebook **Page** auto-poster (Graph API) and the shared 15-minute scheduler tick (`wpap_fbp_cron`) that also drives Instagram. Formats: photo + hook with the link in the first comment, or one of two full-article styles (`social-full.php`). Claim-before-call, error classification, pause / back-off. |
| `includes/social-full.php` | Article → plain text for the two "full article" Facebook styles. |
| `includes/instagram.php` | Instagram posting through the Page token (hidden Page photo → container → publish). |
| `includes/pinterest.php` | Pin data derivation, optional RSS feed (`/feed/pinterest/`), bulk-upload CSV. No Pinterest API poster in this build. |

## Do-not-touch (AI generation pipeline)

By project constraint the AI generation code is off-limits. It lives in
`includes/ai-content.php` (entirely) and inside `wpap_ajax_process_title` in
`includes/publishing.php` (the nested `wpap_generate_hook_via_ai` / `wpap_build_clean_hook`).
Edit everything else freely.

## Scale notes (9.18.0)

- Dedup lookups (`_wpap_source_key`, image imports keyed on `_wpap_source_image_hash`) are
  index-backed by a composite `wp_postmeta (meta_key(32), meta_value(191))` index added on
  upgrade (`wpap_ensure_meta_dedup_index`, idempotent + best-effort).
- Orphan-Hub cleanup is a single batched set-based `DELETE` (no fetch-all + N+1).
- Dashboard stats (5-min) and the related-posts block (12-h) are transient-cached.
  Because a related block embeds *other* posts, invalidation is site-wide: any plugin-post
  publish/trash/delete calls `wpap_invalidate_content_caches()`, which clears the dashboard
  transient and bumps `wpap_rel_ver` (folded into every related cache key), so every block
  recomputes at once — no stale links to removed posts.
- The `wpap_automation_seen` map is written once per cron run; ad code is length-capped at
  storage; the Hub count drops its `wp_posts` join on the default (unfiltered) view.

### Extreme-scale items (implemented in 9.19.0)

- **Hub list ordering** no longer filesorts a computed joined-column expression. `wpap_hub_ordered_rows()`
  (distribution.php) fetches "published first, then newest" as two segments, each ordered by the
  primary key `t.id` (index-backed, no filesort), with straddle math preserving the exact
  pagination. Used by both the Hub list and the JSON export. (Chosen over denormalizing
  `post_status` into the Hub table, which couldn't be kept correct without editing the off-limits
  AI handler where one of the row-inserts lives.)
- **Tombstones** moved from the O(n) `wpap_automation_deleted_keys` option to the indexed
  `wpap_tombstones` table (`WPAP_TOMB_TABLE`): recording a deletion is one `INSERT IGNORE`
  (`wpap_automation_tombstone_key`), the dedup check is an indexed `SELECT`
  (`wpap_automation_key_is_done`), and existing tombstones backfill once on upgrade
  (`wpap_migrate_tombstones_to_table`, DB v4). The legacy option is left in place for rollback.

## Bulk-import publishing pipeline (the main tool)

A batch of **ready-made** items is published with no AI call at import time; each item is one
post. Entry points (`includes/publishing.php`, all `manage_options` + nonce):

- **`wpap_ajax_bulk_publish_posts`** — a JSON array (or `{items:[…]}`) pasted in Direct Publish
  (≤ 2 MB / 300 items per request, one atomic run lock, ordered spread over the schedule window).
- **`wpap_ajax_bulk_publish_zip`** — an uploaded `.zip` bundle (`posts.json` + `images/`, images
  referenced by in-zip path; `<img src="images/…">` inside articles are imported too). Extracts to a
  private temp dir under `uploads/` (`Deny from all`) with: a per-entry name guard (absolute / `..` /
  drive-letter / NUL rejected), an extension allowlist (json + images only), entry-count +
  uncompressed-size caps (zip-bomb), a `realpath`-inside-root check on every resolved image, an
  atomic `INSERT IGNORE` **concurrency lock** (`wpap_atomic_lock_acquire`; a retry can't
  double-publish the bundle), and a `register_shutdown_function` cleanup so an uncatchable OOM can't
  leak the extract dir. (build-final's later per-file-size, compression-ratio and symlink-entry
  guards are *not* ported here.)
- **`wpap_rest_publish`** — `POST /wp-json/wpap/v1/publish` (Application Password, `manage_options`),
  ≤ 2000 items, hours or `drip:N`.
- **`wpap_automation_run`** (`automation.php`) — the hourly Google-Sheet cron; it maps only a fixed set
  of columns (title, content, image, hook, category, id, SEO fields, tags).
- **`wpap_ajax_bulk_import_distribution`** — the Hub's "Bulk Import Rows" box for Facebook poster rows
  (`{caption, comment, imageUrl}`). It **creates one published post per row** whose body is only the
  caption (no URL matching, no dedup) — never use it for articles.

The first four funnel each item into **`wpap_publish_article($item, $opts)`**, which:

1. Inserts the post — title, `kses`'d content split into `parts` (an existing `<!--nextpage-->` is kept;
   a `[nextpage]` token is converted first by `wpap_apply_content_pagebreaks` — 9.47.0+), `post_excerpt` = meta description.
2. **Category** — `wpap_resolve_category_path`: a bare name, a numeric id, or a `Parent > Child`
   path (each level created lazily; the root segment is scoped to top-level so a bare name can't
   bind to a nested same-name term).
3. **Featured image** — local (bundle) or remote (SSRF-guarded) sideload, with a descriptive
   per-item `image_alt` overriding the title default.
4. **Per-type schema routing** — `type:"recipe"` (or ingredients+steps present, *unless* an
   explicit `article`/`guide`/`story` type says otherwise) → `_wpap_recipe_*` meta (ingredients,
   steps, `wpap_parse_duration_to_minutes` on prep/cook/total, yield) → **schema.org/Recipe**.
   Requires BOTH lists to survive or it falls back to a valid **Article** (never a broken Recipe).
   Everything else → Article.
5. **SEO + linking** — `wpap_set_seo_meta` (description/title/keyword), tags (≤15), curated
   internal links (`_wpap_related_manual`), `_wpap_smart_link`, content-hash dedup anchors, and
   the Distribution-Hub row.

The item shape is the single source of truth in **`BULK-IMPORT-CONTRACT.md`**, shared with the
content-generation source (which supplies *raw material*; category/type/recipe/alt enrichment is
done in the processing layer, not the generator).

## SEO & structured data

Emission is layered so each part is self-contained and defers cleanly:

- **Plugin** (`seo-schema.php`, on `_wpap_smart_link` posts): OG/Twitter + meta description, and a
  connected `@graph` — WebPage → Article → **Person** (author, with `url`/avatar/`description`/
  `sameAs` for E-E-A-T) → **Organization** (publisher) + primary ImageObject + BreadcrumbList,
  all `@id`-linked. The `#organization` / `#website` `@id`s reuse the site convention so they
  **merge** with a site-level Organization/WebSite graph. The Article node is omitted when a
  Recipe will render as the page's primary entity.
- **Theme** (companion viral-reader): the Recipe card + Recipe JSON-LD from `_wpap_recipe_*`, and
  the related-posts UI — the plugin defers both to the theme when its functions exist.
- **Site level** (optional per-site mu-plugin): Organization + WebSite (SearchAction); merges with
  the plugin graph by shared `@id`.
- **Deferral**: with a dedicated SEO plugin (Yoast / Rank Math / AIOSEO / SEOPress / SEO Framework)
  active, the plugin's meta/OG/schema step stands aside entirely to avoid duplication.

Deliberately **not** emitted: **HowTo** schema (Google retired it in 2023). **FAQPage** is built from a
post's own `<h2>…FAQ…</h2><h3>Q</h3><p>A</p>` section but is **default OFF** (a site opts in with the
`wpap_faq_schema_enabled` filter), because Google restricted FAQ rich results to authoritative government/health
sites in 2023, so it rarely renders for a typical site.
