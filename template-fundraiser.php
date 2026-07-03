<?php
/**
 * Template Name: TSA Fundraiser
 *
 * Full fundraiser landing page — how it works, earnings showcase, FAQ.
 * Assign to: /fundraisers/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-fr-hero">
    <div class="tsa-fr-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-fr-hero__inner">
        <div class="tsa-kicker tsa-kicker--light">Zero Risk Fundraising</div>
        <h1 class="tsa-fr-hero__title">Launch a Fundraiser<br>That Actually Works</h1>
        <p class="tsa-fr-hero__sub">Online ordering, no upfront costs, no inventory. We handle printing and fulfillment — your group keeps every dollar of profit.</p>
        <div class="tsa-fr-hero__actions">
            <a href="<?php echo esc_url( home_url( '/start-a-fundraiser/' ) ); ?>" class="tsa-btn tsa-btn-primary">Start Your Fundraiser</a>
            <a href="#how-it-works" class="tsa-btn tsa-svh-btn-ghost">How It Works ↓</a>
        </div>
        <div class="tsa-fr-hero__stats">
            <div class="tsa-fr-stat"><span class="tsa-fr-stat__num">$0</span><span class="tsa-fr-stat__label">Upfront Cost</span></div>
            <div class="tsa-fr-stat-div"></div>
            <div class="tsa-fr-stat"><span class="tsa-fr-stat__num">100%</span><span class="tsa-fr-stat__label">Online Ordering</span></div>
            <div class="tsa-fr-stat-div"></div>
            <div class="tsa-fr-stat"><span class="tsa-fr-stat__num">Fast</span><span class="tsa-fr-stat__label">Payout After Close</span></div>
            <div class="tsa-fr-stat-div"></div>
            <div class="tsa-fr-stat"><span class="tsa-fr-stat__num tsa-fr-stat__num--pink">Zero</span><span class="tsa-fr-stat__label">Risk to Your Group</span></div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════ -->
<section id="how-it-works" class="tsa-fr-section tsa-fr-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-fr-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Simple Process</div>
            <h2>How Fundraising Works</h2>
            <p>From kickoff to payout — we do the heavy lifting.</p>
        </div>
        <div class="tsa-fr-steps">
            <?php
            $steps = [
                [ 'num' => '01', 'icon' => '🎨', 'title' => 'Pick Your Products',     'desc' => 'We work with your group to select apparel styles and create a custom design that your supporters will love.' ],
                [ 'num' => '02', 'icon' => '🏪', 'title' => 'We Build Your Store',    'desc' => 'We launch a branded online store for your fundraiser — supporters can browse, order, and pay online in minutes.' ],
                [ 'num' => '03', 'icon' => '📣', 'title' => 'Promote & Sell',         'desc' => 'Share your store link on social media, in email blasts, and group chats. No door-to-door, no cash collection.' ],
                [ 'num' => '04', 'icon' => '💰', 'title' => 'We Ship, You Get Paid',  'desc' => 'When the campaign closes, we fulfill every order and pay out your group\'s profit — typically within 7 days.' ],
            ];
            foreach ( $steps as $s ) :
            ?>
            <div class="tsa-fr-step">
                <div class="tsa-fr-step__icon"><?php echo esc_html( $s['icon'] ); ?></div>
                <div class="tsa-fr-step__num"><?php echo esc_html( $s['num'] ); ?></div>
                <h4 class="tsa-fr-step__title"><?php echo esc_html( $s['title'] ); ?></h4>
                <p class="tsa-fr-step__desc"><?php echo esc_html( $s['desc'] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     EARNINGS SHOWCASE
══════════════════════════════════════ -->
<section class="tsa-fr-section tsa-fr-section--dark">
    <div class="tsa-sd-container">
        <div class="tsa-fr-section-head tsa-fr-section-head--light">
            <div class="tsa-kicker" style="background:rgba(254,194,192,.15);color:var(--tsa-pink);border:1px solid rgba(254,194,192,.3)">Real Numbers</div>
            <h2 style="color:#fff">How Much Can Your Group Earn?</h2>
            <p style="color:rgba(255,255,255,.6)">Here's what a typical fundraiser looks like at different participation levels.</p>
        </div>
        <div class="tsa-fr-earn-grid">
            <?php
            $tiers = [
                [ 'label' => 'Small Group',   'orders' => 25,  'item_price' => 30, 'profit_per' => 8,  'color' => 'var(--tsa-pink)' ],
                [ 'label' => 'Medium Group',  'orders' => 75,  'item_price' => 30, 'profit_per' => 8,  'color' => 'var(--tsa-gold)' ],
                [ 'label' => 'Large Group',   'orders' => 150, 'item_price' => 30, 'profit_per' => 10, 'color' => '#4ade80' ],
            ];
            foreach ( $tiers as $tier ) :
                $total_sales  = $tier['orders'] * $tier['item_price'];
                $total_profit = $tier['orders'] * $tier['profit_per'];
            ?>
            <div class="tsa-fr-earn-card">
                <div class="tsa-fr-earn-card__label"><?php echo esc_html( $tier['label'] ); ?></div>
                <div class="tsa-fr-earn-card__orders"><?php echo $tier['orders']; ?> orders</div>
                <div class="tsa-fr-earn-card__profit" style="color:<?php echo esc_attr( $tier['color'] ); ?>">
                    $<?php echo number_format( $total_profit ); ?>
                </div>
                <div class="tsa-fr-earn-card__sub">your group earns</div>
                <ul class="tsa-fr-earn-card__detail">
                    <li>Retail price: $<?php echo $tier['item_price']; ?>/item</li>
                    <li>Profit: $<?php echo $tier['profit_per']; ?>/item</li>
                    <li>Total sales: $<?php echo number_format( $total_sales ); ?></li>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="tsa-fr-earn-note">* Example figures using a standard t-shirt at $30 retail. Actual profit per item depends on product type, quantity, and design complexity.</p>
    </div>
</section>

<!-- ══════════════════════════════════════
     WHY TSA FUNDRAISERS
══════════════════════════════════════ -->
<section class="tsa-fr-section tsa-fr-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-fr-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Why Us</div>
            <h2>Why Groups Choose Tee Shirt Ali</h2>
            <p>We make fundraising easy, profitable, and professional — for any size group.</p>
        </div>
        <div class="tsa-fr-why-grid">
            <?php
            $features = [
                [ 'icon' => '💸', 'title' => 'No Upfront Cost',   'desc' => 'We print after orders come in — your group pays nothing upfront and risks absolutely nothing.' ],
                [ 'icon' => '🖥️', 'title' => 'Online Ordering',   'desc' => 'Supporters order with a credit card from any device — no cash collection or order form tracking.' ],
                [ 'icon' => '🎨', 'title' => 'Custom Designs',     'desc' => 'Your group gets a custom-designed item with your logo, mascot, or theme included at no extra charge.' ],
                [ 'icon' => '📊', 'title' => 'Real-Time Tracking', 'desc' => 'Monitor orders and revenue in real time through your group\'s dedicated campaign dashboard.' ],
                [ 'icon' => '⚡', 'title' => 'Fast Turnaround',    'desc' => 'Campaigns run 2–4 weeks. All orders are fulfilled and shipped within 7–10 days of close.' ],
                [ 'icon' => '🤝', 'title' => 'White-Glove Setup',  'desc' => 'We handle design, store setup, printing, shipping, and customer service. You just promote and collect.' ],
            ];
            foreach ( $features as $f ) :
            ?>
            <div class="tsa-fr-feature">
                <span class="tsa-fr-feature__icon"><?php echo esc_html( $f['icon'] ); ?></span>
                <div>
                    <strong class="tsa-fr-feature__title"><?php echo esc_html( $f['title'] ); ?></strong>
                    <p class="tsa-fr-feature__desc"><?php echo esc_html( $f['desc'] ); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     WHO WE SERVE
══════════════════════════════════════ -->
<section class="tsa-fr-section tsa-fr-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-fr-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Who It's For</div>
            <h2>Perfect for Any Group</h2>
        </div>
        <div class="tsa-fr-who-grid">
            <?php
            $who = [
                [ 'icon' => '🏫', 'title' => 'Schools & Clubs',    'desc' => 'Color guard, band, sports teams, student councils, booster clubs, and spirit campaigns.' ],
                [ 'icon' => '⛪', 'title' => 'Churches & Missions', 'desc' => 'Mission trips, youth groups, church campaigns, and charity drives.' ],
                [ 'icon' => '⚾', 'title' => 'Sports Teams',        'desc' => 'Travel teams, recreation leagues, competitive programs, and parent booster groups.' ],
                [ 'icon' => '🤲', 'title' => 'Community Orgs',     'desc' => 'Nonprofits, neighborhood associations, civic groups, and service organizations.' ],
                [ 'icon' => '🎉', 'title' => 'Events & Causes',    'desc' => 'Charity walks, awareness campaigns, memorial events, and one-time collections.' ],
                [ 'icon' => '🏢', 'title' => 'Businesses',          'desc' => 'Employee appreciation drives, corporate charity campaigns, and branded team launches.' ],
            ];
            foreach ( $who as $w ) :
            ?>
            <div class="tsa-fr-who-card">
                <span class="tsa-fr-who-card__icon"><?php echo esc_html( $w['icon'] ); ?></span>
                <h4 class="tsa-fr-who-card__title"><?php echo esc_html( $w['title'] ); ?></h4>
                <p class="tsa-fr-who-card__desc"><?php echo esc_html( $w['desc'] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     FAQ
══════════════════════════════════════ -->
<section class="tsa-fr-section tsa-fr-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-fr-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Questions</div>
            <h2>Fundraiser FAQ</h2>
        </div>
        <div class="tsa-qr-faq" id="tsa-fr-faq-list" style="max-width:760px;margin:0 auto;">
            <?php
            $faqs = [
                [ 'q' => 'How long does a fundraiser campaign run?',
                  'a' => 'Most campaigns run 2–4 weeks. We recommend 3 weeks for the best balance of urgency and reach. You can start and stop the campaign at any time.' ],
                [ 'q' => 'When does my group get paid?',
                  'a' => 'We process profit payouts within 7 business days after the campaign closes and all orders have been fulfilled. Payment is made by check or ACH depending on your preference.' ],
                [ 'q' => 'Is there a minimum number of orders to run a campaign?',
                  'a' => 'We recommend a minimum of 10 orders for the campaign to be worth running, but there is no hard minimum. We will advise you if the expected volume is too low to be profitable.' ],
                [ 'q' => 'Can we sell multiple products in one campaign?',
                  'a' => 'Yes. A campaign can include multiple products — for example, a t-shirt, a hoodie, and a hat. Each item can have its own profit margin set individually.' ],
                [ 'q' => 'Does my group have to handle any inventory?',
                  'a' => 'No. Every order is printed to demand and shipped directly to the buyer. You never touch inventory, collect payments, or manage fulfillment.' ],
                [ 'q' => 'Can we use an existing design or do you create one for us?',
                  'a' => 'Both. If you have an existing logo or artwork, we\'ll use it. If you need a design created from scratch, our design team can create one — just mention it when you request your store.' ],
            ];
            foreach ( $faqs as $i => $faq ) :
            ?>
            <div class="tsa-qr-faq-item" id="tsa-fr-faq-<?php echo $i; ?>">
                <button class="tsa-qr-faq-q" aria-expanded="false" aria-controls="tsa-fr-faq-a-<?php echo $i; ?>">
                    <span><?php echo esc_html( $faq['q'] ); ?></span>
                    <span class="tsa-qr-faq-chevron" aria-hidden="true"></span>
                </button>
                <div class="tsa-qr-faq-a" id="tsa-fr-faq-a-<?php echo $i; ?>" role="region" hidden>
                    <p><?php echo esc_html( $faq['a'] ); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     CTA
══════════════════════════════════════ -->
<section class="tsa-svh-cta">
    <div class="tsa-sd-container">
        <div class="tsa-svh-cta__inner">
            <div class="tsa-svh-cta__copy">
                <h2>Ready to launch your fundraiser?</h2>
                <p>Tell us about your group and campaign goal — we'll put together a custom fundraiser plan at no cost or commitment.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/start-a-fundraiser/' ) ); ?>" class="tsa-btn tsa-btn-primary">Start a Fundraiser →</a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-outline">Ask a Question</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>

<script>
document.querySelectorAll('#tsa-fr-faq-list .tsa-qr-faq-q').forEach(function(btn){
    btn.addEventListener('click',function(){
        var open=btn.getAttribute('aria-expanded')==='true';
        document.querySelectorAll('#tsa-fr-faq-list .tsa-qr-faq-q').forEach(function(b){
            b.setAttribute('aria-expanded','false');
            b.closest('.tsa-qr-faq-item').classList.remove('is-open');
            var a=document.getElementById(b.getAttribute('aria-controls'));
            if(a) a.hidden=true;
        });
        if(!open){btn.setAttribute('aria-expanded','true');btn.closest('.tsa-qr-faq-item').classList.add('is-open');var ans=document.getElementById(btn.getAttribute('aria-controls'));if(ans) ans.hidden=false;}
    });
});
</script>
