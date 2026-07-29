<?php
/**
 * Template Name: TSA Store — Programs Hub
 *
 * Generic, premium programs hub (e.g. /schools/<store>/programs/). Two modes,
 * matching the Design Library / Tee Party drill pattern:
 *   • default        → program cards (image + count), grouped by category
 *   • ?program=<slug> → that program's designs → store-branded configurator
 *
 * Programs are derived from the store's design inventory (or its curated store
 * record), so this works for any store type with zero per-program pages.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$page_id = get_the_ID();
$ctx     = tsa_store_context( tsa_resolve_store_slug( $page_id ) );
$drill   = isset( $_GET['program'] ) ? sanitize_title( wp_unslash( $_GET['program'] ) ) : '';

tsa_store_brand_style( $ctx );
tsa_store_chrome_header( $ctx );
?>

<div class="dths-page">

<?php if ( $drill && $ctx['slug'] ) :
	// ── DRILL-DOWN: one program's designs ──────────────────────────────
	$term      = get_term_by( 'slug', $drill, 'tsa_design_category' );
	$prog_name = $term ? $term->name : ucwords( str_replace( '-', ' ', $drill ) );
	?>
	<section class="dths-coll-hero" style="padding:64px 0 40px;background:radial-gradient(circle at 30% 0%,rgba(var(--dths-purple-bright-rgb),.30),transparent 45%),var(--dths-black)">
		<div class="dths-shell">
			<a href="<?php echo esc_url( home_url( $ctx['base'] . 'programs/' ) ); ?>" style="color:var(--dths-muted);text-decoration:none;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">&larr; All Programs</a>
			<h1 class="dths-display" style="font-size:clamp(40px,7vw,84px);color:#fff;line-height:.92;text-transform:uppercase;margin-top:12px"><?php echo esc_html( $prog_name ); ?></h1>
			<p style="color:var(--dths-muted);margin-top:14px;font-size:16px;max-width:520px;line-height:1.6">Official <?php echo esc_html( $ctx['name'] ); ?> designs — configure on your apparel, color &amp; size.</p>
		</div>
	</section>
	<section class="dths-section" style="padding-top:40px;padding-bottom:88px">
		<div class="dths-shell">
			<?php tsa_render_store_design_grid( $ctx['slug'], $drill ); ?>
		</div>
	</section>

<?php else :
	// ── HUB: one clean grid of program cards (compact, uniform) ─────────
	$programs = $ctx['programs'];
	$hub_url  = home_url( $ctx['base'] . 'programs/' );
	?>
	<style>
	.tsa-prog-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px; }
	.tsa-prog-card{ display:flex; flex-direction:column; background:var(--dths-charcoal); border:1px solid var(--dths-line); border-radius:16px; overflow:hidden; text-decoration:none; transition:transform .15s ease, border-color .15s ease; }
	.tsa-prog-card:hover{ transform:translateY(-3px); border-color:rgba(var(--dths-purple-bright-rgb),.55); }
	.tsa-prog-card__visual{ position:relative; aspect-ratio:1/1; background:#0d0d10; display:flex; align-items:center; justify-content:center; padding:18px; box-sizing:border-box; }
	.tsa-prog-card__visual img{ max-width:100%; max-height:100%; object-fit:contain; }
	.tsa-prog-card__noimg{ font-family:'Anton',sans-serif; font-size:40px; color:rgba(var(--dths-purple-bright-rgb),.75); }
	.tsa-prog-card__arrow{ position:absolute; top:12px; right:12px; width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,.5); color:#fff; display:flex; align-items:center; justify-content:center; font-size:15px; opacity:0; transition:opacity .15s; }
	.tsa-prog-card:hover .tsa-prog-card__arrow{ opacity:1; }
	.tsa-prog-card__body{ padding:13px 15px 15px; border-top:1px solid var(--dths-line); }
	.tsa-prog-card__count{ font-size:10px; letter-spacing:.16em; text-transform:uppercase; color:var(--dths-muted); }
	.tsa-prog-card__name{ font-family:'Anton',sans-serif; text-transform:uppercase; font-size:19px; line-height:1.05; color:#fff; margin-top:3px; letter-spacing:.01em; }
	@media(max-width:520px){ .tsa-prog-grid{ grid-template-columns:repeat(2,1fr); gap:12px; } .tsa-prog-card__name{ font-size:16px; } }
	</style>

	<section class="dths-coll-hero" style="padding:64px 0 40px;background:radial-gradient(circle at 30% 0%,rgba(var(--dths-purple-bright-rgb),.30),transparent 45%),var(--dths-black)">
		<div class="dths-shell">
			<span class="dths-kicker">All Programs</span>
			<h1 class="dths-display" style="font-size:clamp(40px,7vw,80px);color:#fff;line-height:.92;text-transform:uppercase;margin-top:12px">The Programs Hub</h1>
			<p style="color:var(--dths-muted);margin-top:14px;font-size:16px;max-width:560px;line-height:1.6">Every <?php echo esc_html( $ctx['name'] ); ?> team, club and event — pick a program to shop its designs.</p>
		</div>
	</section>

	<section class="dths-section" style="padding-top:36px;padding-bottom:96px">
		<div class="dths-shell">
		<?php if ( $programs ) : ?>
			<div class="tsa-prog-grid">
				<?php foreach ( $programs as $pr ) :
					$pslug = $pr['slug'] ?? sanitize_title( $pr['name'] ?? '' );
					$count = (int) ( $pr['count'] ?? 0 );
					$href  = add_query_arg( 'program', $pslug, $hub_url );
				?>
				<a class="tsa-prog-card" href="<?php echo esc_url( $href ); ?>">
					<div class="tsa-prog-card__visual tsa-autocontrast">
						<?php if ( ! empty( $pr['image'] ) ) : ?>
						<img src="<?php echo esc_url( $pr['image'] ); ?>" alt="<?php echo esc_attr( $pr['name'] ); ?>" loading="lazy"/>
						<?php else : ?>
						<span class="tsa-prog-card__noimg"><?php echo esc_html( strtoupper( substr( $pr['name'], 0, 2 ) ) ); ?></span>
						<?php endif; ?>
						<span class="tsa-prog-card__arrow">→</span>
					</div>
					<div class="tsa-prog-card__body">
						<div class="tsa-prog-card__count"><?php echo $count; ?> design<?php echo $count === 1 ? '' : 's'; ?></div>
						<div class="tsa-prog-card__name"><?php echo esc_html( $pr['name'] ); ?></div>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p style="color:var(--dths-muted);text-align:center;padding:60px 0">No programs are set up for <?php echo esc_html( $ctx['name'] ); ?> yet.</p>
		<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

</div>

<?php
tsa_store_chrome_footer( $ctx ); // wp_footer() + closes body/html — do NOT call get_footer()
