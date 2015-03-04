<?php
/*
Plugin Name: Easy Digital Downloads - Braintree
Plugin URI: http://www.designwritebuild.com/edd/braintree/
Description: Accept credit card payments in EDD using your Braintree merchant account.
Author: DesignWriteBuild and Pippin Williamson
Author URI: https://easydigitaldownloads.com
Version: 1.0.2

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if( class_exists( 'EDD_License' ) && is_admin() ) {
	$edd_bt_license = new EDD_License( __FILE__, 'Braintree', '1.0.2', 'Pippin Williamson', 'braintree_license_key' );
}

function edd_braintree_process_payment( $purchase_data ) {

	global $edd_options;
	
	require_once 'braintree/lib/Braintree.php';

	// check the posted cc deails
	$cc = edd_braintree_check_cc_details( $purchase_data );

	// fcheck for errors before we continue to processing
	if( ! edd_get_errors() ) {
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
			'status' 		=> 'pending'
		);
		$payment = edd_insert_payment( $payment );

		$transaction = array(
			'orderId'		=> $payment,
			'amount' 		=> $purchase_data['price'],
			'merchantAccountId'	=> trim( edd_get_option( 'braintree_merchantAccountId', '' ) ),
			'creditCard'	=> array(
				'cardholderName'	=> $cc['card_name'],
				'number'			=> $cc['card_number'],
				'expirationMonth'	=> $cc['card_exp_month'],
				'expirationYear'	=> $cc['card_exp_year'],
				'cvv'				=> $cc['card_cvc']
			),
			'customer' 		=> array(
				'firstName' 		=> $purchase_data['user_info']['first_name'],
				'lastName' 			=> $purchase_data['user_info']['last_name'],
				'email' 			=> $purchase_data['user_email']
			),
			'billing' 		=> array(
				'streetAddress'		=> $purchase_data['card_info']['card_address'],
				'extendedAddress'	=> $purchase_data['card_info']['card_address_2'],
				'locality' 			=> $purchase_data['card_info']['card_city'],
				'region' 			=> $purchase_data['card_info']['card_state'],
				'postalCode' 		=> $purchase_data['card_info']['card_zip'],
				'countryCodeAlpha2'	=> $purchase_data['card_info']['card_country']
			),
			'options'	=> array()
		);

		if( edd_get_option( 'braintree_submitForSettlement' ) ) {
			$transaction['options']['submitForSettlement'] = true;
		}

		if( edd_get_option( 'braintree_storeInVaultOnSuccess' ) ) {
			$transaction['options']['storeInVaultOnSuccess'] = true;
		}

		if( edd_is_test_mode() ) {
			Braintree_Configuration::environment('sandbox');
		} else {
			Braintree_Configuration::environment('production');
		}
			
		Braintree_Configuration::merchantId( trim( edd_get_option( 'braintree_merchantId' ) ) );
		Braintree_Configuration::publicKey( trim( edd_get_option( 'braintree_publicKey' ) ) );
		Braintree_Configuration::privateKey( trim( edd_get_option( 'braintree_privateKey' ) ) );

		$result = Braintree_Transaction::sale( $transaction );

		if( $result->success ) {
			
			// WINNING
			if( edd_get_option( 'braintree_storeInVaultOnSuccess' ) && isset( $purchase_data['user_info']['id'] ) && $purchase_data['user_info']['id'] > 0 ) {
				$tokens = get_user_meta( $purchase_data['user_info']['id'], 'edd_braintree_cc_tokens', true );
				if( empty( $tokens ) ) {
					$tokens = array();
				}
				$tokens[ $result->transaction->customerDetails->id ][ $result->transaction->creditCardDetails->token ] = $result->transaction->creditCardDetails->token;
				update_user_meta( $purchase_data['user_info']['id'], 'edd_braintree_cc_tokens', $tokens );
			}

			edd_empty_cart();
			edd_update_payment_status( $payment, 'complete' );

			edd_send_to_success_page();
		
		} else { // LOSING

			switch( $result->transaction->status ) {
				case 'processor_declined':
					$reason = $result->transaction->processorResponseText . ' (' . $result->transaction->processorResponseCode . ')';
				break;
				case 'gateway_rejected':
					$reason = sprintf( __( 'Transaction Failed (%s)', 'edd_braintree'), $result->transaction->gatewayRejectionReason );
				break;
				default:
					$reason = $result->errors->deepAll();
					if( is_object( $reason ) ) {
						$reason = sprintf( __( 'Transaction Failed (%s)', 'edd_braintree'), $reason->code . ' : ' . $reason->message );
					} else {
						$reason = sprintf( __( 'Transaction Failed (%s)', 'edd_braintree'), $result->errors->deepAll() );
					}
					break;
			}
			edd_set_error( 'braintree_decline' , sprintf( __( 'Transaction Declined: %s', 'edd_braintree' ), $reason ) );
			edd_record_gateway_error( __( 'Transaction Failed', 'edd_braintree' ), sprintf( __( 'Transaction status did not return complete. POST Data: %s', 'edd_braintree' ), json_encode( $_GET ), $_GET['orderid'] ) );
			edd_send_back_to_checkout( '?payment-mode=braintree' );
			$fail = true;
		}

	} else {
		edd_send_back_to_checkout( '?payment-mode=braintree' );
		$fail = true;
	}
	
}
add_action( 'edd_gateway_braintree', 'edd_braintree_process_payment' );

function edd_braintree_check_cc_details( $purchase_data ) {
	$keys = array(
		'card_number' => __( 'credit card number', 'edd_braintree' ),
		'card_exp_month' => __( 'expiration month', 'edd_braintree' ),
		'card_exp_year' => __( 'expiration year', 'edd_braintree' ),
		'card_name' => __( 'card holder name', 'edd_braintree' ),
		'card_cvc' => __( 'security code', 'edd_braintree' ),
	);

	$cc_details = array();

	foreach( $keys as $key => $desc ) {
		if( !isset( $_POST[ $key ] ) || empty( $_POST[ $key ] ) ) {
			edd_set_error( 'bad_' . $key , sprintf( __('You must enter a valid %s.', 'edd_braintree' ), $desc ) );
		} else {
			$data = esc_textarea( trim( $_POST[ $key ] ) );
			switch( $key ) {
				case 'card_exp_month':
					$data = str_pad( $data, 2, 0, STR_PAD_LEFT);
					break;
				case 'card_exp_year':
					if( strlen( $data ) > 2 ) 
						$data = substr( $data, -2);
					break;
			}
			$cc_details[ $key ] = $data;
			
		}
	}
	return $cc_details;
}

/**
 * Register Settings
 **/

function edd_braintree_add_settings( $settings ) {
 
	$gateway_settings = array(
		array(
			'id' => 'braintree_settings',
			'name' => '<strong>' . __( 'Braintree Settings', 'edd_braintree' ) . '</strong>',
			'desc' => __( 'Configure Braintree', 'edd_braintree' ),
			'type' => 'header'
		),
		array(
			'id' => 'braintree_merchantId',
			'name' => __( 'Merchant ID', 'edd_braintree' ),
			'desc' => __( 'Enter your unique merchant ID (found on the portal under API Keys when you first login).', 'edd_braintree' ),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'braintree_merchantAccountId',
			'name' => __( 'Merchant Account ID', 'edd_braintree' ),
			'desc' => __( 'Enter your unique merchant account ID (found under Account > Processing > Merchant Accounts).', 'edd_braintree' ),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'braintree_publicKey',
			'name' => __( 'Public Key', 'edd_braintree' ),
			'desc' => __( 'Enter your public key (found on the portal under API Keys when you first login).', 'edd_braintree' ),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'braintree_privateKey',
			'name' => __( 'Private Key', 'edd_braintree' ),
			'desc' => __( 'Enter your private key (found on the portal under API Keys when you first login).', 'edd_braintree' ),
			'type' => 'password',
			'size' => 'regular'
		),
		array(
			'id' => 'braintree_submitForSettlement',
			'name' => __( 'Submit for Settlement', 'edd_braintree' ),
			'desc' => __( 'Enable this option if you would like to immediately submit all transactions for settlment.', 'edd_braintree' ),
			'type' => 'checkbox'
		),
		array(
			'id' => 'braintree_storeInVaultOnSuccess',
			'name' => __( 'Store In Vault on Success', 'edd_braintree' ),
			'desc' => __( 'Enable this option if you would like to store the customers information in the vault on a successful purchase.', 'edd_braintree' ),
			'type' => 'checkbox'
		),
	);

	return array_merge( $settings, $gateway_settings );	
}
add_filter( 'edd_settings_gateways', 'edd_braintree_add_settings' );

function edd_braintree_textdomain() {
	// Set filter for plugin's languages directory
	$edd_lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
	$edd_lang_dir = apply_filters( 'edd_braintree_languages_directory', $edd_lang_dir );

	// Load the translations
	load_plugin_textdomain( 'edd_braintree', false, $edd_lang_dir );
}
add_action('init', 'edd_braintree_textdomain');

function edd_braintree_register_gateway( $gateways ) {
	$gateways['braintree'] = array(
		'admin_label' => 'Braintree',
		'checkout_label' => __( 'Credit Card', 'edd_braintree' )
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'edd_braintree_register_gateway' );