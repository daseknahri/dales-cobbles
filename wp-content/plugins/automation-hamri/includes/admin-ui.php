<?php
/**
 * Settings-page UX helpers (9.46.0): hover-help "i" bubbles, collapsible-group + sticky
 * "On this page" nav styles/JS, and the help copy for the main settings page.
 * Presentation only — no option keys, save handlers or behaviour live here.
 * Loaded before admin.php and the Facebook/Instagram/Pinterest admin files.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Hover-help "i" bubble. Tips come from the `wpap_help_tips_extra` filter (authored copy, kses-restricted). */
if ( ! function_exists( 'wpap_help_tip' ) ) {
	function wpap_help_tip( $key ) {
		$tips = apply_filters( 'wpap_help_tips_extra', array() );
		if ( empty( $tips[ $key ] ) ) { return ''; }
		$allowed = array(
			'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(),
			'br' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'code' => array(),
		);
		return '<span class="wpap-help" tabindex="0" role="img" aria-label="How to use this setting — hover or focus for directions">'
			. '<span class="wpap-help-i" aria-hidden="true">i</span>'
			. '<span class="wpap-tip" role="tooltip">' . wp_kses( (string) $tips[ $key ], $allowed ) . '</span></span>';
	}
}

/* Open a collapsible settings group. Pair with wpap_group_end(). Title/sub are plain text (escaped here). */
function wpap_group_start( $id, $title, $sub = '', $open = false ) {
	echo '<details class="wpap-group" id="' . esc_attr( $id ) . '"' . ( $open ? ' open' : '' ) . '><summary><span class="wpap-g-title">'
		. esc_html( $title ) . '</span>' . ( '' !== $sub ? ' <span class="wpap-g-sub">' . esc_html( $sub ) . '</span>' : '' )
		. '</summary><div class="wpap-group-body">';
}
function wpap_group_end() {
	echo '</div></details>';
}

/* Shared CSS for tooltips, groups and the sticky nav (printed once on the settings page). */
function wpap_settings_ui_css() {
	?>
	<style>
		.wpap-help{position:relative;display:inline-flex;vertical-align:middle;margin-left:6px;cursor:help}
		.wpap-help:focus{outline:none}
		.wpap-help-i{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#2271b1;color:#fff;font:700 11px/1 Georgia,"Times New Roman",serif;font-style:italic}
		.wpap-help:hover .wpap-help-i,.wpap-help:focus .wpap-help-i{background:#135e96}
		.wpap-tip{display:none;position:absolute;left:0;top:24px;z-index:100000;width:340px;max-width:78vw;background:#1f2937;color:#f3f4f6;border-radius:8px;padding:12px 14px;font:400 12.5px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:left;white-space:normal;box-shadow:0 12px 34px -8px rgba(0,0,0,.55);text-transform:none;letter-spacing:normal}
		.wpap-help:hover .wpap-tip,.wpap-help:focus .wpap-tip,.wpap-help:focus-within .wpap-tip{display:block}
		.wpap-tip::before{content:"";position:absolute;left:4px;top:-6px;border:6px solid transparent;border-top:0;border-bottom-color:#1f2937}
		.wpap-tip strong,.wpap-tip b{color:#fff;font-weight:700}
		.wpap-tip code{background:rgba(255,255,255,.16);color:#fde68a;padding:1px 4px;border-radius:3px;font-size:11.5px}
		.wpap-tip ul,.wpap-tip ol{margin:6px 0 0;padding-left:18px}
		.wpap-tip li{margin:2px 0}
		.wpap-tip a{color:#93c5fd}
		@media (max-width:782px){ .wpap-tip{width:280px} }
		details.wpap-group{border:1px solid #dcdcde;border-radius:10px;background:#fff;margin:0 0 14px;max-width:940px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
		details.wpap-group>summary{cursor:pointer;list-style:none;padding:14px 16px;display:flex;align-items:baseline;gap:10px;user-select:none;border-radius:10px}
		details.wpap-group>summary::-webkit-details-marker{display:none}
		details.wpap-group>summary::before{content:"\25B8";color:#2271b1;font-size:12px;transition:transform .15s ease;line-height:1.4;flex:0 0 auto}
		details.wpap-group[open]>summary::before{transform:rotate(90deg)}
		details.wpap-group>summary .wpap-g-title{font-size:14.5px;font-weight:700;color:#1d2327}
		details.wpap-group>summary .wpap-g-sub{font-weight:400;color:#646970;font-size:12.5px}
		details.wpap-group[open]>summary{border-bottom:1px solid #f0f0f1;border-radius:10px 10px 0 0}
		details.wpap-group>.wpap-group-body{padding:2px 16px 12px}
		details.wpap-group .form-table{margin-top:4px}
		details.wpap-group .form-table th{font-weight:600}
		.wrap .form-table{max-width:940px}
		.wrap .button{border-radius:7px}
		.wrap p.submit .button-primary{border-radius:8px;min-height:38px;padding:0 22px;font-weight:600}
		.wpap-nav{position:sticky;top:32px;z-index:20;display:flex;align-items:center;flex-wrap:wrap;gap:4px;margin:0 0 22px;padding:8px 10px;background:rgba(255,255,255,.92);backdrop-filter:saturate(1.4) blur(6px);border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 2px 10px -4px rgba(0,0,0,.12);max-width:940px}
		.wpap-nav-label{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#8b93a1;margin-right:6px;padding-left:4px}
		.wpap-nav a{font-size:12.5px;line-height:1;text-decoration:none;color:#475569;padding:6px 10px;border-radius:8px;white-space:nowrap;transition:background .12s,color .12s}
		.wpap-nav a:hover{background:#eef2f7;color:#1d2327}
		.wpap-nav a.active{background:#2271b1;color:#fff;font-weight:600}
		details.wpap-group[id^="wpap-sec-"],details.wpap-group[id^="wpap-grp-"]{scroll-margin-top:84px}
		@media (max-width:782px){ .wpap-nav{top:46px} details.wpap-group[id^="wpap-sec-"],details.wpap-group[id^="wpap-grp-"]{scroll-margin-top:100px} }
	</style>
	<?php
}

/* Sticky "On this page" nav. $sections = array( element-id => label ). Scroll-spy + opens the target group on click. */
function wpap_settings_nav( $sections ) {
	echo '<nav class="wpap-nav" id="wpap-nav" aria-label="Settings sections"><span class="wpap-nav-label">On this page</span>';
	foreach ( $sections as $id => $label ) {
		echo '<a href="#' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	?>
	<script>
	(function(){
		function init(){
			var nav = document.getElementById('wpap-nav'); if(!nav) return;
			var links = {}, order = [];
			function setActive(id){ for(var k in links){ links[k].classList.toggle('active', k===id); } }
			nav.querySelectorAll('a[href^="#wpap-"]').forEach(function(a){
				var id = a.getAttribute('href').slice(1); links[id] = a; order.push(id);
				a.addEventListener('click', function(){
					var t = document.getElementById(id);
					if(t && t.tagName === 'DETAILS'){ t.open = true; }
					setTimeout(function(){ setActive(id); }, 0);
				});
			});
			var secs = order.map(function(id){ return document.getElementById(id); }).filter(Boolean);
			if('IntersectionObserver' in window){
				var visible = {};
				var io = new IntersectionObserver(function(ents){
					ents.forEach(function(e){ visible[e.target.id] = e.isIntersecting; });
					for(var i=0;i<order.length;i++){ if(visible[order[i]]){ setActive(order[i]); break; } }
				}, { rootMargin: '-90px 0px -70% 0px', threshold: 0 });
				secs.forEach(function(s){ io.observe(s); });
			}
			if(order.length) setActive(order[0]);
			if(location.hash){ var h = document.getElementById(location.hash.slice(1)); if(h && h.tagName === 'DETAILS'){ h.open = true; } }
		}
		if(document.readyState !== 'loading') init(); else document.addEventListener('DOMContentLoaded', init);
	})();
	</script>
	<?php
}

/* Help copy for the main settings page (plain-English, step-by-step). */
add_filter( 'wpap_help_tips_extra', function ( $t ) {
	return array_merge( (array) $t, array(
		'v9_claude_api_key' => '<strong>Only needed for the AI writing tools.</strong> Publishing from a file, a ZIP or a Google Sheet works without it.<br><strong>How to get one:</strong><ol><li>Sign in at console.anthropic.com and open <em>API Keys</em>.</li><li>Click <em>Create Key</em> and copy it.</li><li>Paste it here and Save. It is stored on your site only and never shown again.</li></ol>Leave blank to keep the saved key.',
		'v9_gemini_api_key' => '<strong>Only needed for AI image generation.</strong><br><strong>How to get one:</strong><ol><li>Open aistudio.google.com and sign in.</li><li>Click <em>Get API key</em>, then <em>Create API key</em>.</li><li>Paste it here and Save.</li></ol>Leave blank to keep the saved key.',
		'v9_pexels_api_key' => '<strong>Free stock photos</strong> used when a post has no image of its own.<br><strong>How to get one:</strong><ol><li>Create a free account at pexels.com/api.</li><li>Click <em>Your API Key</em> and copy it.</li><li>Paste it here and Save.</li></ol>Leave blank to keep the saved key.',

		'v9_auto_enabled'   => '<strong>Publishes new rows from your Google Sheet by itself</strong>, once an hour.<br><strong>How to use it:</strong> paste the Sheet link below, set the daily limit, tick this and Save. Rows already published are never repeated.<br><em>Default: OFF.</em>',
		'v9_auto_sheet_url' => '<strong>The link to your Sheet as a CSV.</strong><ol><li>In Google Sheets choose <em>File &rarr; Share &rarr; Publish to web</em>.</li><li>Pick the right tab and <em>Comma-separated values (.csv)</em>.</li><li>Click <em>Publish</em> and copy the link (it ends in <code>output=csv</code>).</li><li>Paste it here and Save.</li></ol>The first row must be the column names.',
		'v9_auto_per_day'   => '<strong>The most posts published in one day.</strong> Set 0 to pause publishing without switching the automation off.<br>Example: 20 means at most 20 new posts a day, however many rows are waiting.',
		'v9_auto_per_run'   => '<strong>The most posts published each hourly run.</strong> Use 1 for a slow, natural drip; a higher number publishes faster. The daily limit above always wins.',
		'v9_auto_default_category' => '<strong>The category used when a row has no category</strong> of its own. Type the category name; it is created if it does not exist yet. Blank uses the WordPress default.',
		'v9_auto_schedule_window'  => '<strong>Spreads posts out so they do not all go live at once.</strong> 0 publishes straight away. Any other number schedules each post at a random time within that many hours.<br>Example: 6 spreads a batch over the next 6 hours.',

		'v9_ads_enabled'   => '<strong>The master switch for ads on your site.</strong> Nothing is printed until this is ticked.<br><strong>How to use it:</strong> paste your AdSense code in the boxes below, tick this and Save.<br><strong>All posts:</strong> off means only posts this plugin created show ads.<br><strong>Advertisement label:</strong> adds a small word above each ad.<br><em>Default: OFF.</em>',
		'v9_ads_auto_code' => '<strong>Your AdSense Auto Ads snippet.</strong> Google then chooses where to place extra ads.<br><strong>How to get it:</strong><ol><li>In AdSense open <em>Ads &rarr; By site</em>, then <em>Edit</em> for your site.</li><li>Click <em>Get code</em> and copy the whole <code>&lt;script&gt;</code>.</li><li>Paste it here and Save.</li></ol>This snippet also loads the manual units below, so paste it once.',
		'v9_ads_density'   => '<strong>Stops pages from getting crowded.</strong><br><em>Min paragraphs between ads</em> keeps ads apart (2 means at least two paragraphs between any two ads).<br><em>Max in-content ads per post</em> caps the total; 0 means no cap.<br>Fewer ads usually mean happier readers and safer AdSense approval.',
		'v9_ads_top'       => '<strong>One ad at the very top of each article.</strong><ol><li>In AdSense create a <em>Display</em> ad unit and copy its code.</li><li>Paste it here, tick <em>On</em>, and Save.</li></ol>',
		'v9_ads_inc'       => '<strong>One ad inside the article text</strong>, after the paragraph number you choose.<ol><li>In AdSense create an <em>In-article</em> unit and copy its code.</li><li>Paste it here, set the paragraph number (2&ndash;3 works well), tick <em>On</em> and Save.</li></ol>',
		'v9_ads_rep'       => '<strong>Repeats an ad down a long article</strong>, every few paragraphs, up to a maximum.<ol><li>Paste an <em>In-article</em> unit code.</li><li>Choose how often (for example every 5 paragraphs) and the maximum number of repeats.</li><li>Tick <em>On</em> and Save.</li></ol>Longer articles show more ads; short ones show fewer.',
		'v9_ads_bot'       => '<strong>One ad at the end of each article.</strong><ol><li>Create a <em>Display</em> unit in AdSense and copy its code.</li><li>Paste it here, tick <em>On</em> and Save.</li></ol>',
		'v9_ads_zone_header'  => '<strong>An ad in the page header.</strong> Only works if your theme has a header ad area (the Viral Reader theme does). Paste a unit&rsquo;s code, tick <em>On</em> and Save. A theme without that area simply ignores it.',
		'v9_ads_zone_sidebar' => '<strong>An ad in the sidebar.</strong> Needs a theme with a sidebar ad area (Viral Reader has one). Paste a unit&rsquo;s code, tick <em>On</em> and Save.',
		'v9_ads_zone_footer'  => '<strong>An ad in the page footer.</strong> Needs a theme with a footer ad area. Paste a unit&rsquo;s code, tick <em>On</em> and Save.',
		'v9_ads_custom'       => '<strong>Extra ads exactly where you want them</strong> (up to 10).<ol><li>Click <em>+ Add placement</em>.</li><li>Pick a position: after paragraph N, top of article, or before related posts.</li><li>Paste the AdSense code and Save.</li></ol>The density limits above still apply. Empty rows are dropped when you save.',

		'v9_skip_dupe_titles' => '<strong>Prevents duplicate posts.</strong> If a post with the same title already exists, the new one is skipped.<br>Tick this and Save before re-uploading a batch that may overlap what you already published.<br><em>Default: OFF.</em>',
		'v9_min_words'        => '<strong>Skips very short articles.</strong> Enter the smallest word count you accept (for example 300). Shorter posts are not published. 0 means no minimum.',
		'v9_disable_comments' => '<strong>Closes comments</strong> on every post this plugin publishes, which avoids spam. Existing posts are not changed.<br><em>Default: OFF.</em>',
		'v9_clean_media'      => '<strong>Tidies imported images.</strong> Renames each file from the post title (better for image search) and removes hidden camera/location data.<br>Applies to newly imported images only.<br><em>Default: OFF.</em>',
		'v9_fb_comment_template' => '<strong>The text for the first comment</strong> you post under each Facebook share.<br>Write a short line and put <code>{{link}}</code> where the article link should appear. Leave blank to export just the link.<br>Example: <code>Full story here: {{link}}</code>',
		'v9_webp'             => '<strong>Makes images smaller</strong> by converting new downloads to WebP, so pages load faster. If your server cannot convert, the original image is kept automatically.<br><em>Default: ON.</em>',

		'v9_fb_domain_verify' => '<strong>Proves to Meta that you own this domain.</strong><ol><li>In Meta Business Suite open <em>Settings &rarr; Brand safety &rarr; Domains</em> and add your domain.</li><li>Choose <em>Add a meta-tag</em>.</li><li>Copy only the value inside <code>content="..."</code> and paste it here.</li><li>Save, clear any page cache, then click <em>Verify</em> in Meta.</li></ol>Optional; blank adds nothing to your pages.',
		'v9_fb_app_id'        => '<strong>Optional.</strong> If you have a Meta for Developers app, paste its numeric App ID here. It removes the &ldquo;missing fb:app_id&rdquo; warning in Facebook&rsquo;s Sharing Debugger.<br>Find it at developers.facebook.com in your app&rsquo;s <em>Settings &rarr; Basic</em>. Leave blank if you have none.',

		'v9_llms_txt'         => '<strong>Helps AI assistants find your content.</strong> Serves two small text files, <code>/llms.txt</code> and <code>/ai.txt</code>, built from your live posts.<br>Tick, Save, then open <code>/llms.txt</code> on your site to check. A real file at your site root always wins.<br><em>Default: OFF.</em>',

		'v9_dup_threshold'    => '<strong>How alike two posts must be to count as duplicates.</strong> Lower finds looser matches (more false alarms); higher is stricter.<br><strong>How to use it:</strong> keep 0.42, click <em>Scan</em>, untick anything that is not really a duplicate, then <em>Move checked to Trash</em>. Trash is recoverable.',

		'v9_ads_txt'          => '<strong>Lets ad networks confirm you are an authorised seller.</strong><ol><li>In AdSense open <em>Sites</em> and copy your <code>ads.txt</code> line (it looks like <code>google.com, pub-..., DIRECT, f08c47fec0942fa0</code>).</li><li>Paste it here, one line per network, and Save.</li></ol>Served at <code>/ads.txt</code> unless a real file already exists. Blank turns it off.',

		'v9_indexnow_enabled' => '<strong>Tells Bing and other search engines about each new post straight away</strong> so it is found in minutes. (Google does not use IndexNow; submit your sitemap in Search Console for Google.)<br>Tick and Save; nothing else to set up.<br><em>Default: OFF.</em>',
		'v9_indexnow_key'     => '<strong>Your IndexNow key file</strong>, created for you. Click the link: it should open a page showing the key. If it does, search engines can verify your pings.',
	) );
} );
