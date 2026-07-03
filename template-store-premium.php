<?php
/**
 * Template Name: TSA Store — Premium Home
 *
 * Generic, premium store landing page (e.g. /schools/<store>/). Store-type-agnostic
 * port of the hardcoded Dutchtown home: hero, live collections, coming-soon
 * ecosystem, latest designs, value props and CTA — all driven by the store record.
 *
 * Assign to a store landing page (with _tsa_store_id, or a slug matching the store).
 */

defined( 'ABSPATH' ) || exit;

get_header();

$page_id  = get_the_ID();
$ctx      = tsa_store_context( tsa_resolve_store_slug( $page_id ) );
$programs = $ctx['programs'];
$live     = array_values( array_filter( $programs, function ( $p ) { return ( $p['status'] ?? '' ) === 'live'; } ) );
$soon     = array_values( array_filter( $programs, function ( $p ) { return ( $p['status'] ?? '' ) !== 'live'; } ) );

$prog_url = function ( $pr ) use ( $ctx ) {
	return tsa_store_program_url( $ctx, $pr['slug'] ?? sanitize_title( $pr['name'] ?? '' ) );
};

tsa_store_brand_style( $ctx );
tsa_store_chrome_header( $ctx );
?>

<div class="dths-page">

	<style>
	.tsa-prog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
	.tsa-prog-card{display:flex;flex-direction:column;background:var(--dths-charcoal);border:1px solid var(--dths-line);border-radius:16px;overflow:hidden;text-decoration:none;transition:transform .15s ease,border-color .15s ease}
	.tsa-prog-card:hover{transform:translateY(-3px);border-color:rgba(var(--dths-purple-bright-rgb),.55)}
	.tsa-prog-card__visual{position:relative;aspect-ratio:1/1;background:#0d0d10;display:flex;align-items:center;justify-content:center;padding:18px;box-sizing:border-box}
	.tsa-prog-card__visual img{max-width:100%;max-height:100%;object-fit:contain}
	.tsa-prog-card__noimg{font-family:'Anton',sans-serif;font-size:40px;color:rgba(var(--dths-purple-bright-rgb),.75)}
	.tsa-prog-card__arrow{position:absolute;top:12px;right:12px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;opacity:0;transition:opacity .15s}
	.tsa-prog-card:hover .tsa-prog-card__arrow{opacity:1}
	.tsa-prog-card__body{padding:13px 15px 15px;border-top:1px solid var(--dths-line)}
	.tsa-prog-card__count{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--dths-muted)}
	.tsa-prog-card__name{font-family:'Anton',sans-serif;text-transform:uppercase;font-size:19px;line-height:1.05;color:#fff;margin-top:3px;letter-spacing:.01em}
	@media(max-width:520px){.tsa-prog-grid{grid-template-columns:repeat(2,1fr);gap:12px}.tsa-prog-card__name{font-size:16px}}
	</style>

	<!-- HERO -->
	<section class="dths-hero dths-grain">
		<div class="dths-hero-overlay"></div>
		<div class="dths-shell dths-hero-inner">
			<span class="dths-kicker"><?php echo esc_html( $ctx['tagline'] ?: 'Official Merch Store' ); ?></span>
			<h1 class="dths-hero-h1 dths-display" style="margin-top:14px"><?php echo esc_html( strtoupper( $ctx['name'] ) ); ?></h1>
			<p class="dths-hero-sub">Premium spirit gear powered by Tee Shirt Ali — configure your design on your apparel, color and size, with live pricing and no email back-and-forth.</p>
			<div class="dths-hero-actions">
				<?php if ( $live ) : ?>
					<?php foreach ( array_slice( $live, 0, 2 ) as $i => $pr ) : ?>
					<a href="<?php echo esc_url( $prog_url( $pr ) ); ?>" class="<?php echo $i === 0 ? 'dths-btn-primary' : 'dths-btn-secondary'; ?>">SHOP <?php echo esc_html( strtoupper( $pr['name'] ) ); ?> &nbsp;→</a>
					<?php endforeach; ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( $ctx['base'] . 'programs/' ) ); ?>" class="dths-btn-primary">VIEW ALL PROGRAMS &nbsp;→</a>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- SCROLLING MARQUEE -->
	<div class="dths-marquee-wrap" aria-hidden="true">
		<div class="dths-marquee-track">
		<?php
		$mq = $ctx['ticker'];
		for ( $r = 0; $r < 2; $r++ ) { foreach ( $mq as $mi ) { echo '<span class="dths-marquee-item dths-display">' . esc_html( strtoupper( $mi ) ) . '<span class="dths-marquee-dot">&#10022;</span></span>'; } }
		?>
		</div>
	</div>

	<!-- STATS -->
	<section class="dths-section" style="padding-top:12px;padding-bottom:12px">
		<div class="dths-shell">
			<?php $stat_designs = array_sum( array_map( function ( $p ) { return (int) ( $p['count'] ?? 0 ); }, $programs ) ); ?>
			<div class="dths-stats-grid">
				<div class="dths-stat dths-glass"><div class="dths-stat-k">Programs</div><div class="dths-stat-v dths-display"><?php echo count( $programs ); ?></div><div class="dths-stat-s">Collections</div></div>
				<div class="dths-stat dths-glass"><div class="dths-stat-k">Designs</div><div class="dths-stat-v dths-display"><?php echo (int) $stat_designs; ?></div><div class="dths-stat-s">Available now</div></div>
				<div class="dths-stat dths-glass"><div class="dths-stat-k">Drops</div><div class="dths-stat-v dths-display">Scheduled</div><div class="dths-stat-s">New designs</div></div>
				<div class="dths-stat dths-glass"><div class="dths-stat-k">Powered by</div><div class="dths-stat-v dths-display" style="font-size:clamp(16px,2.2vw,24px)">TSA</div><div class="dths-stat-s">Tee Shirt Ali</div></div>
			</div>
		</div>
	</section>

	<!-- LIVE COLLECTIONS -->
	<?php if ( $live ) : ?>
	<section class="dths-section">
		<div class="dths-shell">
			<div class="dths-section-split">
				<div>
					<span class="dths-kicker">Programs</span>
					<div class="dths-section-h2 dths-display">THE COLLECTIONS</div>
				</div>
				<p class="dths-section-desc">Shop the live stores now.
					<a href="<?php echo esc_url( home_url( $ctx['base'] . 'programs/' ) ); ?>">See all programs →</a>
				</p>
			</div>
			<div class="tsa-prog-grid">
				<?php foreach ( $live as $pr ) :
					$count = (int) ( $pr['count'] ?? 0 ); ?>
				<a class="tsa-prog-card" href="<?php echo esc_url( $prog_url( $pr ) ); ?>">
					<div class="tsa-prog-card__visual">
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

			<!-- ECOSYSTEM (coming soon) -->
			<?php if ( $soon ) : ?>
			<div style="margin-top:56px">
				<div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap">
					<div>
						<div style="font-size:11px;letter-spacing:.26em;color:var(--dths-muted);text-transform:uppercase">On Deck</div>
						<div class="dths-display" style="font-size:clamp(22px,3vw,32px);color:#fff;margin-top:4px">THE FULL <?php echo esc_html( strtoupper( $ctx['name'] ) ); ?> ECOSYSTEM</div>
					</div>
					<a class="dths-btn-ghost" href="<?php echo esc_url( home_url( $ctx['base'] . 'programs/' ) ); ?>">VIEW ALL PROGRAMS &nbsp;→</a>
				</div>
				<div class="dths-eco-grid">
					<?php foreach ( array_slice( $soon, 0, 10 ) as $pr ) : ?>
					<a class="dths-eco-tile" href="<?php echo esc_url( home_url( $ctx['base'] . 'programs/' ) ); ?>">
						<div class="dths-eco-tile-inner">
							<span class="dths-eco-tile-soon">Soon</span>
							<span class="dths-eco-tile-name dths-display"><?php echo esc_html( $pr['name'] ); ?></span>
						</div>
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<!-- LATEST DESIGNS -->
	<?php
	$latest = tsa_store_designs_query( $ctx['slug'] );
	if ( $latest->post_count > 0 ) : ?>
	<section class="dths-section">
		<div class="dths-shell">
			<div class="dths-section-split">
				<div>
					<span class="dths-kicker">Fresh Ink</span>
					<div class="dths-section-h2 dths-display">LATEST DESIGNS</div>
				</div>
			</div>
			<?php tsa_render_store_design_grid( $ctx['slug'], '' ); ?>
		</div>
	</section>
	<?php endif; ?>

	<!-- VALUE PROPS -->
	<section class="dths-section">
		<div class="dths-shell">
			<div class="dths-vp-grid">
				<?php foreach ( [
					[ '✨', 'Designed for Performance', 'Premium fabrics and drop-quality printwork, built to look right on and off the floor.' ],
					[ '⚡', 'Configure It Your Way',     'Pick your design, apparel, color and sizes with live pricing — add to cart in minutes.' ],
					[ '🚀', 'Fast Fulfilment',           'Printed and shipped by Tee Shirt Ali\'s production network. Built in Georgia, delivered to your door.' ],
				] as $vp ) : ?>
				<div class="dths-vp-card dths-glass">
					<div class="dths-vp-icon"><?php echo $vp[0]; ?></div>
					<div class="dths-vp-title dths-display"><?php echo esc_html( $vp[1] ); ?></div>
					<p class="dths-vp-text"><?php echo esc_html( $vp[2] ); ?></p>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- FINAL CTA -->
	<section class="dths-section" style="padding-bottom:112px">
		<div class="dths-shell">
			<div class="dths-final-card">
				<div class="dths-final-glow"></div>
				<div class="dths-final-body">
					<span class="dths-kicker">Join The Community</span>
					<div class="dths-final-h2 dths-display">GET THE<br>NEXT DROP.</div>
					<p class="dths-final-sub">Create a free account for early access to drops and members-only releases.</p>
					<div class="dths-final-actions">
						<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-btn-primary">CREATE YOUR ACCOUNT &nbsp;→</a>
						<a href="<?php echo esc_url( home_url( $ctx['base'] . 'drops/' ) ); ?>" class="dths-btn-secondary">SEE UPCOMING DROPS</a>
					</div>
				</div>
			</div>
		</div>
	</section>

</div>

<?php
tsa_store_chrome_footer( $ctx ); // wp_footer() + closes body/html — do NOT call get_footer()
