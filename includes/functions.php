<?php
/**
 * Functions
**/

/**
 * Check CC Details
 * Used in Before processing gateway
 * 
 * @param array $purchase_data
 */
function edd_braintree_check_cc_details( $purchase_data ) {
	$keys = array(
		'card_number' => __( 'credit card number', 'edd-braintree' ),
		'card_exp_month' => __( 'expiration month', 'edd-braintree' ),
		'card_exp_year' => __( 'expiration year', 'edd-braintree' ),
		'card_name' => __( 'card holder name', 'edd-braintree' ),
		'card_cvc' => __( 'security code', 'edd-braintree' ),
	);

	$cc_details = array();

	foreach ( $keys as $key => $desc ) {
		if ( ! isset( $_POST[ $key ] ) || empty( $_POST[ $key ] ) ) {
			edd_set_error( 'bad_' . $key , sprintf( __( 'You must enter a valid %s.', 'edd-braintree' ), $desc ) );
		} else {
			$data = esc_textarea( trim( $_POST[ $key ] ) );
			switch( $key ) {
				case 'card_exp_month':
					$data = str_pad( $data, 2, 0, STR_PAD_LEFT );
					break;
				case 'card_exp_year':
					if( strlen( $data ) > 2 )
						$data = substr( $data, -2 );
					break;
			}
			$cc_details[ $key ] = $data;

		}
	}
	return $cc_details;
}




