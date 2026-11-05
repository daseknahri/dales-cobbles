<?php
/**
 * Dales & Cobbles site seed — run by docker/site-init.sh via `wp eval-file` on every start.
 *
 * Idempotent: the brand/site setup runs once per DC_SEED_VERSION, and each article bundle is imported once
 * (tracked in the dc_imported_bundles option), so a restart mid-import simply resumes.
 * Brand identity is read from content/site-profile.json when present (playbook §1.2); the literals below are
 * the fallback and must stay in sync with that file.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const DC_SEED_VERSION = 1;
const DC_DATA         = '/opt/site/data';
const DC_BRAND_DIR    = WP_CONTENT_DIR . '/themes/dales-cobbles/assets/brand';
const DC_AUTHOR_BIO   = 'Dales & Cobbles is written by soap fans, for soap fans. The team follows Emmerdale and Coronation Street episode by episode, reports confirmed spoilers and news from official releases, listings and cast interviews — and always labels a rumour as a rumour.';

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function dc_log( $msg ) { WP_CLI::log( '[seed] ' . $msg ); }

function dc_profile() {
	static $p = null;
	if ( null === $p ) {
		$p = array();
		$f = '/opt/site/content/site-profile.json';
		if ( is_readable( $f ) ) {
			$j = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled repo file.
			if ( is_array( $j ) ) { $p = $j; }
		}
	}
	return $p;
}

function dc_site_email() {
	$e = trim( (string) getenv( 'SITE_EMAIL' ) );
	if ( '' !== $e ) { return $e; }
	$p = dc_profile();
	return (string) ( $p['brand']['site_email'] ?? 'daseknahri@gmail.com' );
}

function dc_admin_id() {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	return $admins ? (int) $admins[0] : 1;
}

/* Copy a bundled brand file into the media library once (keyed by filename). */
function dc_brand_media( $file, $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_dc_brand', 'meta_value' => $file, 'fields' => 'ids', 'numberposts' => 1 ) );
	if ( $found ) { return (int) $found[0]; }
	$tmp = wp_tempnam( $file );
	copy( DC_BRAND_DIR . '/' . $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) { dc_log( 'media failed: ' . $file . ' ' . $id->get_error_message() ); return 0; }
	update_post_meta( $id, '_dc_brand', $file );
	return (int) $id;
}

function dc_page( $slug, $title, $html, $status = 'publish' ) {
	$p = get_page_by_path( $slug );
	$data = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $html, 'post_status' => $status, 'post_author' => dc_admin_id() );
	if ( $p ) { $data['ID'] = $p->ID; return (int) wp_update_post( wp_slash( $data ) ); }
	return (int) wp_insert_post( wp_slash( $data ) );
}

/* The editorial team is the site's author: display name, bio and the brand mark as avatar
   (feeds byline, author box and the Person schema). No invented persona. */
function dc_seed_author() {
	$uid = dc_admin_id();
	wp_update_user( array( 'ID' => $uid, 'display_name' => 'Dales & Cobbles Team', 'nickname' => 'Dales & Cobbles Team', 'description' => DC_AUTHOR_BIO ) );
	$avatar = dc_brand_media( 'dales-cobbles-icon-512.png', 'Dales & Cobbles' );
	if ( $avatar ) { update_user_meta( $uid, 'vr_author_avatar', $avatar ); }
}

function dc_categories() {
	$p = dc_profile();
	if ( ! empty( $p['categories'] ) && is_array( $p['categories'] ) ) {
		$out = array();
		foreach ( $p['categories'] as $c ) { $out[ $c['slug'] ] = array( $c['name'], $c['description'] ); }
		return $out;
	}
	return array(
		'emmerdale'         => array( 'Emmerdale', 'Emmerdale spoilers, news and gossip from the Dales — every twist in the village, episode by episode.' ),
		'coronation-street' => array( 'Coronation Street', 'Coronation Street spoilers, news and gossip from the cobbles — Weatherfield’s biggest storylines as they land.' ),
		'spoilers'          => array( 'Spoilers', 'Soap spoilers for Emmerdale and Coronation Street — confirmed advance storylines, clearly flagged before you read on.' ),
		'soap-news'         => array( 'Soap News', 'UK soap news: cast announcements, schedule changes, awards and everything happening off-screen.' ),
		'cast-characters'   => array( 'Cast & Characters', 'Who plays who, who is leaving, who is returning — the Emmerdale and Coronation Street cast and characters guide.' ),
	);
}

function dc_seed_site() {
	$p    = dc_profile();
	$name = (string) ( $p['brand']['name'] ?? 'Dales & Cobbles' );
	$tag  = (string) ( $p['brand']['tagline'] ?? 'Soap Spoilers · News · Gossip' );
	$mail = dc_site_email();

	update_option( 'blogname', $name );
	update_option( 'blogdescription', $tag );
	update_option( 'admin_email', $mail );
	update_option( 'WPLANG', 'en_GB' );
	update_option( 'timezone_string', 'Europe/London' );
	update_option( 'date_format', 'j F Y' );
	update_option( 'time_format', 'H:i' );
	update_option( 'start_of_week', 1 );
	update_option( 'posts_per_page', 12 );
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'blog_public', 1 );
	update_option( 'permalink_structure', '/%postname%/' );

	/* Theme options: one editorial voice, and the theme's nouns read "article". */
	set_theme_mod( 'vr_byline_author', true );
	set_theme_mod( 'vr_noun', 'article' );

	$icon = dc_brand_media( 'dales-cobbles-icon-512.png', 'Dales & Cobbles Icon' );
	if ( $icon ) { update_option( 'site_icon', $icon ); }
	$logo = dc_brand_media( 'dales-cobbles-logo.png', 'Dales & Cobbles Logo' );
	if ( $logo ) { set_theme_mod( 'custom_logo', $logo ); update_option( 'site_logo', $logo ); }

	/* WordPress sample content. */
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $x ) {
		$sp = get_page_by_path( $x[0], OBJECT, $x[1] );
		if ( $sp ) { wp_delete_post( $sp->ID, true ); }
	}

	/* Categories — slugs fixed so the mood colours in the child theme CSS match. */
	$cats = dc_categories();
	foreach ( $cats as $slug => $c ) {
		$t = get_term_by( 'slug', $slug, 'category' );
		if ( ! $t ) { $t = get_term_by( 'name', $c[0], 'category' ); }
		if ( $t ) { wp_update_term( $t->term_id, 'category', array( 'name' => $c[0], 'slug' => $slug, 'description' => $c[1] ) ); }
		else { wp_insert_term( $c[0], 'category', array( 'slug' => $slug, 'description' => $c[1] ) ); }
	}
	$news = get_term_by( 'slug', 'soap-news', 'category' );
	if ( $news ) { update_option( 'default_category', (int) $news->term_id ); }
	$uncat = get_term_by( 'slug', 'uncategorized', 'category' );
	if ( $uncat ) { wp_update_term( $uncat->term_id, 'category', array( 'name' => 'General', 'slug' => 'general' ) ); }

	/* Pages — trust/legal set for an SEO + AdSense-approval site (UK). */
	dc_page( 'about', 'About',
		'<p><strong>Dales &amp; Cobbles</strong> is a fan-run soap blog covering the two biggest villages in British TV: <strong>Emmerdale</strong> in the Dales and <strong>Coronation Street</strong> on the cobbles. Our tagline says what we do: <em>Soap Spoilers · News · Gossip</em>.</p>'
		. '<p>We started in the Facebook fan groups — Coronation Street News &amp; Spoilers, the Emmerdale Fans Clubs and Emmerdale Absolute Spoilers &amp; Gossips — where tens of thousands of fans swap theories every single day. This site is where those conversations get a proper home: confirmed spoilers written up clearly, soap news as it breaks, and cast and character guides that stay up to date.</p>'
		. '<h2>How we cover spoilers</h2><p>Spoiler stories are flagged in the headline and at the top of the article, so you choose whether to read on. We report what is confirmed — official ITV releases, episode listings and cast interviews — and when a story is only a rumour, we label it a rumour. We never invent storylines. Read more in our <a href="/editorial-policy/">Editorial Policy</a>.</p>'
		. '<h2>Unofficial and proud of it</h2><p>Dales &amp; Cobbles is an independent fan publication. We are not affiliated with ITV, Emmerdale or Coronation Street. All soap titles and programme names belong to their respective owners.</p>'
		. '<p>Questions, corrections or a spoiler tip? Head to the <a href="/contact/">Contact</a> page.</p>' );
	dc_page( 'contact', 'Contact',
		'<p>Got a question about a story, spotted a mistake, or heard a rumour you want us to check? We read every message.</p>'
		. '<p>Email the team at <a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a>.</p>'
		. '<p>For corrections, please include the link to the article and tell us what looks wrong — confirmed errors are fixed promptly and noted in the article. You can also reach the community any time in our Facebook groups: Coronation Street News &amp; Spoilers, Emmerdale Fans Club and Emmerdale Absolute Spoilers &amp; Gossips.</p>' );
	$privacy = dc_page( 'privacy-policy', 'Privacy Policy',
		'<p>Last updated: November 2026</p>'
		. '<p>This Privacy Policy explains what information Dales &amp; Cobbles ("we", "the Site") collects when you visit, why we collect it, and the choices you have. By using the Site you agree to the practices described here.</p>'
		. '<h2>Information we collect</h2><ul><li><strong>Information you provide.</strong> If you email us, we receive your email address and the content of your message.</li><li><strong>Log data.</strong> Our servers automatically record technical information such as your IP address, browser and device type, referring page, and the date and time of your visit, to keep the Site secure and working.</li><li><strong>Cookies and similar technologies.</strong> We and our partners use cookies to operate the Site, understand usage and, where advertising is enabled, to deliver and measure ads. See our <a href="/cookie-policy/">Cookie Policy</a>.</li></ul>'
		. '<h2>How we use information</h2><p>We use information to operate and improve the Site, respond to messages, understand which content readers find useful, keep the Site secure, and display advertising that helps fund the Site. We do not sell your personal information.</p>'
		. '<h2>Third-party advertising and Google AdSense</h2><p>The Site is funded by advertising served by third-party vendors, including Google AdSense (publisher pub-6869205417923902). Third-party vendors, including Google, use cookies to serve ads based on your prior visits to this and other websites. You can opt out of personalised advertising at <a href="https://www.google.com/settings/ads" rel="nofollow noopener">Google Ads Settings</a> or via <a href="https://www.aboutads.info/choices" rel="nofollow noopener">aboutads.info/choices</a>. For visitors in the UK, EEA and Switzerland, personalised ads and non-essential cookies are used only with your consent, collected through a Google-certified consent management platform (Google Privacy &amp; Messaging). See <a href="https://policies.google.com/technologies/partner-sites" rel="nofollow noopener">How Google uses information from sites that use its services</a>.</p>'
		. '<h2>Analytics</h2><p>If analytics are enabled, we use them in aggregate to understand how visitors use the Site (pages viewed, approximate location from IP address, device type). Analytics cookies are non-essential and, where required, are set only with your consent.</p>'
		. '<h2>Your rights (UK GDPR / GDPR)</h2><p>Depending on where you live, you may have the right to access, correct or delete the personal information we hold about you, to object to or restrict certain processing, and to complain to a data protection authority (in the UK, the ICO). To make a request, email us at <a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a> and we will respond as required by applicable law.</p>'
		. '<h2>Children</h2><p>The Site is a general-audience publication and is not directed at children under 13. We do not knowingly collect personal information from children.</p>'
		. '<h2>Changes</h2><p>We may update this policy from time to time; the "Last updated" date above will change when we do.</p>' );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	dc_page( 'terms', 'Terms',
		'<p>By accessing or using Dales &amp; Cobbles (the "Site"), you agree to these Terms. If you do not agree, please do not use the Site.</p>'
		. '<h2>Acceptable use</h2><p>You agree to use the Site for lawful, personal purposes only. You may not damage, disable or impair the Site, interfere with other visitors, attempt unauthorised access, or scrape or harvest content with automated tools without our permission.</p>'
		. '<h2>Intellectual property</h2><p>Unless otherwise noted, the articles, text and design on the Site are owned by Dales &amp; Cobbles and protected by copyright. Enjoy them for personal use; do not copy, republish or use them commercially without prior written permission. Emmerdale, Coronation Street and related names and imagery belong to ITV and their respective owners; the Site is an unofficial fan publication and claims no ownership of them.</p>'
		. '<h2>Commentary and fair dealing</h2><p>The Site publishes original news reporting, commentary and criticism about television programmes. References to programmes, characters and storylines are made for the purposes of reporting and review. If you believe any content infringes your rights, contact us and we will review it promptly.</p>'
		. '<h2>No warranty</h2><p>The Site and its content are provided "as is" and "as available" without warranties of any kind. We work hard to keep spoilers and news accurate and clearly labelled, but we do not guarantee that content is complete, current or error-free; broadcast schedules and storylines can change.</p>'
		. '<h2>Limitation of liability</h2><p>To the fullest extent permitted by law, Dales &amp; Cobbles will not be liable for any loss or damage arising from your use of the Site or reliance on its content.</p>'
		. '<h2>External links</h2><p>The Site may link to third-party websites we do not control. We are not responsible for their content or practices.</p>'
		. '<h2>Changes</h2><p>We may update these Terms from time to time; changes take effect once posted on this page.</p>' );
	dc_page( 'disclaimer', 'Disclaimer',
		'<h2>Unofficial fan site</h2><p>Dales &amp; Cobbles is an independent, unofficial fan publication. It is not affiliated with, endorsed by or connected to ITV, Emmerdale or Coronation Street. All programme titles, character names and related marks belong to their respective owners.</p>'
		. '<h2>Spoilers and rumours</h2><p>Spoiler articles reveal future storylines and are clearly flagged in the headline and at the top of the article — reading on is your choice. Confirmed spoilers come from official releases, episode listings and cast interviews. Anything unconfirmed is labelled a rumour, and rumours sometimes turn out to be wrong; broadcasters can also change schedules and storylines at short notice.</p>'
		. '<h2>Accuracy</h2><p>We aim for accuracy in everything we publish and correct confirmed errors promptly. If you spot a mistake, please tell us via the <a href="/contact/">Contact</a> page.</p>'
		. '<h2>Advertising</h2><p>The Site displays advertising, which helps fund it and does not influence our editorial coverage. See our <a href="/privacy-policy/">Privacy Policy</a> for how advertising cookies work and how to control them.</p>' );
	dc_page( 'editorial-policy', 'Editorial Policy',
		'<p>This page explains how Dales &amp; Cobbles creates its content and the standards we hold it to.</p>'
		. '<h2>What we publish</h2><p>Soap spoilers, news and gossip about Emmerdale and Coronation Street: advance storylines, cast announcements and interviews, schedule changes, and guides to the characters and the actors who play them.</p>'
		. '<h2>Sourcing</h2><p>Confirmed stories are based on official broadcaster releases, published episode listings and synopses, and interviews given by cast and producers. We name the type of source in the article so you can judge how solid a story is.</p>'
		. '<h2>Spoilers and rumours</h2><p>Spoilers are flagged in the headline and again at the top of the article. Rumours — stories circulating without official confirmation — are always labelled as rumours in the headline or first paragraph. We never present a rumour, a fan theory or our own speculation as fact, and we never fabricate storylines.</p>'
		. '<h2>Independence</h2><p>Our coverage reflects our own honest judgement as fans and writers. Advertising funds the Site but never buys coverage, and we do not publish paid or sponsored content disguised as editorial. Any commercial relationship would be disclosed clearly.</p>'
		. '<h2>Corrections</h2><p>If we get something wrong, we fix it promptly and, where the error was material, note the correction in the article. Report an error via the <a href="/contact/">Contact</a> page.</p>'
		. '<h2>Images</h2><p>Our brand artwork is original. We do not lift publicity stills or screenshots and pass them off as our own.</p>' );
	dc_page( 'cookie-policy', 'Cookie Policy',
		'<p>This Cookie Policy explains how Dales &amp; Cobbles uses cookies and similar technologies. It should be read alongside our <a href="/privacy-policy/">Privacy Policy</a>.</p>'
		. '<h2>What cookies are</h2><p>Cookies are small text files stored on your device when you visit a website. They help sites function, remember preferences and understand how pages are used. Similar technologies include web beacons and pixels. Cookies may be set by us (first-party) or by third parties whose services we use.</p>'
		. '<h2>Types of cookies we use</h2><ul><li><strong>Essential cookies.</strong> Needed for the Site to work and stay secure, including remembering your cookie preferences.</li><li><strong>Analytics cookies.</strong> Help us understand, in aggregate, which pages readers visit and how they navigate the Site.</li><li><strong>Advertising cookies.</strong> Where advertising is enabled, partners including Google use cookies to deliver ads, limit how often you see the same ad, and measure performance; they may personalise ads based on your visits to this and other sites.</li></ul>'
		. '<h2>Consent</h2><p>For visitors in the UK, EEA and Switzerland, non-essential cookies — analytics and personalised advertising — are used only with your consent, collected through a Google-certified consent management platform. You can withdraw or change your consent at any time through that tool or your browser settings.</p>'
		. '<h2>How to control cookies</h2><p>Most browsers let you view, block and delete cookies in their Settings or Privacy sections. Blocking some cookies may affect how parts of the Site work. You can opt out of personalised advertising at <a href="https://www.google.com/settings/ads" rel="nofollow noopener">Google Ads Settings</a>.</p>'
		. '<h2>Questions</h2><p>Questions about cookies: email us via the <a href="/contact/">Contact</a> page.</p>' );

	/* Primary menu: the content pillars. */
	$menu = wp_get_nav_menu_object( 'Primary' );
	$mid  = $menu ? $menu->term_id : wp_create_nav_menu( 'Primary' );
	if ( ! $menu ) {
		foreach ( array_keys( $cats ) as $slug ) {
			$t = get_term_by( 'slug', $slug, 'category' );
			if ( $t ) { wp_update_nav_menu_item( $mid, 0, array( 'menu-item-object' => 'category', 'menu-item-object-id' => $t->term_id, 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) ); }
		}
	}
	/* Footer menu: the trust/legal pages. */
	$fmenu = wp_get_nav_menu_object( 'Footer' );
	$fmid  = $fmenu ? $fmenu->term_id : wp_create_nav_menu( 'Footer' );
	if ( ! $fmenu ) {
		foreach ( array( 'about', 'contact', 'editorial-policy', 'privacy-policy', 'cookie-policy', 'terms', 'disclaimer' ) as $slug ) {
			$pg = get_page_by_path( $slug );
			if ( $pg ) { wp_update_nav_menu_item( $fmid, 0, array( 'menu-item-object' => 'page', 'menu-item-object-id' => $pg->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) ); }
		}
	}
	$locs = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( get_registered_nav_menus() as $loc => $label ) {
		if ( false !== strpos( $loc, 'primary' ) || 'menu-1' === $loc ) { $locs[ $loc ] = $mid; }
		if ( false !== strpos( $loc, 'footer' ) ) { $locs[ $loc ] = $fmid; }
	}
	set_theme_mod( 'nav_menu_locations', $locs );

	/* Drop WordPress's default sidebar widgets (English 'Recent Posts', Archives, Meta…); the theme renders its own
	   latest-articles + topics blocks when the sidebar is empty. */
	$sw = (array) get_option( 'sidebars_widgets', array() );
	foreach ( $sw as $k => $v ) { if ( is_array( $v ) && 'wp_inactive_widgets' !== $k ) { $sw[ $k ] = array(); } }
	update_option( 'sidebars_widgets', $sw );

	dc_seed_author();

	flush_rewrite_rules( false );
	update_option( 'dc_seed_version', DC_SEED_VERSION );
	dc_log( 'site seeded' );
}

/* AdSense, two-stage (playbook §5). The publisher id is the getemoji.site family's, shared with the sibling
   subdomains. Stage 1 (ADSENSE_ENABLE!=1): ads.txt is served and the plugin stays OFF, so the site can be
   added + reviewed in AdSense without showing a single ad. Stage 2 (ADSENSE_ENABLE=1): Auto ads loader only —
   no manual slot ids exist for this site yet, so no manual units are configured here.
   Falls back to the site publisher when the variable arrives empty (Coolify passes declared-but-unset
   variables through as empty strings). */
const DC_ADSENSE_DEFAULT = 'ca-pub-6869205417923902';
function dc_apply_ads() {
	$client = trim( (string) getenv( 'ADSENSE_CLIENT' ) );
	if ( '' === $client ) { $client = DC_ADSENSE_DEFAULT; }
	if ( 'off' === $client || ! preg_match( '/^ca-pub-\d{10,20}$/', $client ) ) { return; }
	update_option( 'wpap_ads_txt', 'google.com, ' . str_replace( 'ca-', '', $client ) . ', DIRECT, f08c47fec0942fa0' );
	$ads = (array) get_option( 'wpap_ads_inject', array() );
	if ( '1' === trim( (string) getenv( 'ADSENSE_ENABLE' ) ) ) {
		$ads['enabled']   = 1;
		$ads['scope_all'] = 1;
		$ads['auto_code'] = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $client . '" crossorigin="anonymous"></script>';
	} else {
		$ads['enabled'] = 0;
	}
	update_option( 'wpap_ads_inject', $ads );
}

/* Article bundles (data/bundles/*.zip: posts.json + images) are imported once, via the plugin publisher.
   Empty at launch — the first content batch drops zips in and restarts. */
function dc_import_bundles() {
	if ( ! function_exists( 'wpap_publish_article' ) ) { dc_log( 'plugin not loaded — skipping import' ); return; }
	$done  = (array) get_option( 'dc_imported_bundles', array() );
	$zips  = glob( DC_DATA . '/bundles/*.zip' ) ?: array();
	sort( $zips );
	$admin = dc_admin_id();
	wp_set_current_user( $admin );
	foreach ( $zips as $zip ) {
		$name = basename( $zip );
		if ( in_array( $name, $done, true ) ) { continue; }
		$work = trailingslashit( get_temp_dir() ) . 'dc-' . sanitize_file_name( $name );
		$z = new ZipArchive();
		if ( true !== $z->open( $zip ) ) { dc_log( "cannot open $name" ); continue; }
		$z->extractTo( $work );
		$z->close();
		$items = json_decode( (string) file_get_contents( $work . '/posts.json' ), true );
		$n = 0;
		foreach ( (array) $items as $item ) {
			$slug = sanitize_title( $item['slug'] ?? $item['title'] ?? '' );
			if ( $slug && get_page_by_path( $slug, OBJECT, 'post' ) ) { continue; }   /* resume-safe */
			$opts = array( 'default_parts' => 1, 'schedule_window' => 0, 'author' => $admin );
			$img  = isset( $item['image'] ) ? realpath( $work . '/' . ltrim( (string) $item['image'], '/' ) ) : false;
			if ( $img && 0 === strpos( $img, realpath( $work ) ) ) { $opts['local_image_path'] = $img; }
			$id = wpap_publish_article( $item, $opts );
			if ( is_wp_error( $id ) ) { dc_log( 'skip: ' . $id->get_error_message() ); continue; }
			/* build-v9 derives the permalink from the title and ignores item slug — pin the
			   planned slug so the resume check above and [[link:slug]] tokens stay valid. */
			if ( $slug ) { wp_update_post( array( 'ID' => (int) $id, 'post_name' => $slug ) ); }
			$n++;
		}
		/* Clean up the extracted bundle. */
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $work, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
		rmdir( $work );
		$done[] = $name;
		update_option( 'dc_imported_bundles', $done, false );
		dc_log( "$name: $n articles imported" );
	}
	if ( function_exists( 'wpap_internal_links_bake' ) ) { wpap_internal_links_bake( 500 ); }
}

if ( (int) get_option( 'dc_seed_version', 0 ) < DC_SEED_VERSION ) { dc_seed_site(); }
dc_apply_ads();
dc_import_bundles();

/* Self-heal pretty permalinks on every start: a long import can leave the stored rewrite
   rules partial or emptied, and then every pretty URL (including ads.txt) 404s until a
   flush. Regenerating on every start is idempotent and cheap. */
if ( '' === get_option( 'permalink_structure', '' ) ) {
	update_option( 'permalink_structure', '/%postname%/' );
}
flush_rewrite_rules( false );
