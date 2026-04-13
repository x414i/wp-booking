<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── [misaha_booking_calendar] ─────────────────────────────────────────────────
add_shortcode( 'misaha_booking_calendar', 'misaha_booking_calendar_shortcode' );
function misaha_booking_calendar_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'hall_id' => 0,
    ), $atts, 'misaha_booking_calendar' );

    if ( ! is_user_logged_in() ) {
        return misaha_login_notice( misaha__('book a hall') );
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
        return misaha_login_notice( misaha__('select a seat') );
    }

    ob_start();
    include MISAHA_DIR . 'templates/seat-map.php';
    return ob_get_clean();
}

// ── [misaha_user_dashboard] ───────────────────────────────────────────────────
add_shortcode( 'misaha_user_dashboard', 'misaha_user_dashboard_shortcode' );
function misaha_user_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) {
        return misaha_login_notice( misaha__('view your dashboard') );
    }

    ob_start();
    include MISAHA_DIR . 'templates/user-dashboard.php';
    return ob_get_clean();
}

// ── Helper ────────────────────────────────────────────────────────────────────
function misaha_login_notice( $action = '' ) {
    if ( empty( $action ) ) {
        $action = misaha__( 'continue' );
    }
    $is_rtl    = misaha_is_rtl();
    $dir       = $is_rtl ? 'rtl' : 'ltr';
    $login_url = wp_login_url( get_permalink() );

    return '<div class="misaha-login-prompt" dir="' . $dir . '">
        <div class="misaha-login-icon">🔐</div>
        <h3>' . misaha_esc_html('Login Required') . '</h3>
        <p>' . misaha_esc_html('Please log in to') . ' ' . esc_html( $action ) . '.</p>
        <a href="' . esc_url( $login_url ) . '" class="misaha-btn misaha-btn-primary">' . misaha_esc_html('Log In') . '</a>
        <a href="' . esc_url( wp_registration_url() ) . '" class="misaha-btn misaha-btn-outline">' . misaha_esc_html('Create Account') . '</a>
    </div>';
}