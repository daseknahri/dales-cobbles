# Viral Reader — theme

A warm, editorial, mobile-first WordPress theme for **story, recipe and long-form blogs**, built to pair with the **WP Automator Pro** plugin (`build-v9/`, and the vendored copies on the kepoli-family sites). The theme is translation-ready and ships a **German (de_DE)** translation; English is the source language, and a Customizer option swaps the word "story" for "recipe" or "article" in its own strings.

## The look
- **Warm "paper" palette** — cream background, near-white cards, olive/copper accents, soft warm shadows and hairlines. No cool grays. An opt-in **cinematic** dark skin is a site filter away: `add_filter( 'vr_skin', fn() => 'cinematic' )` swaps the colour tokens (body class `vr-skin-cinematic`).
- **Serif reading, sans chrome** — an Iowan Old Style / Palatino / Georgia serif for headlines and article body, a `system-ui` sans for eyebrows, meta, nav and buttons. System fonts only (no web-font download).
- **Hero on the home page** — your latest story as a full-bleed image with an overlay, big serif headline and a copper "Read the story" button (or, when a site supplies one through the `vr_hero_image_url` / `vr_hero_video_url` filters, a standing brand hero with an optional muted looping video). The Customizer can put the photo above the headline instead.
- **680px reading column** (`--measure`) with a **sticky sidebar** (recent stories + topics, or widgets), a reading-progress bar, breadcrumbs (with BreadcrumbList JSON-LD when the plugin is not already emitting one), and share buttons (Facebook / WhatsApp / email / copy / print).
- **Lift-on-hover cards**, pill category chips, a **dark footer** (brand, top categories, an "Information" menu or fallback links, and a social-icon row from Customizer → Social profiles) with an olive→copper accent rule.
- Presentation hardening layer (on by default, `vr_enable_presentation_hardening`): editorial callouts, tutorial step lists, per-heading anchor-copy links and a Pinterest "Save" button on images (`vr_enable_pinterest_save`).
- **Recipe card + Recipe JSON-LD** from the plugin's `_wpap_recipe_*` post meta (the plugin defers to the theme when these functions exist), related-posts block, 404/search/author templates, editor styles that match the front end.
- Fast + light: no jQuery, block-editor CSS dequeued, LCP hint on the featured image. Light colour scheme by default.

## Ads — the plugin owns them
This theme does **not** inject its own in-content ads (so nothing double-runs). Ads come from **WP Automator Pro**:
- **In-content** ads: configured in the plugin (Settings → AdSense ad placement) and injected into the article body.
- **Header / Sidebar / Footer zones**: set in the plugin too (Settings → AdSense ad placement → "Around the article"); the theme renders them via `wpap_zone_html()`. If the plugin is inactive (or a zone is empty) the theme falls back to its own **Header Ad** and **Sidebar** widget areas, so it still works standalone. **There is no footer widget area** — the footer ad strip comes only from the plugin's Footer zone, and the footer columns are theme-rendered.
- Injected `.wpap-ad` blocks and the "You May Also Like" block are styled to match the paper palette, with an "Advertisement" label.

## Install
1. **Appearance → Themes → Add New → Upload Theme** → upload the zip → **Activate**. (On a Coolify site repo the theme is vendored into `wp-content/themes/viral-reader/` — don't upload a zip there.)
2. **Appearance → Menus** — create a menu and assign it to *Primary Menu* (until then, the header auto-fills with your top categories). An optional *Footer Menu* replaces the footer's "Information" fallback links.
3. Ads: configure them in the **plugin**, not here (or drop AdSense units into Appearance → Widgets → **Header Ad** / **Sidebar** if you're not using the plugin zones).

## Site options (1.9.23+ of the Customizer line)
**Appearance → Customize → Site options** — per-site choices, no code. Defaults keep the original look.
- **What this site publishes** — Stories / Recipes / Articles; swaps the word in the theme's buttons and headings.
- **Homepage hero layout** — headline over the photo, or photo above the headline (for images with printed text).
- **Photos on topic tiles** (off by default) — when a category has no cover image of its own, its **homepage tile** uses the newest post's photo. Category page headers stay plain on purpose (their title is never printed over a busy photo); a site that wants a header banner supplies a cover through the `vr_category_cover_url` filter.
- **Show the author name and photo on posts** — untick for a brand-voice site (also hides the author box under posts).

**Appearance → Customize → Social profiles** — one address per network (Facebook, Pinterest, Instagram, X, YouTube, TikTok, LinkedIn); each filled-in box becomes a footer icon.

## Version history — the 1.9.20–1.9.23 collision and the 1.9.35 reconciliation
Between 1.9.19 and 1.9.34 the theme was developed on **three separate lines** that reused the same version
numbers for different code. **1.9.35 is the first build that contains all of them**; when comparing a vendored
copy, go by features, not by the 1.9.2x number.

| Line | Where it lived | What its numbers mean |
|---|---|---|
| **Fork A** (main checkout) | snapshot `save/main-checkout-2026-10-07` | **1.9.20** (what kepoli vendors) = localizable Pinterest "Save" strings + site-wide brand social icon row in the footer (`vr_social_profiles` / `vr_brand_social_html`). **1.9.21–1.9.22** added footer "Information" fallback links (`vr_footer_info_links`) and brand icons in `<head>` (`vr_head_icons`: favicon/apple-touch/manifest via filters). **1.9.23** = proper network names in that row ("YouTube", "TikTok", "LinkedIn" instead of `ucfirst()`). |
| **Fork B** (kitchen line) | `claude/panrecipe-kitchen-dev-6d5179` | Imported fork A's 1.9.22 verbatim, then **1.9.23** = Customizer *Site options* (a different 1.9.23!), 1.9.24–1.9.28 ad frames/logo/pagination fixes, 1.9.29 Pinterest image + Customizer social profiles, 1.9.30 de_DE translation, 1.9.31–1.9.33 dropdown/mega navigation, 1.9.34 cache-busting that survives query-string stripping. |
| **Fork C** (deploy-coolify worktree) | snapshot `save/deploy-coolify-vps-2026-10-07` | **1.9.20** = mobile overflow fix only (single-post grid `min-width:0`, entry media/code/tables capped at 100%, shrinkable search input). Unrelated to fork A's 1.9.20. |

**1.9.35** = fork B 1.9.34 + fork A's 1.9.23 network-name fix + fork C's overflow CSS. No translatable strings
changed, so `languages/*.pot|.po|.mo|.l10n.php` are unchanged. Also from this integration: the canonical theme no
longer ships kepoli's brand images (`assets/img/` holds only an `index.php` guard; each site keeps its own).

**1.9.36** = UCSS-aware stylesheet delivery. When the LiteSpeed Cache plugin has UCSS enabled, the theme stops
inlining its full `style.css` into `<head>` and enqueues the normal external `<link>` instead — LSCWP's UCSS
pipeline rebuilds a small per-URL stylesheet from that link, while the old behavior shipped ~68 KB of inline CSS
on every page *alongside* the optimized file (the optimizer cannot see src-less inline handles). Sites without
LSCWP/UCSS are untouched (inlining stays the default); override with the `vr_inline_stylesheet` filter.
getemoji.site 2026-10-22 (BL-123): removed the 67,953-byte inline block from every page.

What the live sites vendor (as of 2026-10-07): **philo** = fork-A 1.9.23, **kepoli** = fork-A 1.9.20,
**lebensecht / kuche / kitchen** = fork-B (up to 1.9.34). Upgrading any of them to 1.9.35 is a superset — but kepoli
and philo also gain everything from fork B (Site options, new nav, ad-frame/pagination changes): check the
Customizer defaults on those sites after the upgrade.

## Notes
- The theme adds **no `<head>` SEO meta** — the plugin (or Yoast/Rank Math) owns that, so nothing is duplicated.
- Standalone: no external services, no custom post meta, no mu-plugin dependencies. Requires WordPress 5.5+ / PHP 7.2+. Text domain `viral-reader`, GPL-2.0+.
