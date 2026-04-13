<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$halls    = misaha_get_all_halls();
$currency = get_option( 'misaha_currency', 'LYD' );
?>

<div class="misaha-wrap" id="misaha-booking-calendar-wrap">

    <!-- ── Step Indicator ───────────────────────────────────────────────── -->
    <div class="misaha-steps">
        <div class="misaha-step active" data-step="1">
            <span class="misaha-step-num">1</span>
            <span class="misaha-step-label">Choose Hall</span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="2">
            <span class="misaha-step-num">2</span>
            <span class="misaha-step-label">Pick Slot</span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="3">
            <span class="misaha-step-num">3</span>
            <span class="misaha-step-label">Confirm & Pay</span>
        </div>
    </div>

    <!-- ── Step 1: Hall & Date ────────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-step-1">
        <h2 class="misaha-panel-title">🏢 Select Hall &amp; Date</h2>

        <?php if ( empty( $halls ) ) : ?>
            <div class="misaha-alert misaha-alert-warning">No halls available at the moment. Please check back soon.</div>
        <?php else : ?>

        <div class="misaha-hall-grid">
            <?php foreach ( $halls as $hall ) : ?>
            <div class="misaha-hall-card" data-hall-id="<?php echo esc_attr($hall->id); ?>">
                <div class="misaha-hall-icon">🏛️</div>
                <h3><?php echo esc_html($hall->name); ?></h3>
                <p class="misaha-hall-desc"><?php echo esc_html($hall->description); ?></p>
                <div class="misaha-hall-meta">
                    <span>👥 <?php echo esc_html($hall->capacity); ?> pax</span>
                    <span>💰 <?php echo esc_html($currency . ' ' . number_format($hall->price_per_hour,2)); ?>/hr</span>
                </div>
                <button class="misaha-btn misaha-btn-outline misaha-select-hall"
                        data-hall-id="<?php echo esc_attr($hall->id); ?>"
                        data-hall-name="<?php echo esc_attr($hall->name); ?>"
                        data-hall-price="<?php echo esc_attr($hall->price_per_hour); ?>">
                    Select Hall
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="misaha-date-picker" id="misaha-date-picker" style="display:none;">
            <label for="misaha-booking-date">📅 Select Date</label>
            <input type="date" id="misaha-booking-date"
                   min="<?php echo esc_attr( date('Y-m-d') ); ?>"
                   max="<?php echo esc_attr( date('Y-m-d', strtotime('+3 months')) ); ?>">
            <button class="misaha-btn misaha-btn-primary" id="misaha-load-slots">View Available Slots →</button>
        </div>

        <?php endif; ?>
    </div>

    <!-- ── Step 2: Time Slots ─────────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-step-2" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-back" data-to="1">← Back</button>
            <h2 class="misaha-panel-title">⏰ Available Slots</h2>
        </div>

        <div class="misaha-selected-info" id="misaha-selected-info"></div>
        <div class="misaha-loading" id="misaha-slots-loading" style="display:none;">
            <div class="misaha-spinner"></div>
            <p>Loading slots…</p>
        </div>
        <div class="misaha-slots-grid" id="misaha-slots-grid"></div>

        <div class="misaha-legend">
            <span class="misaha-legend-item"><span class="misaha-slot-dot available"></span> Available</span>
            <span class="misaha-legend-item"><span class="misaha-slot-dot selected"></span> Selected</span>
            <span class="misaha-legend-item"><span class="misaha-slot-dot booked"></span> Booked</span>
        </div>

        <button class="misaha-btn misaha-btn-primary" id="misaha-proceed-confirm" style="display:none;">Continue →</button>
    </div>

    <!-- ── Step 3: Confirm & Pay ──────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-step-3" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-back" data-to="2">← Back</button>
            <h2 class="misaha-panel-title">✅ Confirm Booking</h2>
        </div>

        <div class="misaha-summary-card" id="misaha-booking-summary"></div>

        <div class="misaha-discount-row">
            <input type="text" id="misaha-discount-code" placeholder="Discount code (optional)" style="text-transform:uppercase">
            <button class="misaha-btn misaha-btn-outline" id="misaha-apply-discount">Apply</button>
        </div>
        <div class="misaha-discount-msg" id="misaha-discount-msg"></div>

        <div class="misaha-price-breakdown" id="misaha-price-breakdown"></div>

        <button class="misaha-btn misaha-btn-primary misaha-btn-lg" id="misaha-pay-now">
            💳 Proceed to Payment
        </button>
        <div class="misaha-booking-result" id="misaha-booking-result" style="display:none;"></div>
    </div>

</div><!-- .misaha-wrap -->