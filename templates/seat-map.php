<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$halls    = misaha_get_all_halls();
$currency = get_option( 'misaha_currency', 'LYD' );

$prices = array(
    'day'   => (float) get_option( 'misaha_price_seat_day',   15 ),
    'week'  => (float) get_option( 'misaha_price_seat_week',  80 ),
    'month' => (float) get_option( 'misaha_price_seat_month', 250 ),
);
?>

<div class="misaha-wrap" id="misaha-seat-map-wrap">

    <!-- ── Step Indicator ───────────────────────────────────────────────── -->
    <div class="misaha-steps">
        <div class="misaha-step active" data-step="1">
            <span class="misaha-step-num">1</span>
            <span class="misaha-step-label">Choose Hall</span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="2">
            <span class="misaha-step-num">2</span>
            <span class="misaha-step-label">Select Seat</span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="3">
            <span class="misaha-step-num">3</span>
            <span class="misaha-step-label">Choose Pass</span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="4">
            <span class="misaha-step-num">4</span>
            <span class="misaha-step-label">Confirm &amp; Pay</span>
        </div>
    </div>

    <!-- ── Step 1: Hall Selection ─────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-sm-step-1">
        <h2 class="misaha-panel-title">🏢 Choose a Hall</h2>

        <?php if ( empty( $halls ) ) : ?>
            <div class="misaha-alert misaha-alert-warning">No halls available right now.</div>
        <?php else : ?>

        <div class="misaha-hall-grid">
            <?php foreach ( $halls as $hall ) : ?>
            <div class="misaha-hall-card">
                <div class="misaha-hall-icon">🏛️</div>
                <h3><?php echo esc_html($hall->name); ?></h3>
                <p class="misaha-hall-desc"><?php echo esc_html($hall->description); ?></p>
                <div class="misaha-hall-meta">
                    <span>👥 <?php echo esc_html($hall->capacity); ?> seats</span>
                </div>
                <button class="misaha-btn misaha-btn-outline misaha-sm-select-hall"
                        data-hall-id="<?php echo esc_attr($hall->id); ?>"
                        data-hall-name="<?php echo esc_attr($hall->name); ?>">
                    View Seats
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- ── Step 2: Seat Map ───────────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-sm-step-2" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-sm-back" data-to="1">← Back</button>
            <h2 class="misaha-panel-title">💺 Select Your Seat</h2>
        </div>
        <div class="misaha-hall-banner" id="misaha-hall-banner"></div>

        <div class="misaha-loading" id="misaha-sm-loading" style="display:none;">
            <div class="misaha-spinner"></div><p>Loading seat map…</p>
        </div>

        <!-- Stage visual -->
        <div class="misaha-stage-visual">
            <div class="misaha-stage-label">🎤 STAGE / FRONT</div>
        </div>

        <div class="misaha-seat-map-grid" id="misaha-seat-grid"></div>

        <div class="misaha-seat-legend">
            <span class="misaha-legend-item"><span class="misaha-seat-dot seat-available"></span> Available</span>
            <span class="misaha-legend-item"><span class="misaha-seat-dot seat-selected"></span> Your Selection</span>
            <span class="misaha-legend-item"><span class="misaha-seat-dot seat-occupied"></span> Occupied</span>
        </div>

        <button class="misaha-btn misaha-btn-primary" id="misaha-sm-to-pass" style="display:none;">
            Choose Pass Type →
        </button>
    </div>

    <!-- ── Step 3: Pass Type ──────────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-sm-step-3" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-sm-back" data-to="2">← Back</button>
            <h2 class="misaha-panel-title">🎟️ Choose Your Pass</h2>
        </div>
        <div class="misaha-selected-seat-info" id="misaha-selected-seat-info"></div>

        <div class="misaha-pass-cards">
            <div class="misaha-pass-card" data-pass="day">
                <div class="misaha-pass-icon">☀️</div>
                <h3>Day Pass</h3>
                <p>Full access for a single day</p>
                <div class="misaha-pass-price"><?php echo esc_html($currency . ' ' . number_format($prices['day'],2)); ?></div>
                <button class="misaha-btn misaha-btn-outline misaha-pick-pass" data-pass="day"
                        data-price="<?php echo esc_attr($prices['day']); ?>">Select</button>
            </div>
            <div class="misaha-pass-card misaha-pass-popular" data-pass="week">
                <div class="misaha-pass-badge">Most Popular</div>
                <div class="misaha-pass-icon">📅</div>
                <h3>Weekly Pass</h3>
                <p>7 days of unlimited seat access</p>
                <div class="misaha-pass-price"><?php echo esc_html($currency . ' ' . number_format($prices['week'],2)); ?></div>
                <button class="misaha-btn misaha-btn-primary misaha-pick-pass" data-pass="week"
                        data-price="<?php echo esc_attr($prices['week']); ?>">Select</button>
            </div>
            <div class="misaha-pass-card" data-pass="month">
                <div class="misaha-pass-icon">🗓️</div>
                <h3>Monthly Pass</h3>
                <p>30 days of unlimited seat access</p>
                <div class="misaha-pass-price"><?php echo esc_html($currency . ' ' . number_format($prices['month'],2)); ?></div>
                <button class="misaha-btn misaha-btn-outline misaha-pick-pass" data-pass="month"
                        data-price="<?php echo esc_attr($prices['month']); ?>">Select</button>
            </div>
        </div>

        <div class="misaha-start-date-row" id="misaha-start-date-row" style="display:none;">
            <label for="misaha-pass-start">📅 Start Date</label>
            <input type="date" id="misaha-pass-start"
                   min="<?php echo esc_attr(date('Y-m-d')); ?>">
        </div>
    </div>

    <!-- ── Step 4: Confirm & Pay ──────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-sm-step-4" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-sm-back" data-to="3">← Back</button>
            <h2 class="misaha-panel-title">✅ Confirm &amp; Pay</h2>
        </div>

        <div class="misaha-summary-card" id="misaha-pass-summary"></div>

        <div class="misaha-discount-row">
            <input type="text" id="misaha-sm-discount-code" placeholder="Discount code (optional)" style="text-transform:uppercase">
            <button class="misaha-btn misaha-btn-outline" id="misaha-sm-apply-discount">Apply</button>
        </div>
        <div class="misaha-discount-msg" id="misaha-sm-discount-msg"></div>

        <div class="misaha-price-breakdown" id="misaha-pass-price-breakdown"></div>

        <button class="misaha-btn misaha-btn-primary misaha-btn-lg" id="misaha-sm-pay-now">
            💳 Proceed to Payment
        </button>
        <div class="misaha-booking-result" id="misaha-pass-result" style="display:none;"></div>
    </div>

</div><!-- .misaha-wrap -->
