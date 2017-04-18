<?php
/**
 * Setup and Prepare Plugin
**/

/* Migrate Old Settings to New Settings DB Structure */
add_action( 'admin_init', 'edd_braintree_migrate_settings_ids' );

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
		<p><?php printf( __( '<strong>Notice:</strong> Easy Digital Downloads Braintree requires Easy Digital Downloads 2.5 or higher in order to function properly.', 'edd-braintree' ) ); ?></p>
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
		<p><?php printf( __( '<strong>Notice:</strong> Easy Digital Downloads Braintree requires WordPress 4.2 or higher in order to function properly.', 'edd-braintree' ) ); ?></p>
	</div>
	<?php
}












