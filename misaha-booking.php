<?php
/**
 * Plugin Name: Misaha Booking System
 * Plugin URI:  https://misaha.ly/booking
 * Description: A comprehensive booking plugin for Misaha halls and seats with Pluto payment integration, dynamic pricing, and user dashboards.
 * Version:     2.0.0
 * Author:      Web Dev Team
 * Text Domain: misaha-booking
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Plugin Constants ────────────────────────────────────────────────────────
define( 'MISAHA_VERSION',    '2.0.0' );
define( 'MISAHA_DIR',        plugin_dir_path( __FILE__ ) );
define( 'MISAHA_URL',        plugin_dir_url( __FILE__ ) );
define( 'MISAHA_PLUTO_KEY',  '984adf4c-44e1-418f-829b' );   // Test key

// ─── Core Includes ───────────────────────────────────────────────────────────
require_once MISAHA_DIR . 'includes/database-setup.php';
require_once MISAHA_DIR . 'includes/booking-functions.php';
require_once MISAHA_DIR . 'includes/payment-gateway.php';
require_once MISAHA_DIR . 'includes/shortcodes.php';

// ─── Admin Includes ──────────────────────────────────────────────────────────
if ( is_admin() ) {
    require_once MISAHA_DIR . 'admin/admin-settings.php';
    require_once MISAHA_DIR . 'admin/pricing-discounts.php';
}

// ─── Activation / Deactivation ──────────────────────────────────────────────
register_activation_hook( __FILE__, 'misaha_activate' );
function misaha_activate() {
    misaha_setup_database();
    misaha_seed_default_data();
}

register_deactivation_hook( __FILE__, 'misaha_deactivate' );
function misaha_deactivate() {
    // Flush rewrite rules, etc.
    flush_rewrite_rules();
}

// ─── Seed Default Data (runs once on activation) ─────────────────────────────
function misaha_seed_default_data() {
    global $wpdb;

    // Default pricing options
    $defaults = array(
        'misaha_price_hall_per_hour'  => 50,
        'misaha_price_seat_day'       => 15,
        'misaha_price_seat_week'      => 80,
        'misaha_price_seat_month'     => 250,
        'misaha_pluto_api_key'        => MISAHA_PLUTO_KEY,
        'misaha_currency'             => 'LYD',
        'misaha_seats_per_hall'       => 20,
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }

    // Insert a sample hall if table is empty
    $halls_table = $wpdb->prefix . 'misaha_halls';
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM $halls_table" );
    if ( (int) $count === 0 ) {
        $wpdb->insert( $halls_table, array(
            'name'          => 'Main Conference Hall',
            'capacity'      => 50,
            'description'   => 'Large hall with projector and whiteboard',
            'price_per_hour'=> 50.00,
            'status'        => 'active',
        ) );
        $wpdb->insert( $halls_table, array(
            'name'          => 'Board Room',
            'capacity'      => 12,
            'description'   => 'Intimate board room for meetings',
            'price_per_hour'=> 30.00,
            'status'        => 'active',
        ) );

        // Seed seats for each hall
        $halls = $wpdb->get_results( "SELECT id FROM $halls_table" );
        $seats_table = $wpdb->prefix . 'misaha_seats';
        foreach ( $halls as $hall ) {
            $rows   = array('A','B','C','D');
            $cols   = range(1, 5);
            foreach ( $rows as $row ) {
                foreach ( $cols as $col ) {
                    $wpdb->insert( $seats_table, array(
                        'hall_id'     => $hall->id,
                        'seat_number' => $row . $col,
                        'status'      => 'available',
                    ) );
                }
            }
        }
    }
}

// ─── Frontend Assets ─────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'misaha_enqueue_assets' );
function misaha_enqueue_assets() {
    wp_enqueue_style(
        'misaha-style',
        MISAHA_URL . 'assets/css/style.css',
        array(),
        MISAHA_VERSION
    );

    wp_enqueue_script(
        'misaha-script',
        MISAHA_URL . 'assets/js/booking-script.js',
        array( 'jquery' ),
        MISAHA_VERSION,
        true
    );

    wp_localize_script( 'misaha-script', 'misahaVars', array(
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'misaha_nonce' ),
        'currency'   => get_option( 'misaha_currency', 'LYD' ),
        'siteUrl'    => get_site_url(),
        'isLoggedIn' => is_user_logged_in() ? 1 : 0,
        'loginUrl'   => wp_login_url( get_permalink() ),
    ) );
}

// ─── Admin Assets ────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'misaha_admin_enqueue_assets' );
function misaha_admin_enqueue_assets( $hook ) {
    if ( strpos( $hook, 'misaha' ) === false ) return;

    wp_enqueue_style(
        'misaha-admin-style',
        MISAHA_URL . 'assets/css/admin-style.css',
        array(),
        MISAHA_VERSION
    );
    wp_enqueue_script(
        'misaha-admin-script',
        MISAHA_URL . 'assets/js/admin-script.js',
        array( 'jquery' ),
        MISAHA_VERSION,
        true
    );
    wp_localize_script( 'misaha-admin-script', 'misahaAdmin', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'misaha_admin_nonce' ),
    ) );
}

// ─── AJAX: Get Available Slots ────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_get_slots',        'misaha_ajax_get_slots' );
add_action( 'wp_ajax_nopriv_misaha_get_slots', 'misaha_ajax_get_slots' );
function misaha_ajax_get_slots() {
    check_ajax_referer( 'misaha_nonce', 'nonce' );

    $hall_id = isset( $_POST['hall_id'] ) ? absint( $_POST['hall_id'] ) : 0;
    $date    = isset( $_POST['date'] )    ? sanitize_text_field( $_POST['date'] ) : '';

    if ( ! $hall_id || ! $date ) {
        wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
    }

    $slots = misaha_get_hourly_slots( $hall_id, $date );
    wp_send_json_success( $slots );
}

// ─── AJAX: Create Hall Booking ────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_create_booking', 'misaha_ajax_create_booking' );
function misaha_ajax_create_booking() {
    check_ajax_referer( 'misaha_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Please log in to book a hall.' ) );
    }

    $user_id  = get_current_user_id();
    $hall_id  = isset( $_POST['hall_id'] )    ? absint( $_POST['hall_id'] )                    : 0;
    $date     = isset( $_POST['date'] )        ? sanitize_text_field( $_POST['date'] )          : '';
    $slot     = isset( $_POST['slot'] )        ? sanitize_text_field( $_POST['slot'] )          : '';
    $discount = isset( $_POST['discount_code'])? sanitize_text_field( $_POST['discount_code'] ) : '';

    if ( ! $hall_id || ! $date || ! $slot ) {
        wp_send_json_error( array( 'message' => 'Missing booking details.' ) );
    }

    $result = misaha_create_hall_booking( $user_id, $hall_id, $date, $slot, $discount );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( $result );
}

// ─── AJAX: Create Seat Pass ───────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_create_pass', 'misaha_ajax_create_pass' );
function misaha_ajax_create_pass() {
    check_ajax_referer( 'misaha_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Please log in to purchase a pass.' ) );
    }

    $user_id   = get_current_user_id();
    $seat_id   = isset( $_POST['seat_id'] )   ? absint( $_POST['seat_id'] )                    : 0;
    $hall_id   = isset( $_POST['hall_id'] )   ? absint( $_POST['hall_id'] )                    : 0;
    $pass_type = isset( $_POST['pass_type'] ) ? sanitize_text_field( $_POST['pass_type'] )     : '';
    $start     = isset( $_POST['start_date'] )? sanitize_text_field( $_POST['start_date'] )    : '';
    $discount  = isset( $_POST['discount_code'])? sanitize_text_field( $_POST['discount_code'] ) : '';

    if ( ! $seat_id || ! $pass_type || ! $start ) {
        wp_send_json_error( array( 'message' => 'Missing pass details.' ) );
    }

    $result = misaha_create_seat_pass( $user_id, $seat_id, $hall_id, $pass_type, $start, $discount );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( $result );
}

// ─── AJAX: Payment Callback ───────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_payment_callback',        'misaha_payment_callback' );
add_action( 'wp_ajax_nopriv_misaha_payment_callback', 'misaha_payment_callback' );
function misaha_payment_callback() {
    $payload = file_get_contents( 'php://input' );
    misaha_handle_pluto_callback( $payload );
    wp_send_json_success();
}

// ─── AJAX: Get Halls List ─────────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_get_halls',        'misaha_ajax_get_halls' );
add_action( 'wp_ajax_nopriv_misaha_get_halls', 'misaha_ajax_get_halls' );
function misaha_ajax_get_halls() {
    check_ajax_referer( 'misaha_nonce', 'nonce' );
    $halls = misaha_get_all_halls();
    wp_send_json_success( $halls );
}

// ─── AJAX: Get Seats for Hall ─────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_get_seats',        'misaha_ajax_get_seats' );
add_action( 'wp_ajax_nopriv_misaha_get_seats', 'misaha_ajax_get_seats' );
function misaha_ajax_get_seats() {
    check_ajax_referer( 'misaha_nonce', 'nonce' );
    $hall_id = isset( $_POST['hall_id'] ) ? absint( $_POST['hall_id'] ) : 0;
    if ( ! $hall_id ) {
        wp_send_json_error( array( 'message' => 'Invalid hall.' ) );
    }
    $seats = misaha_get_seats_for_hall( $hall_id );
    wp_send_json_success( $seats );
}

// ─── AJAX: Validate Discount Code ────────────────────────────────────────────
add_action( 'wp_ajax_misaha_validate_discount',        'misaha_ajax_validate_discount' );
add_action( 'wp_ajax_nopriv_misaha_validate_discount', 'misaha_ajax_validate_discount' );
function misaha_ajax_validate_discount() {
    check_ajax_referer( 'misaha_nonce', 'nonce' );
    $code  = isset( $_POST['code'] )   ? sanitize_text_field( $_POST['code'] )  : '';
    $type  = isset( $_POST['type'] )   ? sanitize_text_field( $_POST['type'] )  : 'hall';
    $price = isset( $_POST['price'] )  ? floatval( $_POST['price'] )            : 0;

    $discount = misaha_apply_discount( $code, $type, $price );
    wp_send_json_success( $discount );
}

// ─── AJAX: Admin – Save Hall ──────────────────────────────────────────────────
add_action( 'wp_ajax_misaha_admin_save_hall', 'misaha_ajax_admin_save_hall' );
function misaha_ajax_admin_save_hall() {
    check_ajax_referer( 'misaha_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $id          = isset( $_POST['id'] )           ? absint( $_POST['id'] )                       : 0;
    $name        = isset( $_POST['name'] )         ? sanitize_text_field( $_POST['name'] )        : '';
    $capacity    = isset( $_POST['capacity'] )     ? absint( $_POST['capacity'] )                  : 0;
    $description = isset( $_POST['description'] )  ? sanitize_textarea_field( $_POST['description'] ) : '';
    $price       = isset( $_POST['price_per_hour'])? floatval( $_POST['price_per_hour'] )          : 0;
    $status      = isset( $_POST['status'] )       ? sanitize_text_field( $_POST['status'] )      : 'active';

    global $wpdb;
    $table = $wpdb->prefix . 'misaha_halls';
    $data  = compact( 'name', 'capacity', 'description', 'status' );
    $data['price_per_hour'] = $price;

    if ( $id ) {
        $wpdb->update( $table, $data, array( 'id' => $id ) );
    } else {
        $wpdb->insert( $table, $data );
        $id = $wpdb->insert_id;
    }

    wp_send_json_success( array( 'id' => $id, 'message' => 'Hall saved.' ) );
}

// ─── AJAX: Admin – Delete Booking ────────────────────────────────────────────
add_action( 'wp_ajax_misaha_admin_delete_booking', 'misaha_ajax_admin_delete_booking' );
function misaha_ajax_admin_delete_booking() {
    check_ajax_referer( 'misaha_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $id = absint( $_POST['id'] );
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'misaha_bookings', array( 'id' => $id ) );
    wp_send_json_success( array( 'message' => 'Booking deleted.' ) );
}