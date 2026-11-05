# dalesandcobbles.getemoji.site — Dales & Cobbles (created 2026-11-05) — DRAFT CARD

> DRAFT: written by the agent at local-build time. To be moved into `docs/sites/dales-cobbles.md`
> (plgin repo) and registered in `docs/sites/REGISTRY.md` when the site is created/deployed in the
> next phase. Nothing in this card means the site is live — it is NOT deployed yet.

UK soaps blog for the owner's Facebook groups (~54K members: Coronation Street News & Spoilers
11.7K; Emmerdale Fans Club 14.6K + 13.8K; Emmerdale Absolute Spoilers & Gossips 14.4K). Repo
**github.com/daseknahri/dales-cobbles** (TO BE CREATED — local repo in `plgin/dales-cobbles/`,
seeded from the deutsche-rezepte/germ self-seeding pattern, fully rebranded, recipe machinery
removed). Sibling choice: philo was inspected and is NOT the self-seeding pattern (profile-gated
wp-init + separate wp-cli image + autoseed mu-plugins), so the germ/roman pattern was used, per
playbook §1.1/§1.9 and the deutsche-rezepte worked example.

## Stack
Dockerfile (wordpress:php8.3-apache + wp-cli) · `docker-compose.yml` (Coolify magic vars; SITE_URL
default https://dalesandcobbles.getemoji.site, ADSENSE defaults ca-pub-6869205417923902 /
pub-6869205417923902, ADSENSE_ENABLE=0, GA_ENABLE=0, HISTATS_ID=off) · `docker-compose.local.yml`
(local QA only, port 8096, WP_ENVIRONMENT_TYPE=local) · `docker/site-init.sh` · `seed/seed.php`
(`DC_SEED_VERSION` 1; idempotent; imports `data/bundles/*.zip` article bundles via
`wpap_publish_article`, pins `post_name` to planned slugs, unconditional rewrite flush on every
start). Plugin = **build-v9 9.48.0** (vendored from plgin `main` — the last RELEASED version;
9.48.1 exists only on the unmerged `muse/BL-010-seo-head-any-singular` branch and was deliberately
NOT vendored), theme = viral-reader **1.9.37** + child theme **`dales-cobbles` 1.0.0**. Admin user
`editor`. Locale en_GB (Europe/London), admin en_US.

## Child theme facts
Brand from `dales-cobbles-brand/BRAND.md`: split-roundel mark; tokens Dales green #14401F/#1E5631,
gold #E9B44C, brick #A63A28, cream #FBF6EA, ink #1E221A, spoiler pink #FF4D8D (badges only; a darker
derivative #C2185B carries the Spoilers chip text, and gold text on cream uses #9A6B1F — legibility).
Headlines Anton (self-hosted TTF), body Literata (self-hosted woff2, copied from the sibling child
theme). Category moods: Emmerdale #1E5631, Coronation Street #A63A28, Spoilers #C2185B,
Soap News #14401F, Cast & Characters #7A5A1E. End-of-article: reader question (`_dc_question` meta
or default), FB/WhatsApp share + More popover (Telegram/X/email/copy), next-article card + sticky
bar, unofficial-fan-site note. Ad architecture = sibling pattern (fixed 300×250/728×90, deferred
loader, lazy lower units, most-read fallback card) but INERT at launch: no AdSense slot IDs exist
for this site yet and ADSENSE_ENABLE=0, so `dc_ad_client()` returns '' and nothing renders.
Brand assets wired per playbook §1.6: raster icon committed to `content/images/`, icons/logo
attached at seeding from the child theme `assets/brand/` (never hotlinked).

## Seed categories (slugs fixed → CSS mood colours)
`emmerdale` Emmerdale · `coronation-street` Coronation Street · `spoilers` Spoilers ·
`soap-news` Soap News (also the default category) · `cast-characters` Cast & Characters.
`vr_noun=article`. Trust pages: about, contact, privacy-policy, terms, disclaimer,
editorial-policy, cookie-policy (privacy page registered as wp_page_for_privacy_policy).
Disclaimer + About carry the "unofficial fan site, not affiliated with ITV" statement.
Publishing note: publish by numeric category ID — the `&` in "Cast & Characters" becomes `&amp;`
in name matching and duplicates (playbook §1.10). No posts seeded (no invented storylines);
the first batch lands via `data/bundles/`.

## Brand assets
From `plgin/dales-cobbles-brand/` (original AI painting; no ITV stills/logos): split-roundel icon
→ `dales-cobbles-icon-512.png` (+ favicon-32, apple-touch-180); logo.png/logo-light.png (+ WebP
twins generated at build); hero-1920.jpg (+ 640/768/960/1280 WebP srcset variants); og.jpg.

## Status / launch checklist
- 2026-11-05: local repo built + Docker QA (see agent report): compose config valid, seed ran,
  homepage/pillars/legal pages 200, JSON-LD parses, ads.txt correct, icons render. Stack torn
  down after QA (volumes kept). NOT pushed, NOT deployed.
- Pending owner (next phase): create GitHub repo daseknahri/dales-cobbles + push; Hostinger DNS A
  `dalesandcobbles` → 85.31.239.111 (getemoji.site zone); Coolify project + app from the repo;
  first Deploy/Redeploy click; set WP salts + admin password via Coolify env; AdSense "Add site →
  Request review" (account sofiane.shih360@gmail.com, pub-6869205417923902) + Privacy & Messaging
  CMP; GSC property + sitemap; create a Facebook Page for the blog (groups exist; no Page yet) and
  wire social auto-posting tokens; create Histats counter if wanted; confirm contact email
  (seed defaults SITE_EMAIL/WP_ADMIN_EMAIL to daseknahri@gmail.com — the registry's writer/Coolify
  identity; a branded mailbox can replace it later); manual ad slot creation when enabling
  ADSENSE_ENABLE=1 (stage 2).

## Gotchas
Same as deutsche-rezepte.md / lebensecht.md (plugin ignores item `slug` → seed pins `post_name`;
categories by NAME duplicate on `&`; Coolify empty-env passthrough → seed hardcodes the publisher
fallback). Local QA needs Docker Desktop running; port 8096 is this site's local port
(deutsche-rezepte uses 8095, philo 8080).
