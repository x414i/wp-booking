<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── [misaha_booking_calendar] ─────────────────────────────────────────────────
add_shortcode( 'misaha_booking_calendar', 'misaha_booking_calendar_shortcode' );
function misaha_booking_calendar_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'hall_id' => 0,
    ), $atts, 'misaha_booking_calendar' );

    if ( ! is_user_logged_in() ) {
        return misaha_login_notice( 'book a hall' );
    }

    ob_start();
    include MISAHA_DIR . 'templates/booking-calendar.php';
    return ob_get_clean();
}

// ── [misaha_seat_map] ─────────────────────────────────────────────────────────
add_shortcode( 'misaha_seat_map', 'misaha_seat_map_shortcode' );
function misaha_seat_map_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'hall_id' => 0,
    ), $atts, 'misaha_seat_map' );

    if ( ! is_user_logged_in() ) {
        return misaha_login_notice( 'select a seat' );
    }

    ob_start();
    include MISAHA_DIR . 'templates/seat-map.php';
    return ob_get_clean();
}

// ── [misaha_user_dashboard] ───────────────────────────────────────────────────
add_shortcode( 'misaha_user_dashboard', 'misaha_user_dashboard_shortcode' );
function misaha_user_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) {
        return misaha_login_notice( 'view your dashboard' );
    }

    ob_start();
    include MISAHA_DIR . 'templates/user-dashboard.php';
    return ob_get_clean();
}

// ── Helper ────────────────────────────────────────────────────────────────────
function misaha_login_notice( $action = 'continue' ) {
    $login_url = wp_login_url( get_permalink() );
    return '<div class="misaha-login-prompt">
        <div class="misaha-login-icon">🔐</div>
        <h3>Login Required</h3>
        <p>Please log in to ' . esc_html( $action ) . '.</p>
        <a href="' . esc_url( $login_url ) . '" class="misaha-btn misaha-btn-primary">Log In</a>
        <a href="' . esc_url( wp_registration_url() ) . '" class="misaha-btn misaha-btn-outline">Create Account</a>
    </div>';
}