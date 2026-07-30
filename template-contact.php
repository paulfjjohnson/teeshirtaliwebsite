<?php
/**
 * Template Name: TSA Contact
 *
 * Contact page with methods + a working contact form (posts to admin-post.php,
 * handled by tsa_handle_contact_form() in functions.php).
 * Assign to: /contact/
 */

defined( 'ABSPATH' ) || exit;

get_header();

$status = isset( $_GET['contact'] ) ? sanitize_key( $_GET['contact'] ) : '';

// Shown email = the Business Profile public email, else the routed "Contact form"
// recipient (Settings → TSA Form Emails), so the address matches where the form
// actually goes. tsa_biz('email') already applies that fallback chain.
$contact_email = function_exists( 'tsa_biz' )
	? tsa_biz( 'email' )
	: ( function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'contact' ) : get_option( 'admin_email' ) );
if ( is_array( $contact_email ) ) $contact_email = reset( $contact_email );

// Business identity (from Business Profile; falls back to the TSA reference values).
$biz_name     = function_exists( 'tsa_biz' ) ? tsa_biz( 'name' ) : 'Tee Shirt Ali';
$biz_location = function_exists( 'tsa_biz' ) ? tsa_biz( 'location', 'Baton Rouge, Louisiana' ) : 'Baton Rouge, Louisiana';
$biz_area     = function_exists( 'tsa_biz' ) ? tsa_biz( 'service_area', 'Serving Ascension Parish &amp; nationwide' ) : 'Serving Ascension Parish &amp; nationwide';
$biz_phone    = function_exists( 'tsa_biz' ) ? tsa_biz( 'phone' ) : '';
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Get in Touch</div>
        <h1>Contact <?php echo esc_html( $biz_name ); ?></h1>
        <p class="tsa-ip-hero__sub">Questions, custom projects, store setups, or just an idea — we'd love to hear from you. We typically reply within one business day.</p>
    </div>
</section>

<div class="tsa-ip-wrap">
    <div class="tsa-ip-contact">

        <!-- Methods -->
        <?php $svg = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'; ?>
        <div class="tsa-ip-methods">
            <div class="tsa-ip-method">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg></span>
                <div>
                    <h3>Email</h3>
                    <p><a href="mailto:<?php echo esc_attr( antispambot( $contact_email ) ); ?>"><?php echo esc_html( antispambot( $contact_email ) ); ?></a></p>
                </div>
            </div>
            <div class="tsa-ip-method">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <div>
                    <h3>Location</h3>
                    <p><?php echo esc_html( $biz_location ); ?><?php if ( $biz_area ) : ?><br><?php echo wp_kses_post( $biz_area ); ?><?php endif; ?></p>
                </div>
            </div>
            <?php if ( $biz_phone ) : ?>
            <div class="tsa-ip-method">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                <div>
                    <h3>Phone</h3>
                    <p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $biz_phone ) ); ?>"><?php echo esc_html( $biz_phone ); ?></a></p>
                </div>
            </div>
            <?php endif; ?>
            <div class="tsa-ip-method">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg></span>
                <div>
                    <h3>Fastest Path to a Price</h3>
                    <p><a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>">Request a Quote</a> or build it live in the <a href="<?php echo esc_url( home_url( '/configurator/' ) ); ?>">configurator</a>.</p>
                </div>
            </div>
            <div class="tsa-ip-method">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg></span>
                <div>
                    <h3>Common Questions</h3>
                    <p>Many answers live on our <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ page</a>.</p>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="tsa-ip-formcard">
            <?php if ( $status === 'sent' ) : ?>
                <div class="tsa-ip-note" style="border-left-color:var(--status-success);margin:0 0 20px">
                    <strong>Thanks — your message is on its way!</strong> We'll get back to you within one business day.
                </div>
            <?php elseif ( $status === 'error' ) : ?>
                <div class="tsa-ip-note" style="border-left-color:var(--status-error);margin:0 0 20px">
                    <strong>Something went wrong.</strong> Please check the fields and try again, or email us directly.
                </div>
            <?php endif; ?>

            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <input type="hidden" name="action" value="tsa_contact">
                <?php wp_nonce_field( 'tsa_contact', 'tsa_contact_nonce' ); ?>
                <!-- Honeypot -->
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label>Leave this empty <input type="text" name="tsa_hp" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="tsa_name">Name</label>
                        <input class="tsa-ip-input" type="text" id="tsa_name" name="tsa_name" required>
                    </div>
                    <div class="tsa-ip-field">
                        <label for="tsa_email">Email</label>
                        <input class="tsa-ip-input" type="email" id="tsa_email" name="tsa_email" required>
                    </div>
                </div>
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="tsa_phone">Phone <span style="opacity:.6;text-transform:none">(optional)</span></label>
                        <input class="tsa-ip-input" type="tel" id="tsa_phone" name="tsa_phone">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="tsa_topic">Topic</label>
                        <select class="tsa-ip-select" id="tsa_topic" name="tsa_topic">
                            <option>General Question</option>
                            <option>Custom Apparel / Quote</option>
                            <option>School or Team Store</option>
                            <option>Fundraiser</option>
                            <option>DTF Printing</option>
                            <option>Existing Order</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>
                <div class="tsa-ip-field">
                    <label for="tsa_message">Message</label>
                    <textarea class="tsa-ip-textarea" id="tsa_message" name="tsa_message" required></textarea>
                </div>
                <button type="submit" class="tsa-btn tsa-btn-primary tsa-btn-block">Send Message</button>
            </form>
        </div>

    </div>

    <!-- What happens next -->
    <div class="tsa-ip-section" style="margin-top:52px">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">After You Reach Out</div>
            <h2>What happens next</h2>
        </div>
        <div class="tsa-ip-cards">
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg></span>
                <h3>1 · We get your message</h3>
                <p>It lands straight in our inbox — usually read within the hour on business days.</p>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <h3>2 · We reply with a plan</h3>
                <p>Within one business day you'll hear back with options, pricing, and next steps.</p>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><svg <?php echo $svg; // phpcs:ignore ?>><path d="M20 6 9 17l-5-5"/></svg></span>
                <h3>3 · We make it happen</h3>
                <p>Approve a proof and we print, press, and get your gear moving — fast.</p>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
