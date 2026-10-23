<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes the plugin's
 * own settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array(
	'rsuc-enable',
	'rsuc-enable-homepage',
	'rsuc-secret-word',
	'rsuc-cookie-time',
	'rsuc-html',
	'rsuc-ip',
	'rsuc-review-first-seen',
) as $jalma_option ) {
	delete_option( $jalma_option );
}

foreach ( array(
	'rsuc_review_dismissed',
	'rsuc_review_snooze_until',
) as $jalma_meta ) {
	delete_metadata( 'user', 0, $jalma_meta, '', true );
}
