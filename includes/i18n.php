<?php
/**
 * Misaha Booking System — Internationalization (i18n)
 *
 * Provides a lightweight PHP-array-based translation system.
 * Works alongside WordPress's get_locale() to detect the active language.
 * Arabic is the primary supported translation. Extend by adding new language files.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load the translation strings for the current locale.
 *
 * @return array   The translations array (english => translated)
 */
function misaha_get_translations() {
    static $translations = null;

    if ( $translations !== null ) {
        return $translations;
    }

    $locale = determine_locale();

    // Check for Arabic (ar, ar_AR, ar_SA, ar_LY, ar_EG, etc.)
    if ( strpos( $locale, 'ar' ) === 0 ) {
        $file = MISAHA_DIR . 'languages/misaha-booking-ar.php';
        if ( file_exists( $file ) ) {
            $translations = include $file;
            if ( is_array( $translations ) ) {
                return $translations;
            }
        }
    }

    // Fallback — look for a matching locale file
    $file = MISAHA_DIR . 'languages/misaha-booking-' . $locale . '.php';
    if ( file_exists( $file ) ) {
        $translations = include $file;
        if ( is_array( $translations ) ) {
            return $translations;
        }
    }

    $translations = array(); // empty = English fallback
    return $translations;
}

/**
 * Translate a string.
 * Returns the translated version if available, otherwise the original string.
 *
 * @param string $text  English string to translate
 * @return string       Translated string
 */
function misaha__( $text ) {
    $t = misaha_get_translations();
    return isset( $t[ $text ] ) ? $t[ $text ] : $text;
}

/**
 * Echo a translated string.
 *
 * @param string $text  English string to translate
 */
function misaha_e( $text ) {
    echo misaha__( $text );
}

/**
 * Get escaped translated string (for HTML attributes).
 *
 * @param string $text
 * @return string
 */
function misaha_esc_attr( $text ) {
    return esc_attr( misaha__( $text ) );
}

/**
 * Get escaped translated string (for HTML output).
 *
 * @param string $text
 * @return string
 */
function misaha_esc_html( $text ) {
    return esc_html( misaha__( $text ) );
}

/**
 * Check if the current locale is RTL.
 *
 * @return bool
 */
function misaha_is_rtl() {
    if ( function_exists( 'is_rtl' ) ) {
        return is_rtl();
    }
    $locale = determine_locale();
    return strpos( $locale, 'ar' ) === 0
        || strpos( $locale, 'he' ) === 0
        || strpos( $locale, 'fa' ) === 0
        || strpos( $locale, 'ur' ) === 0;
}

/**
 * Build the JS translations object for wp_localize_script.
 *
 * @return array
 */
function misaha_js_translations() {
    $keys = array(
        'Please select a hall first.',
        'Please pick a date.',
        'No slots available for this date.',
        'Failed to load slots. Please try again.',
        'Please enter a discount code.',
        'Checking…',
        'Invalid code.',
        'Network error.',
        'Processing…',
        'Network error. Please try again.',
        'Booked!',
        'Done!',
        'created! Redirecting to payment…',
        'created! Total:',
        'Our team will contact you for payment.',
        'Please select a start date.',
        'Valid:',
        'No seats available for this hall.',
        'Failed to load seats.',
        'click a seat to select it',
        'Enter a discount code.',
        'Booking Summary',
        'Pass Summary',
        'Hall',
        'Seat',
        'Date',
        'Time Slot',
        'Duration',
        '1 hour',
        'Pass Type',
        'Start Date',
        'End Date',
        'Base Price',
        'Pass Price',
        'Discount',
        'Total',
        'Apply',
        'Proceed to Payment',
        'Continue',
        'Back',
        'Available',
        'Booked',
        'Day Pass',
        'Weekly Pass',
        'Monthly Pass',
        'Booking',
        'Pass',
    );

    $out = array();
    foreach ( $keys as $key ) {
        $out[ $key ] = misaha__( $key );
    }
    return $out;
}
