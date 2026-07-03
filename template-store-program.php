<?php
/**
 * Template Name: TSA Store — Program
 *
 * Generic, premium per-program collection page (e.g. /schools/<store>/color-guard/).
 * Store-type-agnostic: resolves its store from the page hierarchy / store record,
 * wears the brandable premium chrome, and renders that program's designs via the
 * shared store-design grid (→ store-branded configurator). Replaces the hardcoded,
 * product-based school collection template.
 *
 * Assign to a program page whose PARENT is the store landing page.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$page_id   = get_the_ID();
$prog_slug = get_post_field( 'post_name', $page_id );   // e.g. color-guard
$prog_name = get_the_title( $page_id );
$parent_id = wp_get_post_parent_id( $page_id );

// Resolve the store robustly (meta → URL path → hierarchy). The program is this
// page's own slug (e.g. color-guard); the store is the /schools/<store>/ segment.
$store_slug = tsa_resolve_store_slug( $page_id );
$ctx        = tsa_store_context( $store_slug );

// Map this program page to its real design-category term. The page slug won't
// always equal the category slug (WP may suffix on conflicts), so fall back to
// matching the page TITLE to a category name. Use the term's true slug to filter.
$cat_term = get_term_by( 'slug', $prog_slug, 'tsa_design_category' );
if ( ! $cat_term ) $cat_term = get_term_by( 'name', $prog_name, 'tsa_design_category' );
$cat_slug = $cat_term ? $cat_term->slug : $prog_slug;

tsa_store_brand_style( $ctx );
tsa_store_chrome_header( $ctx );
?>

<div class="dths-page">

	<section class="dths-coll-hero" style="padding:64px 0 40px;background:radial-gradient(circle at 30% 0%,rgba(var(--dths-purple-bright-rgb),.30),transparent 45%),var(--dths-black)">
		<div class="dths-shell">
			<a href="<?php echo esc_url( $ctx['home_url'] ); ?>" style="color:var(--dths-muted);text-decoration:none;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">&larr; <?php echo esc_html( $ctx['name'] ); ?></a>
			<h1 class="dths-display" style="font-size:clamp(40px,7vw,84px);color:#fff;line-height:.92;text-transform:uppercase;margin-top:12px"><?php echo esc_html( $prog_name ); ?></h1>
			<p style="color:var(--dths-muted);margin-top:14px;font-size:16px;max-width:520px;line-height:1.6">Official <?php echo esc_html( $ctx['name'] ); ?> designs — configure on your apparel, color &amp; size.</p>
		</div>
	</section>

	<section class="dths-section" style="padding-top:40px;padding-bottom:88px">
		<div class="dths-shell">
		<?php
		if ( ! $store_slug ) {
			echo '<p style="color:var(--dths-muted);text-align:center;padding:60px 0">Could not determine the store for this page. Set the page parent to the store landing page, or add a <code>_tsa_store_id</code> custom field.</p>';
		} else {
			// Program-scoped first; fall back to the full store collection so the
			// store is never dead while program tagging catches up.
			$prog_q = tsa_store_designs_query( $store_slug, $cat_slug );
			if ( $prog_q->post_count > 0 ) {
				tsa_render_store_design_grid( $store_slug, $cat_slug );
			} else {
				$all_q = tsa_store_designs_query( $store_slug );
				if ( $all_q->post_count > 0 ) {
					echo '<p style="margin:0 0 20px;padding:12px 16px;background:rgba(var(--dths-purple-bright-rgb),.14);border:1px solid rgba(var(--dths-purple-bright-rgb),.32);border-radius:12px;font-size:14px;color:var(--dths-text)">No designs are tagged to <strong>' . esc_html( $prog_name ) . '</strong> yet — showing the full <strong>' . esc_html( $ctx['name'] ) . '</strong> collection.</p>';
				}
				tsa_render_store_design_grid( $store_slug, '' );
			}
		}
		?>
		</div>
	</section>

</div>

<?php
tsa_store_chrome_footer( $ctx ); // emits wp_footer() + closes body/html — do NOT call get_footer()
