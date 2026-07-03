<?php
/**
 * Template Name: TSA School Directory
 *
 * Lists all TSA partner schools grouped by level (High / Middle / Primary).
 * Assign to: /schools/
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* ── School data ──────────────────────────────────────────────────────────────
 * status: 'live' | 'coming-soon'
 * url:    only set for live stores
 * ──────────────────────────────────────────────────────────────────────────── */
$school_groups = function_exists( 'tsa_school_directory' ) ? tsa_school_directory() : [];

$live_count  = 1;
$total_count = 32;
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-sd-hero">
    <div class="tsa-sd-container">
        <div class="tsa-kicker tsa-kicker--pink">School Spirit Stores</div>
        <h1>Find Your School</h1>
        <p class="tsa-sd-hero__sub">Dedicated online stores for every school in Ascension Parish. Shop gear, support your programs, and rep your school — all in one place.</p>
        <div class="tsa-sd-stats">
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num"><?php echo $total_count; ?></span>
                <span class="tsa-sd-stat__label">Partner Schools</span>
            </div>
            <div class="tsa-sd-stat-divider"></div>
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num tsa-sd-stat__num--live"><?php echo $live_count; ?></span>
                <span class="tsa-sd-stat__label">Stores Live Now</span>
            </div>
            <div class="tsa-sd-stat-divider"></div>
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num"><?php echo $total_count - $live_count; ?></span>
                <span class="tsa-sd-stat__label">Coming Soon</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     LIVE STORE SPOTLIGHT
══════════════════════════════════════ -->
<section class="tsa-sd-spotlight-wrap">
    <div class="tsa-sd-container">
        <?php
        $spot     = function_exists( 'tsa_get_school_colors' ) ? tsa_get_school_colors( 'Dutchtown High School' ) : [ 'primary' => '#592C82', 'secondary' => '#C7C9C8' ];
        $spot_txt = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $spot['secondary'] ) : '#1a1018';
        ?>
        <div class="tsa-sd-spotlight" style="background:linear-gradient(135deg,<?php echo esc_attr( $spot['primary'] ); ?>,<?php echo esc_attr( $spot['primary'] ); ?>e6)">
            <div class="tsa-sd-spotlight__badge">● Live Now</div>
            <div class="tsa-sd-spotlight__body">
                <div class="tsa-sd-spotlight__school">Dutchtown High School</div>
                <div class="tsa-sd-spotlight__sub">Griffins · Color Guard + Marching Band stores open now</div>
                <div class="tsa-sd-spotlight__tags">
                    <span class="tsa-sd-tag tsa-sd-tag--live">Color Guard</span>
                    <span class="tsa-sd-tag tsa-sd-tag--live">Marching Band</span>
                    <span class="tsa-sd-tag">New Drops Weekly</span>
                </div>
            </div>
            <a href="<?php echo esc_url( home_url( '/schools/dutchtown/' ) ); ?>" class="tsa-sd-spotlight__cta"
               style="background:<?php echo esc_attr( $spot['secondary'] ); ?>;color:<?php echo esc_attr( $spot_txt ); ?>">
                Shop Dutchtown →
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     SCHOOL GROUPS
══════════════════════════════════════ -->
<section class="tsa-sd-directory">
    <div class="tsa-sd-container">

        <?php foreach ( $school_groups as $group_name => $schools ) :
            $group_live  = count( array_filter( $schools, function($s) { return $s['status'] === 'live'; } ) );
            $group_total = count( $schools );
        ?>
        <div class="tsa-sd-group">
            <div class="tsa-sd-group__header">
                <h2 class="tsa-sd-group__title"><?php echo esc_html( $group_name ); ?></h2>
                <span class="tsa-sd-group__count">
                    <?php echo $group_total; ?> schools
                    <?php if ( $group_live > 0 ) : ?>
                    &nbsp;·&nbsp; <span class="tsa-sd-count-live"><?php echo $group_live; ?> live</span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="tsa-sd-grid">
                <?php foreach ( $schools as $school ) :
                    $is_live = $school['status'] === 'live';
                    $initials = '';
                    $words = explode( ' ', $school['name'] );
                    foreach ( $words as $w ) {
                        if ( ! in_array( strtolower($w), [ 'high', 'middle', 'elementary', 'primary', 'school', 'of', 'and', 'the' ], true ) ) {
                            $initials .= strtoupper( substr( $w, 0, 1 ) );
                        }
                        if ( strlen($initials) >= 2 ) break;
                    }
                ?>

                <?php
                // Resolve colors from the Brand Guide: explicit (high schools) → name match
                // (feeder schools) → Ascension district palette (everything else).
                $sc     = function_exists( 'tsa_get_school_colors' ) ? tsa_get_school_colors( $school['name'] ) : [ 'primary' => '#5D4777', 'secondary' => '#9991A4' ];
                $color1 = isset( $school['color']  ) ? $school['color']  : $sc['primary'];
                $color2 = isset( $school['color2'] ) ? $school['color2'] : $sc['secondary'];
                ?>

                <?php if ( $is_live ) : ?>
                <a href="<?php echo esc_url( home_url( $school['url'] ) ); ?>" class="tsa-sd-card tsa-sd-card--live">
                    <div class="tsa-sd-card__stripe" style="background:linear-gradient(135deg,<?php echo esc_attr($color1); ?>,<?php echo esc_attr($color2); ?>)"></div>
                    <div class="tsa-sd-card__avatar" style="background:<?php echo esc_attr($color1); ?>;color:#fff">
                        <?php echo esc_html( $initials ); ?>
                    </div>
                    <div class="tsa-sd-card__body">
                        <div class="tsa-sd-card__name"><?php echo esc_html( $school['name'] ); ?></div>
                        <?php if ( isset( $school['mascot'] ) ) : ?>
                        <div class="tsa-sd-card__mascot"><?php echo esc_html( $school['mascot'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tsa-sd-card__right">
                        <div class="tsa-sd-card__status tsa-sd-card__status--live"><span class="tsa-sd-dot"></span> Live</div>
                        <div class="tsa-sd-card__arrow">→</div>
                    </div>
                </a>

                <?php else : ?>
                <div class="tsa-sd-card tsa-sd-card--soon">
                    <div class="tsa-sd-card__stripe" style="background:<?php echo esc_attr($color1); ?>;opacity:.35"></div>
                    <div class="tsa-sd-card__avatar tsa-sd-card__avatar--muted" style="background:<?php echo esc_attr($color1); ?>22;color:<?php echo esc_attr($color1); ?>">
                        <?php echo esc_html( $initials ); ?>
                    </div>
                    <div class="tsa-sd-card__body">
                        <div class="tsa-sd-card__name"><?php echo esc_html( $school['name'] ); ?></div>
                        <?php if ( isset( $school['mascot'] ) ) : ?>
                        <div class="tsa-sd-card__mascot tsa-sd-card__mascot--muted"><?php echo esc_html( $school['mascot'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tsa-sd-card__right">
                        <div class="tsa-sd-card__status tsa-sd-card__status--soon">Soon</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- ══════════════════════════════════════
     CTA — GET YOUR SCHOOL
══════════════════════════════════════ -->
<section class="tsa-sd-cta">
    <div class="tsa-sd-container">
        <div class="tsa-sd-cta__inner">
            <div class="tsa-sd-cta__copy">
                <h2>Don't see your school?</h2>
                <p>We're rolling out new stores every month. Reach out and we'll get your school set up — no upfront cost, no minimums.</p>
            </div>
            <div class="tsa-sd-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-store/?type=school' ) ); ?>" class="tsa-btn tsa-btn-primary">Request Your School Store</a>
                <a href="<?php echo esc_url( home_url( '/schools/' ) ); ?>" class="tsa-btn tsa-btn-outline">Learn How It Works</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
