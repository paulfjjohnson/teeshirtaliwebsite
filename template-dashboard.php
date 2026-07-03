<?php
/**
 * Template Name: TSA Owner Dashboard
 *
 * Command center — owner-only (manage_woocommerce), gated by the `dashboard`
 * feature. Theme-token styled (auto tenant-recolor). Cards open slide-over
 * detail panels via AJAX. Assign to: /dashboard/
 */
defined( 'ABSPATH' ) || exit;
get_header();

$gsb_on = ! function_exists( 'tsa_feature_active' ) || tsa_feature_active( 'dashboard' );

if ( ! function_exists( 'tsa_dash_can' ) || ! tsa_dash_can() || ! $gsb_on ) :
    echo '<section class="tsa-section" style="text-align:center;padding:80px 7%"><h1>Dashboard</h1><p style="color:var(--tsa-muted)">'
        . ( $gsb_on ? 'You need manager access to view this dashboard.' : 'This tool isn\'t included on your current plan.' )
        . '</p></section>';
    get_footer(); return;
endif;

$sales    = tsa_dash_sales( '7d' );
$pipe     = tsa_dash_pipeline();
$cur      = get_woocommerce_currency_symbol();
$has_tp   = function_exists( 'tsa_feature_active' ) ? tsa_feature_active( 'tee_parties' ) : true;
$has_fr   = function_exists( 'tsa_feature_active' ) ? tsa_feature_active( 'fundraising' ) : true;
$has_amb  = function_exists( 'tsa_feature_active' ) ? tsa_feature_active( 'ambassadors' ) : true;

// Health counts (cheap)
$cost_missing = count( get_posts( [ 'post_type' => 'configurator_garment', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => [ 'relation' => 'OR', [ 'key' => '_tsa_garment_cost', 'compare' => 'NOT EXISTS' ], [ 'key' => '_tsa_garment_cost', 'value' => '0', 'compare' => '<=' ] ] ] ) );
$soon_count   = 0;
if ( function_exists( 'tsa_school_store_records' ) ) foreach ( tsa_school_store_records() as $r ) if ( $r['status'] !== 'live' ) $soon_count++;
$draft_designs = (int) ( wp_count_posts( 'tsa_design' )->draft ?? 0 );
?>

<div class="tsa-dash-root">
    <div class="tsa-dash-wrap">

        <div class="tsa-dash-head">
            <div><div class="tsa-dash-hello">Your shop</div><div class="tsa-dash-sub"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> · all stores</div></div>
            <div class="tsa-dash-tf" id="tsa-dash-tf">
                <button data-tf="today">Today</button>
                <button data-tf="7d" class="is-active">7 days</button>
                <button data-tf="30d">30 days</button>
                <button data-tf="all">All</button>
            </div>
        </div>

        <div id="tsa-dash-kpis-wrap"><?php echo tsa_dash_render_kpis( $sales ); ?></div>

        <?php if ( function_exists( 'tsa_admin_hub_launcher' ) ) tsa_admin_hub_launcher(); ?>

        <?php if ( function_exists( 'tsa_order_tickets_panel' ) ) tsa_order_tickets_panel(); ?>

        <!-- Production pipeline -->
        <div class="tsa-dash-card tsa-dash-pipe">
            <div class="tsa-dash-card__head">
                <span class="tsa-dash-card__title">Production pipeline</span>
                <?php $need = ( $pipe['counts']['artwork-review'] ?? 0 ) + ( $pipe['counts']['processing'] ?? 0 ); ?>
                <?php if ( $need ) : ?><span class="tsa-dash-chip"><?php echo (int) $need; ?> need action</span><?php endif; ?>
            </div>
            <div class="tsa-dash-lanes">
                <?php foreach ( $pipe['lanes'] as $st => $lbl ) : ?>
                <button class="tsa-dash-lane<?php echo $st === 'artwork-review' ? ' is-alert' : ''; ?>" data-card="lane:<?php echo esc_attr( $st ); ?>">
                    <strong><?php echo (int) ( $pipe['counts'][ $st ] ?? 0 ); ?></strong><span><?php echo esc_html( $lbl ); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php if ( $pipe['queue'] ) : ?>
            <div class="tsa-dash-queue">
                <?php foreach ( $pipe['queue'] as $q ) : ?>
                <a class="tsa-dash-qrow" href="<?php echo esc_url( $q['url'] ); ?>">
                    <span><strong>#<?php echo esc_html( $q['num'] ); ?></strong> · <?php echo esc_html( tsa_dash_store_name( sanitize_title( (string) $q['store'] ) ) ); ?><br><em><?php echo esc_html( $q['status'] ); ?></em></span>
                    <span><?php echo wp_kses_post( $q['total'] ); ?> ›</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="tsa-dash-perf-wrap"><?php echo tsa_dash_render_performance( $sales ); ?></div>

        <?php if ( $has_tp || $has_fr || $has_amb ) : ?>
        <div class="tsa-dash-engage">
            <?php
            // Drops
            if ( $has_tp && function_exists( 'tsa_party_status' ) ) {
                $parties = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 30, 'meta_query' => [ [ 'key' => '_tsa_party_schedule', 'compare' => 'EXISTS' ] ] ] );
                $live = [];
                foreach ( $parties as $pp ) { $stt = tsa_party_status( $pp->ID ); if ( in_array( $stt['status'], [ 'live', 'grace', 'upcoming' ], true ) ) $live[] = [ $pp, $stt ]; }
                if ( $live ) {
                    echo '<div class="tsa-dash-card"><div class="tsa-dash-tag">Drops</div>';
                    foreach ( array_slice( $live, 0, 3 ) as $pair ) { list( $pp, $stt ) = $pair;
                        echo '<a class="tsa-dash-drow" href="' . esc_url( get_permalink( $pp->ID ) ) . '"><span>' . esc_html( get_post_meta( $pp->ID, '_tsa_party_theme', true ) ?: get_the_title( $pp ) ) . '</span><span>' . ( $stt['status'] === 'upcoming' ? 'Upcoming' : ( (int) $stt['revealed'] . '/' . (int) $stt['total_designs'] . ' live' ) ) . '</span></a>';
                    }
                    echo '</div>';
                }
            }
            // Fundraisers
            if ( $has_fr && function_exists( 'tsa_fundraiser_report' ) ) {
                $frs = get_posts( [ 'post_type' => 'tsa_fundraiser', 'post_status' => 'publish', 'numberposts' => 3 ] );
                if ( $frs ) {
                    echo '<div class="tsa-dash-card"><div class="tsa-dash-tag">Fundraisers</div>';
                    foreach ( $frs as $f ) { $r = tsa_fundraiser_report( $f->ID ); $pct = $r['goal'] > 0 ? (int) $r['goal_pct'] : 0;
                        echo '<button class="tsa-dash-fr" data-card="fundraiser:' . (int) $f->ID . '"><span class="frn">' . esc_html( get_the_title( $f ) ) . '</span><span class="frbar"><span style="width:' . $pct . '%"></span></span><span class="frv">' . ( $r['goal'] > 0 ? $pct . '% · ' : '' ) . $cur . number_format( $r['payout'], 0 ) . '</span></button>';
                    }
                    echo '</div>';
                }
            }
            // Ambassadors
            if ( $has_amb && function_exists( 'tsa_ambassador_report' ) ) {
                $ambs = get_posts( [ 'post_type' => 'tsa_ambassador', 'post_status' => 'publish', 'numberposts' => 20 ] );
                $rows = [];
                foreach ( $ambs as $a ) { $r = tsa_ambassador_report( $a->ID ); if ( $r['units'] > 0 ) $rows[] = [ $a, $r ]; }
                usort( $rows, function( $x, $y ) { return $y[1]['units'] <=> $x[1]['units']; } );
                if ( $rows ) {
                    echo '<div class="tsa-dash-card"><div class="tsa-dash-tag">Ambassadors</div>';
                    foreach ( array_slice( $rows, 0, 4 ) as $pair ) { list( $a, $r ) = $pair;
                        echo '<button class="tsa-dash-row" data-card="ambassador:' . (int) $a->ID . '"><span>' . esc_html( get_the_title( $a ) ) . '</span><span>' . (int) $r['units'] . ' shirts</span></button>';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php endif; ?>

        <!-- Requests inbox -->
        <?php if ( function_exists( 'tsa_requests_dashboard_card' ) ) { echo tsa_requests_dashboard_card(); } // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <!-- Health alerts -->
        <div class="tsa-dash-alerts">
            <?php if ( $cost_missing ) : ?><button class="tsa-dash-alert" data-card="health:cost">⚠ <?php echo (int) $cost_missing; ?> garments missing cost basis</button><?php endif; ?>
            <?php if ( $soon_count ) : ?><button class="tsa-dash-alert" data-card="health:soon"><?php echo (int) $soon_count; ?> stores coming soon</button><?php endif; ?>
            <?php if ( $draft_designs ) : ?><a class="tsa-dash-alert" href="<?php echo esc_url( admin_url( 'edit.php?post_type=tsa_design&post_status=draft' ) ); ?>"><?php echo (int) $draft_designs; ?> draft designs</a><?php endif; ?>
        </div>

    </div>
</div>

<!-- Slide-over detail panel -->
<div class="tsa-dash-overlay" id="tsa-dash-overlay" hidden></div>
<aside class="tsa-dash-detail" id="tsa-dash-detail" aria-hidden="true" role="dialog">
    <div class="tsa-dash-detail__inner" id="tsa-dash-detail-inner"></div>
</aside>

<?php get_footer(); ?>
