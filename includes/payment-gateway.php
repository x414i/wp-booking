<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Pluto API base URL (update when real endpoint is confirmed)
define( 'MISAHA_PLUTO_ENDPOINT', 'https://api.pluto.com/v1' );

/**
 * Initiate a Pluto payment and store a pending payment record.
 *
 * @param int    $reference_id   Booking or Pass ID
 * @param string $reference_type 'booking' | 'pass'
 * @param float  $amount
 * @param int    $user_id
 * @return array  Pluto response body (may contain checkout_url)
 */
function misaha_initiate_pluto_payment( $reference_id, $reference_type, $amount, $user_id ) {
    global $wpdb;

    $api_key      = get_option( 'misaha_pluto_api_key', MISAHA_PLUTO_KEY );
    $currency     = get_option( 'misaha_currency', 'LYD' );
    $callback_url = admin_url( 'admin-ajax.php?action=misaha_payment_callback' );
    $return_url   = add_query_arg( array(
        'misaha_ref'  => $reference_type,
        'misaha_id'   => $reference_id,
    ), get_site_url() );

    $user = get_userdata( $user_id );

    $payload = array(
        'amount'        => (int) round( $amount * 100 ),    // cents
        'currency'      => $currency,
        'description'   => ucfirst( $reference_type ) . ' payment #' . $reference_id,
        'reference_id'  => $reference_type . '_' . $reference_id,
        'customer'      => array(
            'id'    => $user_id,
            'email' => $user ? $user->user_email : '',
            'name'  => $user ? $user->display_name : '',
        ),
        'callback_url'  => $callback_url,
        'return_url'    => $return_url,
        'metadata'      => array(
            'booking_type' => $reference_type,
            'booking_id'   => $reference_id,
        ),
    );

    $response = wp_remote_post(
        MISAHA_PLUTO_ENDPOINT . '/payments',
        array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
        )
    );

    $body = array();
    if ( ! is_wp_error( $response ) ) {
        $raw  = wp_remote_retrieve_body( $response );
        $body = json_decode( $raw, true ) ?: array();
    } else {
        $raw = $response->get_error_message();
    }

    // Log payment record
    $wpdb->insert(
        $wpdb->prefix . 'misaha_payments',
        array(
            'user_id'         => $user_id,
            'reference_type'  => $reference_type,
            'reference_id'    => $reference_id,
            'amount'          => $amount,
            'currency'        => $currency,
            'pluto_payment_id'=> isset( $body['id'] ) ? sanitize_text_field( $body['id'] ) : null,
            'pluto_status'    => isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'initiated',
            'raw_response'    => is_string( $raw ) ? $raw : wp_json_encode( $body ),
        ),
        array( '%d','%s','%d','%f','%s','%s','%s','%s' )
    );

    return $body;
}

/**
 * Handle incoming Pluto webhook callback.
 * Verifies the payload and updates booking/pass status.
 *
 * @param string $raw_payload JSON body from Pluto
 */
function misaha_handle_pluto_callback( $raw_payload ) {
    global $wpdb;

    $data = json_decode( $raw_payload, true );
    if ( ! is_array( $data ) ) return;

    // Signature verification (using API key as shared secret — update to HMAC when Pluto docs confirm)
    $api_key      = get_option( 'misaha_pluto_api_key', MISAHA_PLUTO_KEY );
    $received_sig = isset( $_SERVER['HTTP_X_PLUTO_SIGNATURE'] ) ? sanitize_text_field( $_SERVER['HTTP_X_PLUTO_SIGNATURE'] ) : '';
    if ( $received_sig ) {
        $expected_sig = hash_hmac( 'sha256', $raw_payload, $api_key );
        if ( ! hash_equals( $expected_sig, $received_sig ) ) {
            // Signature mismatch — silently abort
            return;
        }
    }

    $pluto_id     = isset( $data['id'] )     ? sanitize_text_field( $data['id'] )     : '';
    $pluto_status = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
    $meta         = isset( $data['metadata'] ) ? $data['metadata'] : array();

    $reference_type = isset( $meta['booking_type'] ) ? sanitize_text_field( $meta['booking_type'] ) : '';
    $reference_id   = isset( $meta['booking_id'] )   ? absint( $meta['booking_id'] )                : 0;

    if ( ! $reference_type || ! $reference_id ) return;

    // Map Pluto status → internal status
    $paid   = in_array( $pluto_status, array( 'succeeded', 'paid', 'completed' ), true );
    $failed = in_array( $pluto_status, array( 'failed', 'declined', 'expired' ), true );

    if ( $reference_type === 'booking' ) {
        $wpdb->update(
            $wpdb->prefix . 'misaha_bookings',
            array(
                'payment_status' => $paid ? 'paid' : ( $failed ? 'failed' : 'pending' ),
                'payment_id'     => $pluto_id,
                'status'         => $paid ? 'confirmed' : 'pending',
            ),
            array( 'id' => $reference_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    } elseif ( $reference_type === 'pass' ) {
        $wpdb->update(
            $wpdb->prefix . 'misaha_passes',
            array(
                'payment_status' => $paid ? 'paid' : ( $failed ? 'failed' : 'pending' ),
                'payment_id'     => $pluto_id,
                'status'         => $paid ? 'active' : ( $failed ? 'cancelled' : 'active' ),
            ),
            array( 'id' => $reference_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    // Update payments log
    $wpdb->update(
        $wpdb->prefix . 'misaha_payments',
        array(
            'pluto_status'     => $pluto_status,
            'pluto_payment_id' => $pluto_id,
        ),
        array( 'reference_type' => $reference_type, 'reference_id' => $reference_id ),
        array( '%s', '%s' ),
        array( '%s', '%d' )
    );

    // Optional: send confirmation email
    if ( $paid ) {
        misaha_send_confirmation_email( $reference_type, $reference_id );
    }
}

/**
 * Send a booking/pass confirmation email to the user.
 */
function misaha_send_confirmation_email( $type, $id ) {
    global $wpdb;

    if ( $type === 'booking' ) {
        $record = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}misaha_bookings WHERE id = %d", $id )
        );
    } else {
        $record = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}misaha_passes WHERE id = %d", $id )
        );
    }

    if ( ! $record ) return;

    $user  = get_userdata( $record->user_id );
    if ( ! $user ) return;

    $subject = 'Misaha Booking Confirmed – #' . $id;
    $message = "Dear {$user->display_name},\n\n";
    $message .= "Your " . ucfirst( $type ) . " (ID: #{$id}) has been confirmed.\n";
    $message .= "Amount Paid: " . get_option( 'misaha_currency', 'LYD' ) . ' ' . number_format( (float) $record->final_price, 2 ) . "\n\n";
    $message .= "Thank you for choosing Misaha!\n";

    wp_mail( $user->user_email, $subject, $message );
}