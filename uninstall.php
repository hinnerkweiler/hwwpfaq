<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Drops the FAQ table and removes all trace of the plugin from the database.
 *
 * This file must NOT be required by the main plugin file; WP loads it directly.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$table = $wpdb->prefix . 'hwwpfaq_items';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
