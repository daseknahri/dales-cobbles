# Viral Reader changelog

## 1.9.37 — 2026-10-26 (BL-124)

- Opt-in responsive in-content images: `vr_responsive_content_images` (default OFF).
  Post content from the publish pipeline stores raw `<img>` tags (no `wp-image-{id}`
  class), so core `wp_filter_content_tags()` skipped them — no `srcset`, no
  dimensions, mobile got the full-size WP-scaled original. The new
  `the_content` filter (priority 9, core runs at 10) maps same-site upload srcs
  back to their attachment (`vr_attachment_id_from_url`, tolerant of `-WxH` size
  suffixes), stamps `wp-image-{id}` + exact `width`/`height`, and core then adds
  `srcset`/`sizes`/loading attrs. Enable per site:
  `add_filter( 'vr_responsive_content_images', '__return_true' );`
  No visual/behaviour change while the filter is off.

## 1.9.36 — 2026-10-22 (BL-123)

- UCSS-aware stylesheet handling: when LiteSpeed Cache has `optm-ucss` on, the
  theme enqueues the external `style.css` (input for the QUIC.cloud UCSS
  pipeline) instead of inlining the full ~68 KB copy in `<head>`; with UCSS off
  the single-request inline pattern is kept. Version twins + `VR_VERSION`.

(Older history lives in `git log` — this file starts with 1.9.36; BL-043 tracks
backfilling 1.9.20-1.9.35.)
