<?php
/**
 * Payment
 * After all completed ?
**/

/* Payment Details */
add_filter( 'edd_payment_details_transaction_id-braintree', 'edd_braintree_link_transaction_id', 10, 2 );

/**
 * Given a transaction ID, generate a link to the Braintree transaction ID details
 *
 * @since  1.1
 * @param  string $transaction_id The Transaction ID
 * @param  int    $payment_id     The payment ID for this transaction
 * @return string                 A link to the PayPal transaction details
 */
function edd_braintree_link_transaction_id( $transaction_id, $payment_id ) {

	if ( $transaction_id == $payment_id ) {
		return $transaction_id;
	}

	$base = 'https://';

	if ( 'test' == get_post_meta( $payment_id, '_edd_payment_mode', true ) ) {
		$base .= 'sandbox.';
	}

	$base .= 'braintreegateway.com/merchants/' . edd_get_option( 'braintree_merchant_id' ) . '/transactions/';
	$transaction_url = '<a href="' . esc_url( $base . $transaction_id ) . '" target="_blank">' . $transaction_id . '</a>';

	return apply_filters( 'edd_braintree_link_payment_details_transaction_id', $transaction_url );

}


