<?php
/**
 * Settings
**/

/* Add Settings Section */
add_filter( 'edd_settings_sections_gateways', 'edd_braintree_add_settings_section' );

/**
 * Adds the settings section in the Gateways tab in EDD 2.5+
 */
function edd_braintree_add_settings_section( $sections ) {
	$sections['braintree'] = __( 'Braintree', 'edd-braintree' );
	return $sections;
}


/* Gateway Settings Section */
add_filter( 'edd_settings_gateways', 'edd_braintree_add_settings' );

/**
 * Register Settings
 *
 * @param array $settings
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



