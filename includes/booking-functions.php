<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// HALLS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return all active halls.
 */
function misaha_get_all_halls() {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}misaha_halls WHERE status = %s ORDER BY name ASC",
            'active'
        )
    );
}

/**
 * Get a single hall by ID.
 */
function misaha_get_hall( $hall_id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}misaha_halls WHERE id = %d",
            $hall_id
        )
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// HOURLY SLOTS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build 1-hour slots for a given hall & date (08:00 → 22:00).
 * Returns each slot with status: 'available' or 'booked'.
 */
function misaha_get_hourly_slots( $hall_id, $date ) {
    global $wpdb;

    $hall = misaha_get_hall( $hall_id );
    if ( ! $hall ) return array();

    $price_per_hour = misaha_get_hall_price( $hall_id );

    // Fetch confirmed bookings for this hall & date
    $booked = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}misaha_bookings
             WHERE hall_id = %d AND booking_date = %s AND status != %s",
            $hall_id, $date, 'cancelled'
        )
    );

    $booked_ranges = array();
    foreach ( $booked as $b ) {
        $booked_ranges[] = array(
            'start' => strtotime( $date . ' ' . $b->start_time ),
            'end'   => strtotime( $date . ' ' . $b->end_time ),
        );
    }

    $slots = array();
    for ( $h = 8; $h < 22; $h++ ) {
        $slot_start = strtotime( $date . ' ' . sprintf( '%02d:00:00', $h ) );
        $slot_end   = strtotime( $date . ' ' . sprintf( '%02d:00:00', $h + 1 ) );

        $is_booked = false;
        foreach ( $booked_ranges as $r ) {
            // Overlap check: slot overlaps if start < range_end && end > range_start
            if ( $slot_start < $r['end'] && $slot_end > $r['start'] ) {
                $is_booked = true;
                break;
            }
        }

        // Cannot book in the past
        if ( $slot_start < time() ) {
            $is_booked = true;
        }

        $slots[] = array(
            'label'  => sprintf( '%02d:00 – %02d:00', $h, $h + 1 ),
            'start'  => sprintf( '%02d:00:00', $h ),
            'end'    => sprintf( '%02d:00:00', $h + 1 ),
            'status' => $is_booked ? 'booked' : 'available',
            'price'  => $price_per_hour,
        );
    }

    return $slots;
}

// ─────────────────────────────────────────────────────────────────────────────
// CREATE HALL BOOKING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a hall booking after checking for conflicts.
 * Returns array with booking_id and payment_url on success, WP_Error on failure.
 *
 * @param int    $user_id
 * @param int    $hall_id
 * @param string $date         Y-m-d
 * @param string $slot         "HH:00:00-HH:00:00"
 * @param string $discount_code
 * @return array|WP_Error
 */
function misaha_create_hall_booking( $user_id, $hall_id, $date, $slot, $discount_code = '' ) {
    global $wpdb;

    // Parse slot "08:00:00-09:00:00"
    if ( strpos( $slot, '-' ) === false ) {
        return new WP_Error( 'invalid_slot', 'Invalid time slot format.' );
    }
    list( $start_time, $end_time ) = explode( '-', $slot, 2 );
    $start_time = sanitize_text_field( trim( $start_time ) );
    $end_time   = sanitize_text_field( trim( $end_time ) );

    // ── Conflict check (with row-level locking intent via SELECT FOR UPDATE workaround)
    $conflict = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}misaha_bookings
             WHERE hall_id      = %d
               AND booking_date = %s
               AND status       != %s
               AND (
                   (start_time < %s AND end_time > %s)
               )",
            $hall_id, $date, 'cancelled',
            $end_time, $start_time
        )
    );

    if ( (int) $conflict > 0 ) {
        return new WP_Error( 'slot_taken', 'Sorry, this slot was just booked. Please choose another.' );
    }

    // ── Pricing
    $base_price     = misaha_get_hall_price( $hall_id );
    $discount_data  = misaha_apply_discount( $discount_code, 'hall', $base_price );
    $discount_amount = $discount_data['discount_amount'];
    $final_price    = $discount_data['final_price'];

    // ── Insert booking
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'misaha_bookings',
        array(
            'user_id'        => $user_id,
            'hall_id'        => $hall_id,
            'booking_date'   => $date,
            'start_time'     => $start_time,
            'end_time'       => $end_time,
            'hours'          => 1,
            'base_price'     => $base_price,
            'discount_amount'=> $discount_amount,
            'final_price'    => $final_price,
            'discount_code'  => $discount_code ?: null,
            'payment_status' => 'pending',
            'status'         => 'pending',
        ),
        array( '%d','%d','%s','%s','%s','%d','%f','%f','%f','%s','%s','%s' )
    );

    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Failed to save booking. Please try again.' );
    }

    $booking_id = $wpdb->insert_id;

    // ── Increment discount usage
    if ( $discount_code ) {
        misaha_increment_discount_usage( $discount_code );
    }

    // ── Initiate Pluto payment
    $payment = misaha_initiate_pluto_payment( $booking_id, 'booking', $final_price, $user_id );

    return array(
        'booking_id'  => $booking_id,
        'final_price' => $final_price,
        'currency'    => get_option( 'misaha_currency', 'LYD' ),
        'payment_url' => isset( $payment['checkout_url'] ) ? $payment['checkout_url'] : '',
        'payment_id'  => isset( $payment['id'] )           ? $payment['id']           : '',
        'message'     => 'Booking created successfully.',
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// SEATS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all seats for a hall with their current pass status.
 */
function misaha_get_seats_for_hall( $hall_id ) {
    global $wpdb;

    $seats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}misaha_seats WHERE hall_id = %d ORDER BY row_label ASC, seat_number ASC",
            $hall_id
        )
    );

    // Mark seats that have active passes today
    $today = current_time( 'Y-m-d' );
    foreach ( $seats as &$seat ) {
        $active_pass = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, pass_type, end_date FROM {$wpdb->prefix}misaha_passes
                 WHERE seat_id = %d AND status = %s AND start_date <= %s AND end_date >= %s AND payment_status = %s
                 LIMIT 1",
                $seat->id, 'active', $today, $today, 'paid'
            )
        );
        $seat->pass_status = $active_pass ? 'occupied' : 'available';
        $seat->active_pass = $active_pass;
    }
    unset( $seat );

    return $seats;
}

// ─────────────────────────────────────────────────────────────────────────────
// CREATE SEAT PASS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a seat pass (day / week / month).
 * Returns array with pass_id and payment info, or WP_Error.
 */
function misaha_create_seat_pass( $user_id, $seat_id, $hall_id, $pass_type, $start_date, $discount_code = '' ) {
    global $wpdb;

    $allowed = array( 'day', 'week', 'month' );
    if ( ! in_array( $pass_type, $allowed, true ) ) {
        return new WP_Error( 'invalid_pass', 'Invalid pass type.' );
    }

    // Calculate end date
    $start_ts = strtotime( $start_date );
    switch ( $pass_type ) {
        case 'day':
            $end_date = date( 'Y-m-d', $start_ts );
            break;
        case 'week':
            $end_date = date( 'Y-m-d', strtotime( '+6 days', $start_ts ) );
            break;
        case 'month':
            $end_date = date( 'Y-m-d', strtotime( '+1 month -1 day', $start_ts ) );
            break;
    }

    // Check seat conflict for the overlapping period
    $conflict = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}misaha_passes
             WHERE seat_id = %d AND status != %s AND payment_status = %s
               AND start_date <= %s AND end_date >= %s",
            $seat_id, 'cancelled', 'paid', $end_date, $start_date
        )
    );

    if ( (int) $conflict > 0 ) {
        return new WP_Error( 'seat_taken', 'This seat already has an active pass for the selected period.' );
    }

    // Pricing
    $base_price    = misaha_get_pass_price( $pass_type );
    $discount_data = misaha_apply_discount( $discount_code, 'seat', $base_price );
    $final_price   = $discount_data['final_price'];
    $disc_amount   = $discount_data['discount_amount'];

    // Insert
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'misaha_passes',
        array(
            'user_id'        => $user_id,
            'seat_id'        => $seat_id,
            'hall_id'        => $hall_id,
            'pass_type'      => $pass_type,
            'start_date'     => $start_date,
            'end_date'       => $end_date,
            'base_price'     => $base_price,
            'discount_amount'=> $disc_amount,
            'final_price'    => $final_price,
            'discount_code'  => $discount_code ?: null,
            'payment_status' => 'pending',
            'status'         => 'active',
        ),
        array( '%d','%d','%d','%s','%s','%s','%f','%f','%f','%s','%s','%s' )
    );

    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Failed to save pass. Please try again.' );
    }

    $pass_id = $wpdb->insert_id;

    if ( $discount_code ) {
        misaha_increment_discount_usage( $discount_code );
    }

    $payment = misaha_initiate_pluto_payment( $pass_id, 'pass', $final_price, $user_id );

    return array(
        'pass_id'     => $pass_id,
        'pass_type'   => $pass_type,
        'start_date'  => $start_date,
        'end_date'    => $end_date,
        'final_price' => $final_price,
        'currency'    => get_option( 'misaha_currency', 'LYD' ),
        'payment_url' => isset( $payment['checkout_url'] ) ? $payment['checkout_url'] : '',
        'message'     => 'Pass created successfully.',
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// PRICING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the effective price per hour for a hall.
 * Individual hall price overrides global if set.
 */
function misaha_get_hall_price( $hall_id ) {
    global $wpdb;
    $hall = $wpdb->get_row(
        $wpdb->prepare( "SELECT price_per_hour FROM {$wpdb->prefix}misaha_halls WHERE id = %d", $hall_id )
    );
    if ( $hall && (float) $hall->price_per_hour > 0 ) {
        return (float) $hall->price_per_hour;
    }
    return (float) get_option( 'misaha_price_hall_per_hour', 50 );
}

/**
 * Get pricing for a pass type from options table.
 */
function misaha_get_pass_price( $pass_type ) {
    $map = array(
        'day'   => 'misaha_price_seat_day',
        'week'  => 'misaha_price_seat_week',
        'month' => 'misaha_price_seat_month',
    );
    $key = isset( $map[ $pass_type ] ) ? $map[ $pass_type ] : 'misaha_price_seat_day';
    return (float) get_option( $key, 15 );
}

// ─────────────────────────────────────────────────────────────────────────────
// DISCOUNTS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Apply a discount code to a price.
 *
 * @return array { valid, discount_amount, final_price, message }
 */
function misaha_apply_discount( $code, $type, $price ) {
    $result = array(
        'valid'           => false,
        'discount_amount' => 0.00,
        'final_price'     => $price,
        'message'         => '',
        'code'            => $code,
    );

    if ( empty( $code ) ) return $result;

    global $wpdb;
    $today    = current_time( 'Y-m-d' );
    $discount = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}misaha_discounts
             WHERE code = %s AND active = 1
               AND (valid_from IS NULL OR valid_from <= %s)
               AND (valid_until IS NULL OR valid_until >= %s)
               AND (applies_to = 'all' OR applies_to = %s)
             LIMIT 1",
            $code, $today, $today, $type
        )
    );

    if ( ! $discount ) {
        $result['message'] = 'Invalid or expired discount code.';
        return $result;
    }

    // Usage limit check
    if ( $discount->usage_limit !== null && (int) $discount->usage_count >= (int) $discount->usage_limit ) {
        $result['message'] = 'This discount code has reached its usage limit.';
        return $result;
    }

    // Minimum amount check
    if ( $price < (float) $discount->min_amount ) {
        $result['message'] = 'Minimum order amount not met for this discount.';
        return $result;
    }

    // Calculate discount
    if ( $discount->type === 'percentage' ) {
        $disc_amount = round( $price * ( (float) $discount->value / 100 ), 2 );
    } else {
        $disc_amount = min( (float) $discount->value, $price );
    }

    $final = max( 0, $price - $disc_amount );

    $result['valid']           = true;
    $result['discount_amount'] = $disc_amount;
    $result['final_price']     = $final;
    $result['name']            = $discount->name;
    $result['message']         = 'Discount applied: ' . esc_html( $discount->name );

    return $result;
}

/**
 * Increment usage count for a discount code.
 */
function misaha_increment_discount_usage( $code ) {
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}misaha_discounts SET usage_count = usage_count + 1 WHERE code = %s",
            $code
        )
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// USER DASHBOARD DATA
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get all hall bookings for a user.
 */
function misaha_get_user_bookings( $user_id ) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT b.*, h.name AS hall_name
             FROM {$wpdb->prefix}misaha_bookings b
             LEFT JOIN {$wpdb->prefix}misaha_halls h ON b.hall_id = h.id
             WHERE b.user_id = %d
             ORDER BY b.booking_date DESC, b.start_time DESC",
            $user_id
        )
    );
}

/**
 * Get all passes for a user.
 */
function misaha_get_user_passes( $user_id ) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.*, s.seat_number, h.name AS hall_name
             FROM {$wpdb->prefix}misaha_passes p
             LEFT JOIN {$wpdb->prefix}misaha_seats s ON p.seat_id = s.id
             LEFT JOIN {$wpdb->prefix}misaha_halls h ON p.hall_id = h.id
             WHERE p.user_id = %d
             ORDER BY p.created_at DESC",
            $user_id
        )
    );
}

/**
 * Get payment history for a user.
 */
function misaha_get_user_payments( $user_id ) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}misaha_payments
             WHERE user_id = %d
             ORDER BY created_at DESC",
            $user_id
        )
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN: All Bookings
// ─────────────────────────────────────────────────────────────────────────────

function misaha_admin_get_all_bookings( $filters = array() ) {
    global $wpdb;

    $where = '1=1';
    $vals  = array();

    if ( ! empty( $filters['status'] ) ) {
        $where  .= ' AND b.status = %s';
        $vals[]  = sanitize_text_field( $filters['status'] );
    }
    if ( ! empty( $filters['date'] ) ) {
        $where  .= ' AND b.booking_date = %s';
        $vals[]  = sanitize_text_field( $filters['date'] );
    }

    $sql = "SELECT b.*, h.name AS hall_name, u.display_name AS user_name
            FROM {$wpdb->prefix}misaha_bookings b
            LEFT JOIN {$wpdb->prefix}misaha_halls h ON b.hall_id = h.id
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            WHERE $where
            ORDER BY b.booking_date DESC, b.start_time DESC";

    if ( ! empty( $vals ) ) {
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$vals ) );
    }

    return $wpdb->get_results( $sql );
}