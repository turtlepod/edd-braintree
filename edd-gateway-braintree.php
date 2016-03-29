<?php
/*
Plugin Name: Easy Digital Downloads - Braintree
Plugin URI: https://easydigitaldownloads.com/extensions/braintree-gateway/
Description: Accept credit card payments in EDD using your Braintree merchant account.
Author: Pippin Williamson
Author URI: https://easydigitaldownloads.com
Version: 1.1.4
Text Domain: edd-braintree
Domain Path: languages

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

if ( class_exists( 'EDD_License' ) && is_admin() ) {
	$edd_bt_license = new EDD_License( __FILE__, 'Braintree', '1.1.4', 'Pippin Williamson', 'braintree_license_key' );
}

function edd_braintree_process_payment( $purchase_data ) {

	global $edd_options;

	require_once plugin_dir_path( __FILE__ ) . 'braintree/lib/Braintree.php';

	// check the posted cc deails
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
add_action( 'edd_gateway_braintree', 'edd_braintree_process_payment' );

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

/**
 * Register Settings
 **/

function edd_braintree_add_settings( $settings ) {

	$gateway_settings = array(
		array(
			'id' => 'braintree_settings',
			'name' => '<strong>' . __( 'Braintree Settings', 'edd-braintree' ) . '</strong>',
			'desc' => __( 'Configure Braintree', 'edd-braintree' ),
			'type' => 'header',
		),
		array(
			'id' => 'braintree_merchant_id',
			'name' => __( 'Merchant ID', 'edd-braintree' ),
			'desc' => __( 'Enter your unique merchant ID (found on the portal under API Keys when you first login).', 'edd-braintree' ),
			'type' => 'text',
			'size' => 'regular',
		),
		array(
			'id' => 'braintree_merchant_account_id',
			'name' => __( 'Merchant Account ID', 'edd-braintree' ),
			'desc' => __( 'Enter your unique merchant account ID (found under Account > Processing > Merchant Accounts).', 'edd-braintree' ),
			'type' => 'text',
			'size' => 'regular',
		),
		array(
			'id' => 'braintree_public_key',
			'name' => __( 'Public Key', 'edd-braintree' ),
			'desc' => __( 'Enter your public key (found on the portal under API Keys when you first login).', 'edd-braintree' ),
			'type' => 'text',
			'size' => 'regular',
		),
		array(
			'id' => 'braintree_private_key',
			'name' => __( 'Private Key', 'edd-braintree' ),
			'desc' => __( 'Enter your private key (found on the portal under API Keys when you first login).', 'edd-braintree' ),
			'type' => 'password',
			'size' => 'regular',
		),
		array(
			'id' => 'braintree_submit_for_settlement',
			'name' => __( 'Submit for Settlement', 'edd-braintree' ),
			'desc' => __( 'Enable this option if you would like to immediately submit all transactions for settlment.', 'edd-braintree' ),
			'type' => 'checkbox',
		),
		array(
			'id' => 'braintree_store_in_vault_on_success',
			'name' => __( 'Store In Vault on Success', 'edd-braintree' ),
			'desc' => __( 'Enable this option if you would like to store the customers information in the vault on a successful purchase.', 'edd-braintree' ),
			'type' => 'checkbox',
		),
	);

	if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
		$gateway_settings = array( 'braintree' => $gateway_settings );
	}

	return array_merge( $settings, $gateway_settings );
}
add_filter( 'edd_settings_gateways', 'edd_braintree_add_settings' );

function edd_braintree_textdomain() {
	// Set filter for plugin's languages directory
	$edd_lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
	$edd_lang_dir = apply_filters( 'edd_braintree_languages_directory', $edd_lang_dir );

	// Load the translations
	load_plugin_textdomain( 'edd-braintree', false, $edd_lang_dir );
}
add_action( 'init', 'edd_braintree_textdomain' );

function edd_braintree_register_gateway( $gateways ) {
	$gateways['braintree'] = array(
		'admin_label' => 'Braintree',
		'checkout_label' => __( 'Credit Card', 'edd-braintree' )
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'edd_braintree_register_gateway' );

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
add_filter( 'edd_payment_details_transaction_id-braintree', 'edd_braintree_link_transaction_id', 10, 2 );

/**
 * Adds the settings section in the Gateways tab in EDD 2.5+
 */
function edd_braintree_add_settings_section( $sections ) {
	$sections['braintree'] = __( 'Braintree', 'edd-braintree' );
	return $sections;
}
add_filter( 'edd_settings_sections_gateways', 'edd_braintree_add_settings_section' );

/**
 * Migrates old settings values to the new keys
 */
function edd_braintree_migrate_settings_ids() {
	
	if ( !edd_braintree_required_items_exist() ){
		return false;	
	}
	
	if ( edd_get_option( 'edd_braintree_ids_migrated', false ) ) {
		return;
	}

	$ids = array(
		'braintree_merchantId'            => 'braintree_merchant_id',
		'braintree_merchantAccountId'     => 'braintree_merchant_account_id',
		'braintree_publicKey'             => 'braintree_public_key',
		'braintree_privateKey'            => 'braintree_private_key',
		'braintree_submitForSettlement'   => 'braintree_submit_for_settlement',
		'braintree_storeInVaultOnSuccess' => 'braintree_store_in_vault_on_success'
	);

	foreach ( $ids as $old_key => $new_key ) {

		$old_value = edd_get_option( $old_key, false );

		if ( ! empty( $old_value ) ) {
			if ( edd_update_option( $new_key, $old_value ) ) {
				edd_delete_option( $old_key );
			}
		}
	}

	edd_update_option( 'edd_braintree_ids_migrated', true );
}
add_action( 'admin_init', 'edd_braintree_migrate_settings_ids' );

/**
 * Braintree check for required plugins and versions.
 *
 * This function is used to check if all required plugins are active and if their versions are properly up to date. If not, it will return false and add a notice to admin_notices.
 *
 * @since 1.1.4
 * 
 * @return bool
 */
function edd_braintree_required_items_exist(){
		
	global $wp_version;
	
	// If the WordPress site doesn't meet the correct EDD and WP version requirements, deactivate and show notice.
	if ( version_compare( $wp_version, '4.2', '<' ) ) {
		add_action( 'admin_notices', 'edd_braintree_wp_notice' );
		return false;
	} else if ( !class_exists( 'Easy_Digital_Downloads' ) || version_compare( EDD_VERSION, '2.4', '<' ) ) {
		add_action( 'admin_notices', 'edd_braintree_edd_notice' );
		return false;
	}
	
	return true;
}

/**
 * Braintree minimum EDD version notice
 *
 * This function is used to throw an admin notice when the WordPress install
 * does not meet Braintree's minimum EDD version requirements.
 *
 * @since 1.1.4
 * @access public
 * 
 * @return void
 */
function edd_braintree_edd_notice() { ?>
	<div class="updated">
		<p><?php printf( __( '<strong>Notice:</strong> Easy Digital Downloads Braintree requires Easy Digital Downloads 2.5 or higher in order to function properly.', 'edd_fes' ) ); ?></p>
	</div>
	<?php
}

/**
 * Braintree minimum WP version notice
 *
 * This function is used to throw an admin notice when the WordPress install
 * does not meet Braintree's minimum WP version requirements.
 *
 * @since 1.1.4
 * @access public
 * 
 * @return void
 */
function edd_braintree_wp_notice() { ?>
	<div class="updated">
		<p><?php printf( __( '<strong>Notice:</strong> Easy Digital Downloads Braintree requires WordPress 4.2 or higher in order to function properly.', 'edd_fes' ) ); ?></p>
	</div>
	<?php
}
