<?php
/**
 * For this code to work, a tel field must be activated in the form (not number)
 * and it must be set to autoinsert run everywhere.
 */

add_action( 'elementor_pro/forms/validation/tel', function( $field, $record, $ajax_handler ) {
    
    if ( empty( $field['value'] ) ) {
        return;
    }

    $number = preg_replace( '/\D/', '', $field['value'] );

    if ( strlen( $number ) === 11 && $number[0] === '1' ) {
        $number = substr( $number, 1 );
    }

    if ( ! preg_match( '/^[2-9]\d{2}[2-9]\d{6}$/', $number ) ) {
        $ajax_handler->add_error(
            $field['id'],
            'Please enter a valid 10-digit US phone number (e.g. 630 555 1234).'
        );
    }
}, 10, 3 );
