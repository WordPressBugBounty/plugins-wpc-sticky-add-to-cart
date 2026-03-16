<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WPCSB_LITE' ) ? WPCSB_LITE : WPCSB_FILE, 'wpcsb_activate' );
register_deactivation_hook( defined( 'WPCSB_LITE' ) ? WPCSB_LITE : WPCSB_FILE, 'wpcsb_deactivate' );
add_action( 'admin_init', 'wpcsb_check_version' );

function wpcsb_check_version() {
	if ( ! empty( get_option( 'wpcsb_version' ) ) && ( get_option( 'wpcsb_version' ) < WPCSB_VERSION ) ) {
		wpc_log( 'wpcsb', 'upgraded' );
		update_option( 'wpcsb_version', WPCSB_VERSION, false );
	}
}

function wpcsb_activate() {
	wpc_log( 'wpcsb', 'installed' );
	update_option( 'wpcsb_version', WPCSB_VERSION, false );
}

function wpcsb_deactivate() {
	wpc_log( 'wpcsb', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}