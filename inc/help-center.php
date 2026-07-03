<?php
/**
 * TSA Help Center — packaged, admin-facing documentation.
 *
 * Docs live in /docs/*.md (numeric-prefixed for ordering) and are rendered by a
 * small built-in Markdown converter — no third-party dependency to ship/update.
 *
 * Surfaces:
 *   • wp-admin → "TSA Help" menu (manage_woocommerce)
 *   • Front-end → [tsa_help] shortcode, gated to store managers/admins
 *
 * Add a new article = drop a new .md file in /docs/. That's it.
 */
defined( 'ABSPATH' ) || exit;

/* ──────────────────────────────────────────────────────────────────
   Markdown → HTML  (supports the subset our docs use:
   headings, paragraphs, bold/italic/code, lists, links, tables,
   blockquotes, fenced code, horizontal rules)
────────────────────────────────────────────────────────────────── */
function tsa_md_inline( $text ) {
	$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

	// Protect inline code so bold/italic/link rules don't touch its contents.
	$codes = [];
	$text  = preg_replace_callback( '/`([^`]+)`/', function ( $m ) use ( &$codes ) {
		$key           = "\x00C" . count( $codes ) . "\x00";
		$codes[ $key ] = '<code>' . $m[1] . '</code>';
		return $key;
	}, $text );

	$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
	$text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text );
	$text = preg_replace( '/(?<![\w])_([^_]+)_(?![\w])/', '<em>$1</em>', $text );
	$text = preg_replace_callback( '/\[([^\]]+)\]\(([^)]+)\)/', function ( $m ) {
		$url  = $m[2];
		$safe = preg_match( '#^(https?:|mailto:|/|\#)#i', $url ) ? $url : '#';
		return '<a href="' . esc_attr( $safe ) . '">' . $m[1] . '</a>';
	}, $text );

	return strtr( $text, $codes ); // restore protected code spans
}

function tsa_md_table_row( $line ) {
	$line = trim( $line );
	$line = preg_replace( '/^\|/', '', $line );
	$line = preg_replace( '/\|$/', '', $line );
	return array_map( 'trim', explode( '|', $line ) );
}

function tsa_md_to_html( $md ) {
	$md    = str_replace( "\r\n", "\n", (string) $md );
	$lines = explode( "\n", $md );
	$n     = count( $lines );
	$i     = 0;
	$html  = '';
	$para  = [];

	$flush = function () use ( &$para, &$html ) {
		if ( $para ) {
			$html .= '<p>' . tsa_md_inline( implode( ' ', $para ) ) . "</p>\n";
			$para  = [];
		}
	};

	while ( $i < $n ) {
		$line = rtrim( $lines[ $i ] );

		// Blank line
		if ( trim( $line ) === '' ) { $flush(); $i++; continue; }

		// Fenced code block
		if ( strpos( $line, '```' ) === 0 ) {
			$flush();
			$i++;
			$code = [];
			while ( $i < $n && strpos( rtrim( $lines[ $i ] ), '```' ) !== 0 ) { $code[] = $lines[ $i ]; $i++; }
			$i++; // closing fence
			$html .= '<pre class="tsa-help-pre"><code>' . htmlspecialchars( implode( "\n", $code ), ENT_QUOTES, 'UTF-8' ) . "</code></pre>\n";
			continue;
		}

		// Horizontal rule
		if ( preg_match( '/^(-{3,}|\*{3,}|_{3,})$/', trim( $line ) ) ) { $flush(); $html .= "<hr>\n"; $i++; continue; }

		// Heading
		if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $m ) ) {
			$flush();
			$lvl   = strlen( $m[1] );
			$id    = sanitize_title( $m[2] );
			$html .= "<h{$lvl} id=\"" . esc_attr( $id ) . "\">" . tsa_md_inline( $m[2] ) . "</h{$lvl}>\n";
			$i++;
			continue;
		}

		// Table (header row + "---" separator row)
		if ( strpos( $line, '|' ) !== false
			&& $i + 1 < $n
			&& preg_match( '/^\s*\|?[\s:|-]+\|?\s*$/', $lines[ $i + 1 ] )
			&& strpos( $lines[ $i + 1 ], '-' ) !== false ) {
			$flush();
			$header = tsa_md_table_row( $line );
			$i     += 2;
			$rows   = [];
			while ( $i < $n && strpos( $lines[ $i ], '|' ) !== false && trim( $lines[ $i ] ) !== '' ) {
				$rows[] = tsa_md_table_row( rtrim( $lines[ $i ] ) );
				$i++;
			}
			$html .= '<table class="tsa-help-table"><thead><tr>';
			foreach ( $header as $c ) { $html .= '<th>' . tsa_md_inline( $c ) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$html .= '<tr>';
				foreach ( $r as $c ) { $html .= '<td>' . tsa_md_inline( $c ) . '</td>'; }
				$html .= '</tr>';
			}
			$html .= "</tbody></table>\n";
			continue;
		}

		// Blockquote
		if ( preg_match( '/^>\s?(.*)$/', $line, $m ) ) {
			$flush();
			$quote = [];
			while ( $i < $n && preg_match( '/^>\s?(.*)$/', rtrim( $lines[ $i ] ), $mm ) ) { $quote[] = $mm[1]; $i++; }
			$html .= '<blockquote>' . tsa_md_inline( implode( ' ', $quote ) ) . "</blockquote>\n";
			continue;
		}

		// Unordered list
		if ( preg_match( '/^[-*+]\s+(.*)$/', $line ) ) {
			$flush();
			$items = [];
			while ( $i < $n && preg_match( '/^[-*+]\s+(.*)$/', rtrim( $lines[ $i ] ), $mm ) ) { $items[] = $mm[1]; $i++; }
			$html .= "<ul>\n";
			foreach ( $items as $it ) { $html .= '<li>' . tsa_md_inline( $it ) . "</li>\n"; }
			$html .= "</ul>\n";
			continue;
		}

		// Ordered list
		if ( preg_match( '/^\d+\.\s+(.*)$/', $line ) ) {
			$flush();
			$items = [];
			while ( $i < $n && preg_match( '/^\d+\.\s+(.*)$/', rtrim( $lines[ $i ] ), $mm ) ) { $items[] = $mm[1]; $i++; }
			$html .= "<ol>\n";
			foreach ( $items as $it ) { $html .= '<li>' . tsa_md_inline( $it ) . "</li>\n"; }
			$html .= "</ol>\n";
			continue;
		}

		// Paragraph text (accumulate consecutive lines)
		$para[] = $line;
		$i++;
	}
	$flush();

	return $html;
}

/* ──────────────────────────────────────────────────────────────────
   Doc discovery — /docs/*.md, ordered by numeric filename prefix.
   First "# Heading" becomes the article title.
────────────────────────────────────────────────────────────────── */
function tsa_help_docs() {
	static $cache = null;
	if ( $cache !== null ) { return $cache; }
	$cache = [];

	$dir = get_stylesheet_directory() . '/docs';
	if ( ! is_dir( $dir ) ) { return $cache; }

	$files = glob( $dir . '/*.md' );
	if ( ! $files ) { return $cache; }
	sort( $files );

	foreach ( $files as $file ) {
		$base = basename( $file, '.md' );
		if ( ! preg_match( '/^\d+[-_]/', $base ) ) { continue; } // numbered docs only — keeps internal *-design.md specs out of the Help Center
		$slug = preg_replace( '/^\d+[-_]/', '', $base );       // strip "40-" prefix
		$md   = file_get_contents( $file );
		if ( $md === false ) { continue; }

		$title = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		if ( preg_match( '/^\s*#\s+(.+)$/m', $md, $m ) ) {
			$title = trim( $m[1] );
			$md    = preg_replace( '/^\s*#\s+.+$/m', '', $md, 1 ); // drop title line from body
		}

		$cache[] = [
			'slug'  => sanitize_title( $slug ),
			'title' => $title,
			'html'  => tsa_md_to_html( $md ),
		];
	}

	return $cache;
}

/* ──────────────────────────────────────────────────────────────────
   Render — shared by the admin page and the [tsa_help] shortcode.
────────────────────────────────────────────────────────────────── */
function tsa_help_render() {
	$docs = tsa_help_docs();
	if ( ! $docs ) {
		return '<p style="padding:24px;color:#6d6268">No help articles found yet (look in the theme <code>/docs</code> folder).</p>';
	}

	$active = isset( $_GET['doc'] ) ? sanitize_title( wp_unslash( $_GET['doc'] ) ) : $docs[0]['slug'];
	$found  = false;
	foreach ( $docs as $d ) { if ( $d['slug'] === $active ) { $found = true; break; } }
	if ( ! $found ) { $active = $docs[0]['slug']; }

	tsa_help_assets();

	ob_start();
	?>
	<div class="tsa-help">
		<aside class="tsa-help-nav">
			<input type="search" class="tsa-help-search" placeholder="Search help&hellip;" aria-label="Search help">
			<ul class="tsa-help-nav__list">
				<?php foreach ( $docs as $d ) : ?>
				<li><a href="#<?php echo esc_attr( $d['slug'] ); ?>" class="tsa-help-nav__link<?php echo $d['slug'] === $active ? ' is-active' : ''; ?>" data-doc="<?php echo esc_attr( $d['slug'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<p class="tsa-help-nav__empty" hidden>No matches.</p>
		</aside>
		<main class="tsa-help-main">
			<?php foreach ( $docs as $d ) : ?>
			<article class="tsa-help-article<?php echo $d['slug'] === $active ? ' is-active' : ''; ?>" id="<?php echo esc_attr( $d['slug'] ); ?>" data-doc="<?php echo esc_attr( $d['slug'] ); ?>" data-title="<?php echo esc_attr( $d['title'] ); ?>">
				<h1 class="tsa-help-article__title"><?php echo esc_html( $d['title'] ); ?></h1>
				<div class="tsa-help-article__body"><?php echo $d['html']; // phpcs:ignore WordPress.Security.EscapeOutput — converter escapes text internally ?></div>
			</article>
			<?php endforeach; ?>
		</main>
	</div>
	<?php
	return ob_get_clean();
}

function tsa_help_assets() {
	static $printed = false;
	if ( $printed ) { return; }
	$printed = true;
	?>
	<style>
	.tsa-help{--h-line:rgba(37,33,36,.12);display:flex;gap:28px;align-items:flex-start;max-width:1180px;margin:0 auto;font-size:15px;color:var(--tsa-dark,#252124)}
	.tsa-help *{box-sizing:border-box}
	.tsa-help-nav{flex:0 0 240px;position:sticky;top:20px}
	.tsa-help-search{width:100%;padding:9px 12px;border:1.5px solid var(--h-line);border-radius:8px;font-size:13px;margin-bottom:12px;background:#fff}
	.tsa-help-search:focus{outline:none;border-color:var(--tsa-gold,#d8a85f);box-shadow:0 0 0 3px rgba(216,168,95,.18)}
	.tsa-help-nav__list{list-style:none;margin:0;padding:0}
	.tsa-help-nav__list li{margin:0}
	.tsa-help-nav__link{display:block;padding:8px 11px;border-radius:7px;color:var(--tsa-dark,#252124);text-decoration:none;font-size:13.5px;font-weight:600;line-height:1.3}
	.tsa-help-nav__link:hover{background:var(--tsa-pink-soft,#fff1f0)}
	.tsa-help-nav__link.is-active{background:var(--tsa-dark,#252124);color:#fff}
	.tsa-help-nav__empty{font-size:13px;color:#6d6268;padding:8px 11px}
	.tsa-help-main{flex:1;min-width:0;background:#fff;border:1px solid var(--h-line);border-radius:14px;padding:30px 34px}
	.tsa-help-article{display:none}
	.tsa-help-article.is-active{display:block}
	.tsa-help-article__title{font-size:27px;font-weight:900;letter-spacing:-.5px;margin:0 0 18px;padding-bottom:14px;border-bottom:2px solid var(--tsa-gold,#d8a85f)}
	.tsa-help-article__body h2{font-size:20px;font-weight:800;margin:28px 0 10px;color:var(--tsa-dark,#252124)}
	.tsa-help-article__body h3{font-size:16px;font-weight:800;margin:22px 0 8px;color:var(--tsa-dark,#252124)}
	.tsa-help-article__body p{line-height:1.65;margin:0 0 14px}
	.tsa-help-article__body ul,.tsa-help-article__body ol{margin:0 0 14px;padding-left:22px;line-height:1.7}
	.tsa-help-article__body li{margin:3px 0}
	.tsa-help-article__body code{background:var(--tsa-pink-soft,#fff1f0);border:1px solid var(--h-line);border-radius:5px;padding:1px 6px;font-size:.88em;font-family:ui-monospace,Menlo,Consolas,monospace;color:var(--tsa-gold-dark,#a8772f)}
	.tsa-help-pre{background:#252124;color:#fdf6e3;padding:14px 16px;border-radius:10px;overflow:auto;margin:0 0 16px}
	.tsa-help-pre code{background:none;border:0;color:inherit;padding:0}
	.tsa-help-table{width:100%;border-collapse:collapse;margin:0 0 18px;font-size:13.5px}
	.tsa-help-table th,.tsa-help-table td{border:1px solid var(--h-line);padding:8px 11px;text-align:left;vertical-align:top}
	.tsa-help-table th{background:var(--tsa-pink-soft,#fff1f0);font-weight:800}
	.tsa-help-article__body blockquote{margin:0 0 16px;padding:12px 16px;background:#fff8e6;border-left:4px solid var(--tsa-gold,#d8a85f);border-radius:0 8px 8px 0;color:#5b4a1e}
	.tsa-help-article__body hr{border:0;border-top:1px solid var(--h-line);margin:24px 0}
	.tsa-help-article__body a{color:var(--tsa-gold-dark,#a8772f);font-weight:600}
	@media(max-width:860px){.tsa-help{flex-direction:column}.tsa-help-nav{position:static;flex:0 0 auto;width:100%}}
	</style>
	<script>
	(function(){
		var root=document.querySelector('.tsa-help'); if(!root) return;
		var links=[].slice.call(root.querySelectorAll('.tsa-help-nav__link'));
		var arts =[].slice.call(root.querySelectorAll('.tsa-help-article'));
		var search=root.querySelector('.tsa-help-search');
		var empty=root.querySelector('.tsa-help-nav__empty');
		function show(slug){
			var ok=false;
			arts.forEach(function(a){ var on=a.getAttribute('data-doc')===slug; a.classList.toggle('is-active',on); if(on) ok=true; });
			if(!ok) return;
			links.forEach(function(l){ l.classList.toggle('is-active', l.getAttribute('data-doc')===slug); });
			if(history.replaceState){ try{ history.replaceState(null,'','#'+slug); }catch(e){} }
			try{ window.scrollTo({ top: root.getBoundingClientRect().top+window.pageYOffset-20, behavior:'smooth' }); }catch(e){}
		}
		links.forEach(function(l){ l.addEventListener('click', function(e){ e.preventDefault(); show(l.getAttribute('data-doc')); }); });
		if(location.hash){ show(location.hash.slice(1)); }
		if(search){
			var idx={}; arts.forEach(function(a){ idx[a.getAttribute('data-doc')]=(a.getAttribute('data-title')+' '+a.textContent).toLowerCase(); });
			search.addEventListener('input', function(){
				var q=search.value.trim().toLowerCase(), any=false;
				links.forEach(function(l){
					var hit = !q || (idx[l.getAttribute('data-doc')]||'').indexOf(q)!==-1;
					l.parentNode.style.display = hit ? '' : 'none';
					if(hit) any=true;
				});
				if(empty) empty.hidden = any;
			});
		}
	}());
	</script>
	<?php
}

/* ──────────────────────────────────────────────────────────────────
   Surfaces: admin menu + front-end shortcode
────────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_menu_page(
		'TSA Help',
		'TSA Help',
		'manage_woocommerce',
		'tsa-help',
		'tsa_help_admin_page',
		'dashicons-sos',
		58
	);
}, 11 );

function tsa_help_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	echo '<div class="wrap"><h1 style="margin-bottom:16px">Help &amp; Documentation</h1>';
	echo tsa_help_render(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

/* Toolbar "Help" link — visible on front-end + admin for store managers. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	$bar->add_node( [
		'id'    => 'tsa-help',
		'title' => 'Help',
		'href'  => admin_url( 'admin.php?page=tsa-help' ),
		'meta'  => [ 'title' => 'TSA Help & Documentation' ],
	] );
}, 80 );

add_shortcode( 'tsa_help', function () {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
		return '<p style="padding:28px;text-align:center;color:#6d6268">The Help Center is available to store managers. Please log in with a manager account.</p>';
	}
	return tsa_help_render();
} );
