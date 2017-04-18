<?php
/**
 * Register & Process Braintree Gateway
**/

/* REGISTER GATEWAY
------------------------------------------ */

/* Add Payment Gateway */
add_filter( 'edd_payment_gateways', 'edd_braintree_register_gateway' );

/**
 * Register Gateway
 * 
 * @param array $gateways
 */
function edd_braintree_register_gateway( $gateways ) {
	$gateways['braintree'] = array(
		'admin_label'    => 'Braintree',
		'checkout_label' => __( 'Credit Card', 'edd-braintree' )
	);
	return $gateways;
}


/* PROCESS IT
------------------------------------------ */


/* Gateway Processing */
add_action( 'edd_gateway_braintree', 'edd_braintree_process_payment' );

/**
 * Process Payment from Braintree
 * This function is hooked to "edd_gateway_*"
 * 
 * This is the example purchase data
 * 
 * $purchase_data = array(
 *   'downloads' => array of download IDs,
 *   'price' => total price of cart contents,
 *   'purchase_key' =>  // Random key
 *   'user_email' => $user_email,
 *   'date' => date('Y-m-d H:i:s'),
 *   'user_id' => $user_id,
 *   'post_data' => $_POST,
 *   'user_info' => array of user's information and used discount code
 *   'cart_details' => array of cart details,
 * );
 * 
 * @param array $purchase_data
 * 
 * @uses edd_braintree_check_cc_details()
 */
function edd_braintree_process_payment( $purchase_data ) {

	/* Load Braintree PHP library */
	require_once EDDBTGW_PATH . 'braintree/lib/Braintree.php';

	/* Check CC Details */
	$cc = edd_braintree_check_cc_details( $purchase_data );

	// fcheck for errors before we continue to processing
	if ( ! edd_get_errors() ) {
		$purchase_summary = edd_get_purchase_summary( $purchase_data );
		$payment = array(
			'price' 		=> $purchase_data['price'],
			'date' 			=> $purchase_data['date'],
			'user_email' 	=> $purchase_data['user_email'],
			'purchase_key' 	=> $purchase_data['purchase_key'],
			'currency' 		=> edd_get_currency(),
			'downloads' 	=> $purchase_data['downloads'],
			'cart_details' 	=> $purchase_data['cart_details'],
			'user_info' 	=> $purchase_data['user_info'],
			'status' 		=> 'pending',
		);
		$payment = edd_insert_payment( $payment );

		$transaction = array(
			'orderId'		=> $payment,
			'amount' 		=> $purchase_data['price'],
			'merchantAccountId'	=> trim( edd_get_option( 'braintree_merchant_account_id', '' ) ),
			'creditCard'	=> array(
				'cardholderName'	=> $cc['card_name'],
				'number'			=> $cc['card_number'],
				'expirationMonth'	=> $cc['card_exp_month'],
				'expirationYear'	=> $cc['card_exp_year'],
				'cvv'				=> $cc['card_cvc'],
			),
			'customer' 		=> array(
				'firstName' 		=> $purchase_data['user_info']['first_name'],
				'lastName' 			=> $purchase_data['user_info']['last_name'],
				'email' 			=> $purchase_data['user_email'],
			),
			'billing' 		=> array(
				'streetAddress'		=> $purchase_data['card_info']['card_address'],
				'extendedAddress'	=> $purchase_data['card_info']['card_address_2'],
				'locality' 			=> $purchase_data['card_info']['card_city'],
				'region' 			=> $purchase_data['card_info']['card_state'],
				'postalCode' 		=> $purchase_data['card_info']['card_zip'],
				'countryCodeAlpha2'	=> $purchase_data['card_info']['card_country'],
			),
			'options'	=> array()
		);

		if ( edd_get_option( 'braintree_submit_for_settlement' ) ) {
			$transaction['options']['submitForSettlement'] = true;
		}

		if ( edd_get_option( 'braintree_store_in_vault_on_success' ) ) {
			$transaction['options']['storeInVaultOnSuccess'] = true;
		}

		if ( edd_is_test_mode() ) {
			Braintree_Configuration::environment( 'sandbox' );
		} else {
			Braintree_Configuration::environment( 'production' );
		}

		Braintree_Configuration::merchantId( trim( edd_get_option( 'braintree_merchant_id' ) ) );
		Braintree_Configuration::publicKey( trim( edd_get_option( 'braintree_public_key' ) ) );
		Braintree_Configuration::privateKey( trim( edd_get_option( 'braintree_private_key' ) ) );

		$result = Braintree_Transaction::sale( $transaction );

		if ( ! empty( $result->success ) ) {

			// WINNING
			if ( edd_get_option( 'braintree_store_in_vault_on_success' ) && isset( $purchase_data['user_info']['id'] ) && $purchase_data['user_info']['id'] > 0 ) {
				$tokens = get_user_meta( $purchase_data['user_info']['id'], 'edd_braintree_cc_tokens', true );
				if ( empty( $tokens ) ) {
					$tokens = array();
				}
				$tokens[ $result->transaction->customerDetails->id ][ $result->transaction->creditCardDetails->token ] = $result->transaction->creditCardDetails->token;
				update_user_meta( $purchase_data['user_info']['id'], 'edd_braintree_cc_tokens', $tokens );
			}

			edd_empty_cart();
			edd_update_payment_status( $payment, 'complete' );
			if ( function_exists( 'edd_set_payment_transaction_id' ) ) {
				edd_set_payment_transaction_id( $payment, $result->transaction->_attributes['id'] );
			}
			edd_send_to_success_page();

		} else {

			// LOSING

			if( empty( $result->transaction->status ) || empty( $result->transaction ) ) {

				$error = new Braintree_Error_Validation( $result );

				edd_set_error( 'braintree_decline' , sprintf( __( 'Transaction Failed: %s', 'edd-braintree' ), $error->__attributes['message'] ) );
				edd_send_back_to_checkout( '?payment-mode=braintree' );

			}

			switch ( $result->transaction->status ) {
				case 'processor_declined':
					$reason = $result->transaction->processorResponseText . ' (' . $result->transaction->processorResponseCode . ')';
				break;
				case 'gateway_rejected':
					$reason = sprintf( __( 'Transaction Failed (%s)', 'edd-braintree' ), $result->transaction->gatewayRejectionReason );
				break;
				default:
					$reason = $result->errors->deepAll();
					if( is_object( $reason ) ) {
						$reason = sprintf( __( 'Transaction Failed (%s)', 'edd-braintree' ), $reason->code . ' : ' . $reason->message );
					} else {
						$reason = sprintf( __( 'Transaction Failed (%s)', 'edd-braintree' ), $result->errors->deepAll() );
					}
					break;
			}

			edd_set_error( 'braintree_decline' , sprintf( __( 'Transaction Declined: %s', 'edd-braintree' ), $reason ) );
			edd_send_back_to_checkout( '?payment-mode=braintree' );
		}

	} else {
		edd_send_back_to_checkout( '?payment-mode=braintree' );
	}
}








