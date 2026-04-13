<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$halls    = misaha_get_all_halls();
$currency = get_option( 'misaha_currency', 'LYD' );
$is_rtl   = misaha_is_rtl();
$dir      = $is_rtl ? 'rtl' : 'ltr';
$arrow_r  = $is_rtl ? '←' : '→';
$arrow_l  = $is_rtl ? '→' : '←';
?>

<div class="misaha-wrap" id="misaha-booking-calendar-wrap" dir="<?php echo $dir; ?>">

    <!-- ── Step Indicator ───────────────────────────────────────────────── -->
    <div class="misaha-steps">
        <div class="misaha-step active" data-step="1">
            <span class="misaha-step-num">1</span>
            <span class="misaha-step-label"><?php misaha_e('Choose Hall'); ?></span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="2">
            <span class="misaha-step-num">2</span>
            <span class="misaha-step-label"><?php misaha_e('Pick Slot'); ?></span>
        </div>
        <div class="misaha-step-line"></div>
        <div class="misaha-step" data-step="3">
            <span class="misaha-step-num">3</span>
            <span class="misaha-step-label"><?php misaha_e('Confirm & Pay'); ?></span>
        </div>
    </div>

    <!-- ── Step 1: Hall & Date ────────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-step-1">
        <h2 class="misaha-panel-title">🏢 <?php misaha_e('Select Hall & Date'); ?></h2>

        <?php if ( empty( $halls ) ) : ?>
            <div class="misaha-alert misaha-alert-warning"><?php misaha_e('No halls available at the moment. Please check back soon.'); ?></div>
        <?php else : ?>

        <div class="misaha-hall-grid">
            <?php foreach ( $halls as $hall ) : ?>
            <div class="misaha-hall-card" data-hall-id="<?php echo esc_attr($hall->id); ?>">
                <div class="misaha-hall-icon">🏛️</div>
                <h3><?php echo esc_html($hall->name); ?></h3>
                <p class="misaha-hall-desc"><?php echo esc_html($hall->description); ?></p>
                <div class="misaha-hall-meta">
                    <span>👥 <?php echo esc_html($hall->capacity); ?> <?php misaha_e('pax'); ?></span>
                    <span>💰 <?php echo esc_html($currency . ' ' . number_format($hall->price_per_hour,2)); ?><?php misaha_e('/hr'); ?></span>
                </div>
                <button class="misaha-btn misaha-btn-outline misaha-select-hall"
                        data-hall-id="<?php echo esc_attr($hall->id); ?>"
                        data-hall-name="<?php echo esc_attr($hall->name); ?>"
                        data-hall-price="<?php echo esc_attr($hall->price_per_hour); ?>">
                    <?php misaha_e('Select Hall'); ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="misaha-date-picker" id="misaha-date-picker" style="display:none;">
            <label for="misaha-booking-date">📅 <?php misaha_e('Select Date'); ?></label>
            <input type="date" id="misaha-booking-date"
                   min="<?php echo esc_attr( date('Y-m-d') ); ?>"
                   max="<?php echo esc_attr( date('Y-m-d', strtotime('+3 months')) ); ?>">
            <button class="misaha-btn misaha-btn-primary" id="misaha-load-slots"><?php misaha_e('View Available Slots'); ?> <?php echo $arrow_r; ?></button>
        </div>

        <?php endif; ?>
    </div>

    <!-- ── Step 2: Time Slots ─────────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-step-2" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-back" data-to="1"><?php echo $arrow_l; ?> <?php misaha_e('Back'); ?></button>
            <h2 class="misaha-panel-title">⏰ <?php misaha_e('Available Slots'); ?></h2>
        </div>

        <div class="misaha-selected-info" id="misaha-selected-info"></div>
        <div class="misaha-loading" id="misaha-slots-loading" style="display:none;">
            <div class="misaha-spinner"></div>
            <p><?php misaha_e('Loading slots…'); ?></p>
        </div>
        <div class="misaha-slots-grid" id="misaha-slots-grid"></div>

        <div class="misaha-legend">
            <span class="misaha-legend-item"><span class="misaha-slot-dot available"></span> <?php misaha_e('Available'); ?></span>
            <span class="misaha-legend-item"><span class="misaha-slot-dot selected"></span> <?php misaha_e('Selected'); ?></span>
            <span class="misaha-legend-item"><span class="misaha-slot-dot booked"></span> <?php misaha_e('Booked'); ?></span>
        </div>

        <button class="misaha-btn misaha-btn-primary" id="misaha-proceed-confirm" style="display:none;"><?php misaha_e('Continue'); ?> <?php echo $arrow_r; ?></button>
    </div>

    <!-- ── Step 3: Confirm & Pay ──────────────────────────────────────────── -->
    <div class="misaha-panel" id="misaha-step-3" style="display:none;">
        <div class="misaha-panel-header">
            <button class="misaha-btn misaha-btn-ghost misaha-back" data-to="2"><?php echo $arrow_l; ?> <?php misaha_e('Back'); ?></button>
            <h2 class="misaha-panel-title">✅ <?php misaha_e('Confirm Booking'); ?></h2>
        </div>

        <div class="misaha-summary-card" id="misaha-booking-summary"></div>

        <div class="misaha-discount-row">
            <input type="text" id="misaha-discount-code" placeholder="<?php echo misaha_esc_attr('Discount code (optional)'); ?>" style="text-transform:uppercase">
            <button class="misaha-btn misaha-btn-outline" id="misaha-apply-discount"><?php misaha_e('Apply'); ?></button>
        </div>
        <div class="misaha-discount-msg" id="misaha-discount-msg"></div>

        <div class="misaha-price-breakdown" id="misaha-price-breakdown"></div>

        <button class="misaha-btn misaha-btn-primary misaha-btn-lg" id="misaha-pay-now">
            💳 <?php misaha_e('Proceed to Payment'); ?>
        </button>
        <div class="misaha-booking-result" id="misaha-booking-result" style="display:none;"></div>
    </div>

</div><!-- .misaha-wrap -->