<?php
/**
 * Tee Party — Customer Drop Alerts (email).
 *
 * Subscribers register on the Tee Party page and are emailed when the party
 * OPENS and at EACH drop. Self-contained: hooks the existing tsa_party_drop_event
 * + save_post_page; never edits the drop engine. See
 * docs/tee-party-drop-alerts-design.md.
 *
 * Storage: one non-unique post-meta row per subscriber on the party page
 *   _tsa_party_subscriber  = lowercased email (add/get/delete by value, race-free)
 * Idempotency flags:
 *   _tsa_party_notified_open  = '1' once the open email has gone out
 *   _tsa_party_notified_drops = JSON array of reveal timestamps already emailed
 *
 * No nonce on the public form — the party page is full-page cached (LiteSpeed),
 * so a printed nonce would be stale. A honeypot + per-IP rate limit guard abuse;
 * worst case a stray email gets one-click unsubscribe.
 */
defined( 'ABSPATH' ) || exit;

const TSA_PARTY_SUB_META = '_tsa_party_subscriber';

/* ─── Helpers ──────────────────────────────────────────────────────── */

/** Is this post a Tee Party page? */
function tsa_party_is_party( int $pid ): bool {
	return $pid > 0
		&& get_post_type( $pid ) === 'page'
		&& get_post_meta( $pid, '_wp_page_template', true ) === 'template-tee-party.php';
}

/** Distinct, lowercased subscriber emails for a party. */
function tsa_party_subscribers( int $pid ): array {
	$emails = get_post_meta( $pid, TSA_PARTY_SUB_META, false );
	return array_values( array_unique( array_filter( array_map( 'strtolower', (array) $emails ) ) ) );
}

/** HMAC unsubscribe token for an (email, party) pair. */
function tsa_party_unsub_token( string $email, int $pid ): string {
	return hash_hmac( 'sha256', strtolower( $email ) . '|' . $pid, wp_salt( 'auth' ) );
}

/** Cheap cross-request lock to keep cron + lazy sends from overlapping. */
function tsa_party_alerts_lock( int $pid ): bool {
	$key = 'tsa_party_alerts_lock_' . $pid;
	if ( get_transient( $key ) ) return false;
	set_transient( $key, 1, 90 );
	return true;
}
function tsa_party_alerts_unlock( int $pid ): void {
	delete_transient( 'tsa_party_alerts_lock_' . $pid );
}

/* ─── Subscribe form (rendered from template-tee-party.php) ────────── */

/** Markup for the subscribe form. $variant: 'block' (hero CTA) | 'compact' (inline). */
function tsa_party_subscribe_form( int $pid, string $variant = 'block' ): string {
	$GLOBALS['tsa_party_subscribe_rendered'] = true;
	$compact = $variant === 'compact';
	$label   = $compact ? 'Email me each drop' : 'Notify me';
	$ph      = $compact ? 'you@email.com' : 'you@email.com';
	// Root-relative admin-ajax path so the fetch is always SAME-ORIGIN: it
	// inherits the page's host + scheme, avoiding www↔non-www / http↔https
	// mismatches behind LiteSpeed/CDN that otherwise make the POST cross-origin
	// and fail with a "network error".
	$ajax = wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ) ?: admin_url( 'admin-ajax.php' );
	ob_start(); ?>
	<form class="tsa-party-subscribe<?php echo $compact ? ' tsa-party-subscribe--compact' : ''; ?>"
	      method="post" action="<?php echo esc_url( $ajax ); ?>">
		<input type="hidden" name="action" value="tsa_party_subscribe">
		<input type="hidden" name="party_id" value="<?php echo (int) $pid; ?>">
		<input type="text" name="tsa_hp" class="tsa-party-subscribe__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
		<input type="email" name="email" required placeholder="<?php echo esc_attr( $ph ); ?>"
		       class="tsa-party-subscribe__email" aria-label="Email address">
		<button type="submit" class="tsa-btn tsa-btn-primary tsa-party-subscribe__btn"><?php echo esc_html( $label ); ?></button>
		<span class="tsa-party-subscribe__msg" role="status" aria-live="polite"></span>
	</form>
	<?php
	return ob_get_clean();
}

/** One-time inline CSS + AJAX handler, printed only when a form was rendered. */
add_action( 'wp_footer', 'tsa_party_subscribe_assets', 30 );
function tsa_party_subscribe_assets(): void {
	if ( empty( $GLOBALS['tsa_party_subscribe_rendered'] ) ) return;
	?>
	<style>
	.tsa-party-subscribe{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center;max-width:560px;margin:6px auto 0}
	.tsa-party-subscribe--compact{margin:14px auto 0}
	.tsa-party-subscribe__hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;opacity:0}
	.tsa-party-subscribe__email{flex:1 1 280px;min-width:240px;padding:13px 20px!important;border:1px solid rgba(0,0,0,.18)!important;border-radius:999px!important;font-size:15px!important;line-height:1.3!important;height:auto!important;background:#fff!important;color:#252124!important;box-shadow:none!important;margin:0!important}
	.tsa-party-subscribe__btn{flex:0 0 auto;border-radius:999px!important}
	.tsa-party-subscribe__msg{flex:1 1 100%;text-align:center;font-size:13px;color:var(--tp-text,#252124);opacity:.85;min-height:1em}
	.tsa-party-subscribe--done .tsa-party-subscribe__email,.tsa-party-subscribe--done .tsa-party-subscribe__btn{opacity:.5;pointer-events:none}
	</style>
	<script>
	document.addEventListener('submit',function(e){
		var f=e.target.closest('.tsa-party-subscribe'); if(!f) return;
		e.preventDefault();
		var msg=f.querySelector('.tsa-party-subscribe__msg'),btn=f.querySelector('button');
		btn.disabled=true; msg.textContent='Adding you…';
		fetch(f.action,{method:'POST',body:new FormData(f),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
			.then(function(r){ return r.text().then(function(t){
				try { return JSON.parse(t); }
				catch(err){ throw new Error('HTTP '+r.status+' — ' + (t ? t.slice(0,160) : 'empty response')); }
			}); })
			.then(function(j){
				btn.disabled=false;
				msg.textContent=(j&&j.data&&j.data.message)||(j&&j.success?'You’re on the list.':'Couldn’t subscribe — please try again.');
				if(j&&j.success){f.classList.add('tsa-party-subscribe--done');}
			})
			.catch(function(err){
				btn.disabled=false;
				msg.textContent='Couldn’t subscribe — please try again.';
				if(window.console){console.error('[tsa-party-subscribe]', f.action, err);}
			});
	});
	</script>
	<?php
}

/* ─── Subscribe handler (AJAX, public) ─────────────────────────────── */

add_action( 'wp_ajax_tsa_party_subscribe',        'tsa_party_subscribe_handler' );
add_action( 'wp_ajax_nopriv_tsa_party_subscribe', 'tsa_party_subscribe_handler' );
function tsa_party_subscribe_handler(): void {
	// Honeypot: a filled hidden field = bot. Pretend success, store nothing.
	if ( ! empty( $_POST['tsa_hp'] ) ) {
		wp_send_json_success( [ 'message' => "You're on the list — watch your inbox." ] );
	}
	// Per-IP rate limit: 10 submissions / hour.
	$ip  = preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'tsa_party_sub_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 10 ) {
		wp_send_json_error( [ 'message' => 'Too many attempts — please try again later.' ] );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$pid   = absint( $_POST['party_id'] ?? 0 );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! tsa_party_is_party( $pid ) ) {
		wp_send_json_error( [ 'message' => 'That showcase could not be found.' ] );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
	}
	$email = strtolower( $email );
	if ( ! in_array( $email, tsa_party_subscribers( $pid ), true ) ) {
		add_post_meta( $pid, TSA_PARTY_SUB_META, $email );
	}
	wp_send_json_success( [ 'message' => "You're on the list — watch your inbox." ] );
}

/* ─── Unsubscribe (one-click, HMAC-verified) ───────────────────────── */

add_action( 'init', 'tsa_party_handle_unsub' );
function tsa_party_handle_unsub(): void {
	if ( empty( $_GET['tsa_unsub'] ) ) return;
	$token = sanitize_text_field( wp_unslash( $_GET['tsa_unsub'] ) );
	$email = sanitize_email( wp_unslash( $_GET['e'] ?? '' ) );
	$pid   = absint( $_GET['p'] ?? 0 );

	if ( $pid && is_email( $email ) && hash_equals( tsa_party_unsub_token( $email, $pid ), $token ) ) {
		delete_post_meta( $pid, TSA_PARTY_SUB_META, strtolower( $email ) );
		wp_die(
			'<p style="font:16px system-ui;margin:0 0 14px">You\'ve been unsubscribed — you won\'t get any more drop alerts for this showcase.</p>'
			. '<p style="font:14px system-ui"><a href="' . esc_url( home_url( '/' ) ) . '">← Back to Tee Shirt Ali</a></p>',
			'Unsubscribed',
			[ 'response' => 200 ]
		);
	}
	wp_die(
		'<p style="font:16px system-ui">That unsubscribe link is invalid or expired.</p>',
		'Unsubscribe',
		[ 'response' => 200 ]
	);
}

/* ─── Email rendering + sending ────────────────────────────────────── */

/** Branded HTML email body. $designs (optional) = [ ['title'=>, 'img'=>], … ]. */
function tsa_party_email_html( int $pid, string $heading, string $intro, string $cta, string $url, string $to, array $designs = [] ): string {
	$accent = get_post_meta( $pid, '_tsa_party_color_accent', true ) ?: '#d8a85f';
	$text   = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $accent ) : '#ffffff';
	$unsub  = add_query_arg( [
		'tsa_unsub' => tsa_party_unsub_token( $to, $pid ),
		'e'         => rawurlencode( $to ),
		'p'         => $pid,
	], home_url( '/' ) );

	$tiles = '';
	foreach ( array_slice( $designs, 0, 6 ) as $d ) {
		$img = $d['img'] ? '<img src="' . esc_url( $d['img'] ) . '" alt="" width="150" style="width:150px;height:150px;object-fit:contain;background:#faf8f9;border:1px solid #eee;border-radius:10px;display:block">' : '';
		$tiles .= '<td style="padding:6px;text-align:center;vertical-align:top">' . $img
			. '<div style="font:600 12px system-ui;color:#444;margin-top:6px;max-width:150px">' . esc_html( $d['title'] ) . '</div></td>';
	}
	$grid = $tiles ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px auto 0"><tr>' . $tiles . '</tr></table>' : '';

	return '<div style="background:#f4f1f2;padding:28px 12px;font-family:system-ui,Arial,sans-serif">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #ececec">'
		. '<tr><td style="background:' . esc_attr( $accent ) . ';height:6px"></td></tr>'
		. '<tr><td style="padding:30px 30px 8px">'
		. '<h1 style="font:800 23px system-ui;color:#252124;margin:0 0 12px">' . esc_html( $heading ) . '</h1>'
		. '<p style="font:16px/1.55 system-ui;color:#4a4248;margin:0">' . esc_html( $intro ) . '</p>'
		. $grid
		. '<div style="text-align:center;margin:26px 0 6px"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:' . esc_attr( $accent ) . ';color:' . esc_attr( $text ) . ';font:800 15px system-ui;text-decoration:none;padding:13px 26px;border-radius:10px">' . esc_html( $cta ) . ' →</a></div>'
		. '</td></tr>'
		. '<tr><td style="padding:16px 30px 26px;border-top:1px solid #f0eef0;font:12px/1.5 system-ui;color:#9a9298;text-align:center">'
		. 'You\'re getting this because you asked to be notified about this Tee Party.<br>'
		. '<a href="' . esc_url( $unsub ) . '" style="color:#9a9298">Unsubscribe from this showcase</a>'
		. '</td></tr></table></div>';
}

function tsa_party_mail( string $to, string $subject, string $html ): void {
	wp_mail( $to, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Send the "party is live" email once. */
function tsa_party_send_open_email( int $pid ): void {
	if ( get_post_meta( $pid, '_tsa_party_active', true ) !== '1' ) return;
	if ( get_post_meta( $pid, '_tsa_party_notified_open', true ) === '1' ) return;
	if ( ! tsa_party_alerts_lock( $pid ) ) return;
	// Mark first so an overlapping run can't double-send; email is best-effort.
	update_post_meta( $pid, '_tsa_party_notified_open', '1' );

	$subs = tsa_party_subscribers( $pid );
	if ( $subs ) {
		$title = get_the_title( $pid );
		$url   = get_permalink( $pid );
		foreach ( $subs as $email ) {
			// Render per-recipient so each unsubscribe link carries its own token.
			$html = tsa_party_email_html(
				$pid,
				$title . ' is live!',
				'The showcase just opened — new designs drop throughout. Be first to grab yours before the deadline.',
				'Enter the Showcase', $url, $email, []
			);
			tsa_party_mail( $email, $title . ' is live — new designs are dropping', $html );
		}
	}
	tsa_party_alerts_unlock( $pid );
}

/** Send a drop email for any revealed batch not yet notified. */
function tsa_party_send_drop_emails( int $pid ): void {
	if ( get_post_meta( $pid, '_tsa_party_active', true ) !== '1' ) return;
	if ( ! function_exists( 'tsa_party_status' ) ) return;
	$st = tsa_party_status( $pid );
	if ( ! in_array( $st['status'], [ 'live', 'grace' ], true ) ) return;
	if ( ! tsa_party_alerts_lock( $pid ) ) return;

	$schedule = json_decode( get_post_meta( $pid, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [];
	$notified = array_map( 'intval', (array) ( json_decode( get_post_meta( $pid, '_tsa_party_notified_drops', true ) ?: '[]', true ) ?: [] ) );
	$now      = time();

	// A "drop" = the designs sharing one reveal timestamp.
	$batches = [];
	foreach ( $schedule as $row ) {
		$did = absint( $row['design_id'] ?? 0 ); if ( ! $did ) continue;
		$ts  = function_exists( 'tsa_party_ts' ) ? tsa_party_ts( $row['reveal_at'] ?? '' ) : 0;
		if ( ! $ts || $now < $ts ) continue;
		$batches[ $ts ][] = $did;
	}
	ksort( $batches );

	$subs  = tsa_party_subscribers( $pid );
	$title = get_the_title( $pid );
	$url   = get_permalink( $pid );

	foreach ( $batches as $ts => $design_ids ) {
		if ( in_array( $ts, $notified, true ) ) continue;
		$notified[] = $ts;
		// Mark before sending so re-entry / lazy fallback can't double-send.
		update_post_meta( $pid, '_tsa_party_notified_drops', wp_json_encode( array_values( array_unique( $notified ) ) ) );
		if ( ! $subs ) continue;

		$designs = [];
		foreach ( $design_ids as $did ) {
			$designs[] = [
				'title' => get_the_title( $did ),
				'img'   => get_post_meta( $did, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $did, 'medium' ) ?: '' ),
			];
		}
		$n    = count( $design_ids );
		$head = sprintf( '%d new design%s just dropped', $n, $n === 1 ? '' : 's' );
		foreach ( $subs as $email ) {
			$html = tsa_party_email_html(
				$pid, $head,
				sprintf( 'Fresh designs are live in %s right now — configure one on any apparel before the next drop.', $title ),
				'Shop the Drop', $url, $email, $designs
			);
			tsa_party_mail( $email, sprintf( '%s new design%s just dropped in %s', $n, $n === 1 ? '' : 's', $title ), $html );
		}
	}
	tsa_party_alerts_unlock( $pid );
}

/* ─── Triggers ─────────────────────────────────────────────────────── */

// Each scheduled drop reveal (event already created by tsa_save_party_meta).
add_action( 'tsa_party_drop_event', function ( $pid ) { tsa_party_send_drop_emails( (int) $pid ); } );

// Party open: schedule a one-time event at start; runs at priority 30 so it
// reads the meta tsa_save_party_meta (priority 10) just saved.
add_action( 'tsa_party_open_event', function ( $pid ) { tsa_party_send_open_email( (int) $pid ); } );
add_action( 'save_post_page', 'tsa_party_schedule_alerts', 30 );
function tsa_party_schedule_alerts( int $post_id ): void {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
	if ( ! tsa_party_is_party( $post_id ) ) return;

	wp_clear_scheduled_hook( 'tsa_party_open_event', [ $post_id ] );
	if ( get_post_meta( $post_id, '_tsa_party_active', true ) !== '1' ) return;

	$start = function_exists( 'tsa_party_ts' ) ? tsa_party_ts( get_post_meta( $post_id, '_tsa_party_start_date', true ) ) : 0;
	if ( $start && $start > time() ) {
		wp_schedule_single_event( $start, 'tsa_party_open_event', [ $post_id ] );
	} elseif ( $start ) {
		tsa_party_send_open_email( $post_id );      // already open
	}
	tsa_party_send_drop_emails( $post_id );          // catch "Reveal Now" / past drops
}
