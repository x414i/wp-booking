<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id  = get_current_user_id();
$user     = wp_get_current_user();
$bookings = misaha_get_user_bookings( $user_id );
$passes   = misaha_get_user_passes( $user_id );
$payments = misaha_get_user_payments( $user_id );
$currency = get_option( 'misaha_currency', 'LYD' );
$today    = current_time( 'Y-m-d' );
$is_rtl   = misaha_is_rtl();
$dir      = $is_rtl ? 'rtl' : 'ltr';

// Stats
$active_passes    = array_filter( $passes, fn($p) => $p->status === 'active' && $p->end_date >= $today );
$upcoming_bookings= array_filter( $bookings, fn($b) => $b->booking_date >= $today && $b->status !== 'cancelled' );
$total_spent      = array_sum( array_column( array_filter($payments, fn($p) => $p->pluto_status === 'paid'), 'amount' ) );

// Status translation helper
function misaha_status_label( $status ) {
    return misaha_esc_html( ucfirst( $status ) );
}
?>

<div class="misaha-wrap misaha-dashboard" id="misaha-user-dashboard" dir="<?php echo $dir; ?>">

    <!-- ── Header ────────────────────────────────────────────────────── -->
    <div class="misaha-dash-header">
        <div class="misaha-dash-avatar"><?php echo esc_html( strtoupper( substr($user->display_name,0,2) ) ); ?></div>
        <div class="misaha-dash-info">
            <h2><?php misaha_e('Welcome back,'); ?> <?php echo esc_html( $user->display_name ); ?>!</h2>
            <p><?php echo esc_html( $user->user_email ); ?></p>
        </div>
    </div>

    <!-- ── Quick Stats ────────────────────────────────────────────────── -->
    <div class="misaha-stat-cards">
        <div class="misaha-stat-card misaha-stat-blue">
            <div class="misaha-stat-icon">🏢</div>
            <div class="misaha-stat-value"><?php echo count($bookings); ?></div>
            <div class="misaha-stat-label"><?php misaha_e('Total Bookings'); ?></div>
        </div>
        <div class="misaha-stat-card misaha-stat-green">
            <div class="misaha-stat-icon">💺</div>
            <div class="misaha-stat-value"><?php echo count($active_passes); ?></div>
            <div class="misaha-stat-label"><?php misaha_e('Active Passes'); ?></div>
        </div>
        <div class="misaha-stat-card misaha-stat-orange">
            <div class="misaha-stat-icon">📅</div>
            <div class="misaha-stat-value"><?php echo count($upcoming_bookings); ?></div>
            <div class="misaha-stat-label"><?php misaha_e('Upcoming Bookings'); ?></div>
        </div>
        <div class="misaha-stat-card misaha-stat-purple">
            <div class="misaha-stat-icon">💰</div>
            <div class="misaha-stat-value"><?php echo esc_html($currency . ' ' . number_format($total_spent,2)); ?></div>
            <div class="misaha-stat-label"><?php misaha_e('Total Spent'); ?></div>
        </div>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────── -->
    <div class="misaha-dashboard-tabs">
        <button class="misaha-tab-btn active" data-tab="bookings">🏢 <?php misaha_e('Hall Bookings'); ?></button>
        <button class="misaha-tab-btn" data-tab="passes">💺 <?php misaha_e('Seat Passes'); ?></button>
        <button class="misaha-tab-btn" data-tab="payments">💳 <?php misaha_e('Payments'); ?></button>
    </div>

    <!-- ── Hall Bookings Tab ──────────────────────────────────────────── -->
    <div class="misaha-tab-panel active" id="misaha-tab-bookings">
        <?php if ( empty($bookings) ) : ?>
        <div class="misaha-empty-state">
            <div class="misaha-empty-icon">🏢</div>
            <h3><?php misaha_e('No Hall Bookings Yet'); ?></h3>
            <p><?php misaha_e('Start by booking a hall for your event or meeting.'); ?></p>
            <a href="<?php echo esc_url( get_site_url() . '/booking' ); ?>" class="misaha-btn misaha-btn-primary"><?php misaha_e('Book a Hall'); ?></a>
        </div>
        <?php else : ?>
        <div class="misaha-bookings-list">
            <?php foreach ( $bookings as $b ) :
                $is_upcoming = $b->booking_date >= $today;
            ?>
            <div class="misaha-booking-item <?php echo $is_upcoming ? 'upcoming' : 'past'; ?>">
                <div class="misaha-booking-icon">🏛️</div>
                <div class="misaha-booking-details">
                    <h4><?php echo esc_html($b->hall_name); ?></h4>
                    <div class="misaha-booking-meta">
                        <span>📅 <?php echo esc_html( date_i18n( 'D, d M Y', strtotime($b->booking_date) ) ); ?></span>
                        <span>⏰ <?php echo esc_html( substr($b->start_time,0,5) . ' – ' . substr($b->end_time,0,5) ); ?></span>
                        <span>💰 <?php echo esc_html($currency . ' ' . number_format($b->final_price,2)); ?></span>
                    </div>
                </div>
                <div class="misaha-booking-badges">
                    <span class="misaha-badge misaha-badge-<?php echo esc_attr($b->status); ?>"><?php echo misaha_status_label($b->status); ?></span>
                    <span class="misaha-badge misaha-badge-<?php echo esc_attr($b->payment_status); ?>"><?php echo misaha_status_label($b->payment_status); ?></span>
                </div>
                <?php if ($b->discount_code) : ?>
                <div class="misaha-discount-tag">🏷️ <?php echo esc_html($b->discount_code); ?> — <?php misaha_e('saved'); ?> <?php echo esc_html($currency.' '.number_format($b->discount_amount,2)); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Passes Tab ─────────────────────────────────────────────────── -->
    <div class="misaha-tab-panel" id="misaha-tab-passes" style="display:none;">
        <?php if ( empty($passes) ) : ?>
        <div class="misaha-empty-state">
            <div class="misaha-empty-icon">💺</div>
            <h3><?php misaha_e('No Seat Passes Yet'); ?></h3>
            <p><?php misaha_e('Reserve your dedicated seat with a day, weekly, or monthly pass.'); ?></p>
            <a href="<?php echo esc_url( get_site_url() . '/seats' ); ?>" class="misaha-btn misaha-btn-primary"><?php misaha_e('Browse Seats'); ?></a>
        </div>
        <?php else : ?>
        <div class="misaha-bookings-list">
            <?php foreach ( $passes as $p ) :
                $is_active = $p->status === 'active' && $p->end_date >= $today;
                $days_left = max(0, (int) ((strtotime($p->end_date) - strtotime($today)) / 86400) + 1);
                $pass_label = misaha__( ucfirst($p->pass_type) );
            ?>
            <div class="misaha-booking-item <?php echo $is_active ? 'active-pass' : 'expired-pass'; ?>">
                <div class="misaha-booking-icon">
                    <?php echo $p->pass_type === 'day' ? '☀️' : ($p->pass_type === 'week' ? '📅' : '🗓️'); ?>
                </div>
                <div class="misaha-booking-details">
                    <h4>
                        <?php echo esc_html($p->hall_name); ?>
                        — <?php misaha_e('Seat'); ?> <strong><?php echo esc_html($p->seat_number); ?></strong>
                    </h4>
                    <div class="misaha-booking-meta">
                        <span>🎟️ <?php echo esc_html( $pass_label ); ?> <?php misaha_e('Pass'); ?></span>
                        <span>📅 <?php echo esc_html( date_i18n('d M', strtotime($p->start_date)) . ' – ' . date_i18n('d M Y', strtotime($p->end_date)) ); ?></span>
                        <span>💰 <?php echo esc_html($currency . ' ' . number_format($p->final_price,2)); ?></span>
                    </div>
                    <?php if ($is_active) : ?>
                    <div class="misaha-pass-countdown">⏳ <?php echo esc_html($days_left); ?> <?php echo $days_left !== 1 ? misaha_esc_html('days') : misaha_esc_html('day'); ?> <?php misaha_e('remaining'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="misaha-booking-badges">
                    <span class="misaha-badge misaha-badge-<?php echo esc_attr($p->payment_status); ?>"><?php echo misaha_status_label($p->payment_status); ?></span>
                    <span class="misaha-badge misaha-badge-<?php echo $is_active ? 'confirmed' : 'cancelled'; ?>">
                        <?php echo $is_active ? misaha_esc_html('Active') : misaha_status_label($p->status); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Payments Tab ───────────────────────────────────────────────── -->
    <div class="misaha-tab-panel" id="misaha-tab-payments" style="display:none;">
        <?php if ( empty($payments) ) : ?>
        <div class="misaha-empty-state">
            <div class="misaha-empty-icon">💳</div>
            <h3><?php misaha_e('No Payment History'); ?></h3>
            <p><?php misaha_e('Your transactions will appear here.'); ?></p>
        </div>
        <?php else : ?>
        <div class="misaha-payments-table-wrap">
            <table class="misaha-payments-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php misaha_e('Type'); ?></th>
                        <th><?php misaha_e('Amount'); ?></th>
                        <th><?php misaha_e('Status'); ?></th>
                        <th><?php misaha_e('Pluto ID'); ?></th>
                        <th><?php misaha_e('Date'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $payments as $pay ) : ?>
                    <tr>
                        <td><?php echo esc_html($pay->id); ?></td>
                        <td><?php echo esc_html( misaha__( ucfirst($pay->reference_type) ) . ' #' . $pay->reference_id ); ?></td>
                        <td><?php echo esc_html($currency . ' ' . number_format($pay->amount,2)); ?></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($pay->pluto_status ?: 'pending'); ?>"><?php echo misaha_status_label($pay->pluto_status ?: 'pending'); ?></span></td>
                        <td><code><?php echo $pay->pluto_payment_id ? esc_html(substr($pay->pluto_payment_id,0,16)).'…' : '—'; ?></code></td>
                        <td><?php echo esc_html( date_i18n('d M Y H:i', strtotime($pay->created_at)) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .misaha-dashboard -->
