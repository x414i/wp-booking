<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Create all custom database tables for Misaha Booking System.
 * Uses dbDelta for safe CREATE / ALTER on re-activation.
 */
function misaha_setup_database() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // ── 1. Halls ─────────────────────────────────────────────────────────────
    $t = $wpdb->prefix . 'misaha_halls';
    dbDelta( "CREATE TABLE $t (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(150) NOT NULL,
        capacity     SMALLINT UNSIGNED NOT NULL DEFAULT 10,
        description  TEXT,
        price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $c;" );

    // ── 2. Seats ──────────────────────────────────────────────────────────────
    $t = $wpdb->prefix . 'misaha_seats';
    dbDelta( "CREATE TABLE $t (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hall_id      INT UNSIGNED NOT NULL,
        seat_number  VARCHAR(10) NOT NULL,
        row_label    VARCHAR(5)  NOT NULL DEFAULT 'A',
        status       ENUM('available','unavailable') NOT NULL DEFAULT 'available',
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY hall_idx (hall_id)
    ) $c;" );

    // ── 3. Bookings (hall hourly) ─────────────────────────────────────────────
    $t = $wpdb->prefix . 'misaha_bookings';
    dbDelta( "CREATE TABLE $t (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id         BIGINT UNSIGNED NOT NULL,
        hall_id         INT UNSIGNED NOT NULL,
        booking_date    DATE        NOT NULL,
        start_time      TIME        NOT NULL,
        end_time        TIME        NOT NULL,
        hours           TINYINT UNSIGNED NOT NULL DEFAULT 1,
        base_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        final_price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_code   VARCHAR(50)  DEFAULT NULL,
        payment_status  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
        payment_id      VARCHAR(100) DEFAULT NULL,
        status          ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
        notes           TEXT,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_idx    (user_id),
        KEY hall_date   (hall_id, booking_date)
    ) $c;" );

    // ── 4. Passes (seat day/week/month) ───────────────────────────────────────
    $t = $wpdb->prefix . 'misaha_passes';
    dbDelta( "CREATE TABLE $t (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id         BIGINT UNSIGNED NOT NULL,
        seat_id         INT UNSIGNED NOT NULL,
        hall_id         INT UNSIGNED NOT NULL,
        pass_type       ENUM('day','week','month') NOT NULL,
        start_date      DATE        NOT NULL,
        end_date        DATE        NOT NULL,
        base_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        final_price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_code   VARCHAR(50)  DEFAULT NULL,
        payment_status  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
        payment_id      VARCHAR(100) DEFAULT NULL,
        status          ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_idx    (user_id),
        KEY seat_idx    (seat_id)
    ) $c;" );

    // ── 5. Payments ───────────────────────────────────────────────────────────
    $t = $wpdb->prefix . 'misaha_payments';
    dbDelta( "CREATE TABLE $t (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id         BIGINT UNSIGNED NOT NULL,
        reference_type  ENUM('booking','pass') NOT NULL,
        reference_id    INT UNSIGNED NOT NULL,
        amount          DECIMAL(10,2) NOT NULL,
        currency        VARCHAR(5) NOT NULL DEFAULT 'LYD',
        pluto_payment_id VARCHAR(120) DEFAULT NULL,
        pluto_status    VARCHAR(50)  DEFAULT NULL,
        raw_response    LONGTEXT,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_idx    (user_id),
        KEY ref_idx     (reference_type, reference_id)
    ) $c;" );

    // ── 6. Discount Rules ─────────────────────────────────────────────────────
    $t = $wpdb->prefix . 'misaha_discounts';
    dbDelta( "CREATE TABLE $t (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(100) NOT NULL,
        code            VARCHAR(50) UNIQUE NOT NULL,
        type            ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
        value           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        applies_to      ENUM('all','hall','seat') NOT NULL DEFAULT 'all',
        discount_category VARCHAR(50) DEFAULT 'custom',
        min_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        usage_limit     INT UNSIGNED DEFAULT NULL,
        usage_count     INT UNSIGNED NOT NULL DEFAULT 0,
        valid_from      DATE DEFAULT NULL,
        valid_until     DATE DEFAULT NULL,
        active          TINYINT(1) NOT NULL DEFAULT 1,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY code_idx    (code)
    ) $c;" );
}