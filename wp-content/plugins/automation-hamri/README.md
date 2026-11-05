# Automation Hamri — build-v9 (full-featured build, 9.46+)

Bulk-import publisher + SEO / structured-data / ads engine + Facebook, Instagram and Pinterest distribution tooling
for WordPress. It publishes **ready-made** articles (JSON, ZIP, Google Sheet or REST) and takes care of everything
around the post: categories, per-type schema, on-page SEO, internal links, ad placement and the hand-off to social.
(Internal plugin name: **Automation Hamri**; the admin menu is branded **WP Automator Pro**; folder name
`automation-hamri`, main file `wp-automator-pro.php`, text domain `wp-automator-pro`.)

This is the **full** build used by the kepoli family of sites (kepoli, philo / Frame of Mind, crochet1, lebensecht,
crosetam…) together with the **Viral Reader** theme. Its sibling `build-final/` is the *passive* bulk-only build for
the panrecipe network — **never install both on one site** (they share `wpap_` options and the Hub table). For
internals and file-by-file references see [`docs/components/build-v9.md`](../docs/components/build-v9.md); for the
JSON a writer must send see [`BULK-IMPORT-CONTRACT.md`](BULK-IMPORT-CONTRACT.md).

## What it does

| Area | What you get |
|---|---|
| **Publishing** | **📦 Direct Publish** (paste/upload JSON; single page by default; spread over 1–48 h *in order*, or a `drip:N` human drip), **📦 Bulk ZIP Publish** (`posts.json` + image files; also imports `<img src="images/…">` inside articles; accepts hours or `drip:N`), **Google-Sheet auto-publish** (hourly cron, dedup, per-day/per-run caps), and a **REST endpoint** (`POST /wp-json/wpap/v1/publish`, Application Password, `manage_options`). All four run one shared publisher, so a post is identical whichever route made it. |
| **Per-type schema** | `type: recipe` (or ingredients + steps present) → Recipe card + schema.org/Recipe JSON-LD; guides/stories/articles → Article. Categories accept `Parent > Child` paths. |
| **SEO head** | Meta description, Open Graph / Twitter, one connected JSON-LD `@graph` (Organization, author Person, ImageObject, WebPage, Article, BreadcrumbList), `noindex` enforcement + sitemap exclusion. Only for posts the plugin published; **stands aside completely when Yoast, Rank Math, AIOSEO, SEOPress or The SEO Framework is active** (and fills their fields instead). Opt-in FAQPage and `/llms.txt` + `/ai.txt`. |
| **Internal linking** | `[[link:slug]]` writer tokens (forward references self-heal), keyword auto-linking from a per-post `keywords` list, a curated `related` list, repair of old nested links. Runs automatically after Direct Publish and Bulk ZIP; manual buttons under Settings → Internal linking (incl. **Activate on existing posts** for a catalogue published earlier). |
| **Ads** | AdSense head loader, in-content slots (top / after paragraph N / repeating / bottom / up to 10 custom) with a density guardrail, header/sidebar/footer ad zones (`[wpap_zone]` shortcode, `wpap/ad-zone` block, theme hook), live preview + earnings estimator, `ads.txt`. All opt-in. |
| **Distribution Hub** | A table of every plugin-managed post with its Facebook caption, first-comment template, image and link; search/status/Facebook filters; JSON export (`{caption, comment, imageUrl}`); **Import Blog Posts**; "posted" ticks. |
| **Social posting (opt-in, OFF by default)** | **Facebook Page** auto-poster (photo + hook with the link in the first comment, or full-article styles), **Instagram** poster (rides the Page token), **Pinterest** RSS feed + bulk-upload CSV (no Pinterest API poster in this build). One 15-minute scheduler tick shares at most one post per channel inside your daily window. |
| **Author tools** | Gutenberg/Classic meta box (auto-fill SEO, alt text, tags, recipe, page split), **Apply Rewrites** (upload `{id,title,content}` to rewrite live posts in place, with backup + revert), near-duplicate cleanup, settings export/import, a WP-dashboard health widget. |
| **AI generator (legacy, optional)** | The original Bulk Generator (Claude / Gemini / Pexels / Pollinations) is still in the Dashboard. It is only needed if you enter API keys; **publishing from files, ZIPs, Sheets or REST needs no keys and no external API.** |

## Install

1. Upload the `automation-hamri` folder to `/wp-content/plugins/` (or install the zip via Plugins → Add New → Upload)
   and activate it. Keep the folder name so WordPress treats an upgrade as the same plugin. On Coolify-hosted site
   repos the plugin is *vendored* (`wp-content/plugins/automation-hamri/`) and shipped by redeploy — don't upload a
   zip on those sites, it is wiped on the next deploy.
2. There is no licence step (the old Gist licence system is dead code; `wpap_is_licensed()` always returns true).
3. WP-Admin → **WP Automator Pro**: Dashboard (Direct Publish / Distribution Hub / Generator), **Settings**,
   **Bulk ZIP Publish**, **Apply Rewrites**.

Requirements: WordPress 5.8+, PHP 7.4+ (8.1+ recommended); PHP `ZipArchive` for Bulk ZIP; GD for the Instagram
image. API keys (Claude / Gemini / Pexels, or the `WPAP_*_API_KEY` constants in `wp-config.php`) only for the AI tools.

## Settings (one form, collapsible groups, ⓘ help on every setting)

API keys · Automation: Google Sheet · AdSense ad placement · Content options (min word count, **skip duplicate
titles — OFF by default in this build**, close comments, clean imported images, WebP, first-comment template) ·
Facebook identifiers · AI search (`llms.txt`) · Duplicate cleanup · ads.txt · IndexNow (opt-in) · Facebook Page ·
Instagram · Pinterest · Automation status · Internal linking · Backup & restore. Each group is off / no-op until you
switch it on; a sticky "On this page" bar jumps between them.

## Publishing quick start

1. Dashboard → **📦 Direct Publish**, paste a JSON array (see the contract), pick **Split** (default single page — best
   for ad RPM) and **Schedule** (default: spread over 6 h in submission order; 0 = publish now), click **Publish All**.
   Limits per request: 2 MB / 300 items. A **Human drip** (`drip:N`, ~N posts/day in daytime) is selectable since 9.47.0.
2. Images are local files? → **Bulk ZIP Publish**: one `.zip` with `posts.json` (+ `images/`, optional `fb-images/`).
3. A hands-off drip? → **Google Sheet**: *File → Share → Publish to web → CSV*, paste the link in Settings → Automation.
   Columns: `title`, `content`, `imageUrl`, `hook`, `category`, `id`, `metaDescription`, `metaTitle`, `focusKeyword`,
   `tags` (a row needs a title or content; an `id` column gives stable de-duplication). The Sheet route carries
   nothing else (no recipe/type/keywords) — use JSON/ZIP/REST for the full contract. Fetch cap 24 MB (filter `wpap_sheet_max_bytes`; a body that hits the cap is rejected as truncated — 9.47.0+).
4. After a REST or Sheet publish, run the Internal-linking buttons (the automatic bake only follows Direct Publish
   and Bulk ZIP). A catalogue published before 9.28.0: Settings → Internal linking → **Activate on existing posts** once.
5. Mind duplicates: turn **Skip duplicate titles** on for any site fed by repeated batches, and use Settings →
   **Duplicate cleanup** (preview → trash, recoverable) for reworded near-duplicates.

## Notes and gotchas

- **`[nextpage]`** tokens become page breaks since 9.47.0 (through 9.46 only a literal `<!--nextpage-->` or the `parts` field paginated).
  (build-final converts it; don't reuse its batches here.) An item `slug` and `id` are also ignored by the publisher.
- **Scheduled posts rely on WP-Cron**, which runs on traffic; on quiet sites add a real server cron hitting
  `wp-cron.php`. Social posting and the Sheet automation need cron too. Purge the page cache after changes
  (LiteSpeed / WP Rocket / W3TC / WP Super Cache / SiteGround are purged automatically after bulk publishes).
- **Shared links are the clean permalink** (no `?v=`); a scheduled post's link is built in its pretty form, never `?p=ID`.
- **UTM tagging** for Facebook comment links reads option `wpap_utm`, which has no settings screen — set it via
  Settings → Backup & restore → Import (or WP-CLI) if you want it.
- Settings export/import omit the Facebook/Instagram tokens by design — social setup is redone per site by hand.
- `uninstall.php` keeps all data by default; define `WPAP_UNINSTALL_PURGE` for a purge (it still leaves the social
  options and the tombstones table).
- **Do not touch the AI generation pipeline** (`includes/ai-content.php`, `wpap_ajax_process_title` and its hook
  generators) unless explicitly asked.

## Security

Every AJAX handler checks `check_ajax_referer()` + `current_user_can('manage_options')`; there are no `nopriv`
endpoints. Social tokens and API keys are stored `autoload=no`, never printed, exported or logged. Remote images and
Sheet URLs are fetched through SSRF guards (public-IP check, pinned resolution, manual redirects); the ZIP importer
rejects absolute / `../` paths, allows only JSON + image extensions, caps entry count and uncompressed size and
confines every resolved file to its private extract directory. Ad snippets are admin-only, stored as pasted.

## Where things are documented

| Need | File |
|---|---|
| Item JSON the plugin accepts | [`BULK-IMPORT-CONTRACT.md`](BULK-IMPORT-CONTRACT.md) |
| Module overview of this folder | [`ARCHITECTURE.md`](ARCHITECTURE.md) |
| Everything cited by file and line, gotchas, release procedure | [`docs/components/build-v9.md`](../docs/components/build-v9.md) |
| Version history | [`CHANGELOG.md`](CHANGELOG.md) |
