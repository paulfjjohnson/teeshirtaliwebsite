<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function tsa_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

// Only the three pure functions under test are needed — extract them by
// requiring the full file (WP function stubs below cover what it touches
// at load time: add_action/add_shortcode registration calls).
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $callback ) {}

require dirname( __DIR__ ) . '/inc/school-builder.php';

// ── tsa_sb_type_choices() ──
$choices = tsa_sb_type_choices();
tsa_assert( $choices === [
    'school'   => 'School',
    'team'     => 'Team',
    'business' => 'Business',
    'event'    => 'Event',
    'main'     => 'TSA Main',
], 'Type choices must be the 5 types the plugin already allows, in a stable order.' );

// ── tsa_sb_type_meta() ──
$school = tsa_sb_type_meta( 'school' );
tsa_assert( $school['base'] === 'schools', 'School base segment must be "schools".' );
tsa_assert( $school['label'] === 'Schools', 'School label must be "Schools".' );
tsa_assert( $school['directory_template'] === 'template-school-directory.php', 'School directory template must match the existing file.' );

$team = tsa_sb_type_meta( 'team' );
tsa_assert( $team['base'] === 'teams', 'Team base segment must be "teams".' );
tsa_assert( $team['directory_template'] === 'template-team-directory.php', 'Team directory template must match the existing file.' );

$business = tsa_sb_type_meta( 'business' );
tsa_assert( $business['base'] === 'business', 'Business base segment must be "business".' );
tsa_assert( $business['directory_template'] === 'template-business-directory.php', 'Business directory template must match the existing file.' );

$event = tsa_sb_type_meta( 'event' );
tsa_assert( $event['base'] === 'events', 'Event base segment must be "events".' );
tsa_assert( $event['directory_template'] === '', 'Event has no directory template yet — must be empty, not a guess.' );

$main = tsa_sb_type_meta( 'main' );
tsa_assert( $main['base'] === 'schools', 'Main is a singleton with no section of its own — falls back to schools.' );

$unknown = tsa_sb_type_meta( 'nonsense' );
tsa_assert( $unknown['base'] === 'schools', 'Unknown type must fall back to the school config, not error.' );

// ── tsa_sb_active_from_status() ──
tsa_assert( tsa_sb_active_from_status( 'hidden' ) === '0', 'Hidden status must derive to inactive.' );
tsa_assert( tsa_sb_active_from_status( 'live' ) === '1', 'Live status must derive to active.' );
tsa_assert( tsa_sb_active_from_status( 'coming-soon' ) === '1', 'Coming-soon status must derive to active (shown, just not shoppable).' );
tsa_assert( tsa_sb_active_from_status( 'garbage' ) === '1', 'Unrecognized status must default to active, matching the Builder\'s own coming-soon default.' );

echo "PASS: school-builder type helpers\n";
