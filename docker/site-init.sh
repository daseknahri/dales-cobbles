#!/bin/bash
# Idempotent site setup. Runs on every container start:
#   1. wait for WordPress core + the database,
#   2. sync the vendored theme/plugin into wp-content,
#   3. install WordPress once (en_GB), then run the seed (brand, pages, menus, article bundles) once per SEED_VERSION.
set -u
WP="wp --allow-root --path=/var/www/html"
log(){ echo "[site-init] $*"; }

for i in $(seq 1 120); do
  [ -f /var/www/html/wp-config.php ] && $WP db check >/dev/null 2>&1 && break
  sleep 3
done
$WP db check >/dev/null 2>&1 || { log "database not reachable, giving up"; exit 0; }

log "syncing theme + plugin + mu-plugins"
for d in themes/viral-reader themes/dales-cobbles plugins/automation-hamri mu-plugins; do
  rm -rf "/var/www/html/wp-content/$d"
  mkdir -p "$(dirname /var/www/html/wp-content/$d)"
  cp -r "/opt/site/wp-content/$d" "/var/www/html/wp-content/$d"
done
# Fresh code ships on every start: drop the full-page cache so no visitor gets a pre-redeploy HTML snapshot.
rm -rf /var/www/html/wp-content/cache/dc-page 2>/dev/null || true
chown -R www-data:www-data /var/www/html/wp-content

URL="${SITE_URL:-${SERVICE_FQDN_WORDPRESS:-http://localhost}}"
if ! $WP core is-installed >/dev/null 2>&1; then
  log "installing WordPress at $URL"
  $WP core install --url="$URL" --title="Dales & Cobbles" --locale=en_GB \
    --admin_user="${WP_ADMIN_USER:-editor}" --admin_password="${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD missing}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" --skip-email
  $WP language core install en_GB --activate || log "en_GB language pack download failed (site stays en_US until retried)"
fi

$WP theme activate dales-cobbles
$WP plugin activate automation-hamri
# Google Site Kit (Analytics + AdSense reports in wp-admin). Installed once from wordpress.org, then kept on the volume.
$WP plugin is-installed google-site-kit || $WP plugin install google-site-kit || log "site kit download failed (retried next start)"
$WP plugin is-installed google-site-kit && $WP plugin activate google-site-kit
$WP eval-file /opt/site/seed/seed.php && log "seed done"
chown -R www-data:www-data /var/www/html/wp-content/uploads 2>/dev/null || true

# One-time image refresh (child theme 1.0.0): generate the missing 480px "dc-card" sub-size (as WebP) for articles
# imported before it existed. Idempotent via an option flag (set only after a clean finish), runs in the background so it never
# delays Apache, and --only-missing never touches or deletes existing files.
if [ "$($WP option get dc_card_sizes_v1 2>/dev/null)" != "1" ]; then
  ( $WP media regenerate --only-missing --yes >/dev/null 2>&1 && $WP option update dc_card_sizes_v1 1 >/dev/null 2>&1 \
      && chown -R www-data:www-data /var/www/html/wp-content/uploads && log "card image sizes generated" ) &
fi
