<?php
/**
 * Plugin Name:       HW Wordpress FAQ
 * Description:       Stores and displays FAQ elements with scheduled publication, category filtering, and a Gutenberg block.
 * Version:           1.0.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Text Domain:       hwwpfaq
 * Author:            Hinnerk Weiler
 * Author URI:        https://www.aufmboot.com
 * License:           MIT
 */

defined( 'ABSPATH' ) || exit;

define( 'HWWPFAQ_VERSION', '1.0.0' );
define( 'HWWPFAQ_FILE',    __FILE__ );
define( 'HWWPFAQ_PATH',    plugin_dir_path( __FILE__ ) );
define( 'HWWPFAQ_URL',     plugin_dir_url( __FILE__ ) );
define( 'HWWPFAQ_TABLE',   'hwwpfaq_items' );

require_once HWWPFAQ_PATH . 'includes/class-hwwpfaq-db.php';
require_once HWWPFAQ_PATH . 'admin/class-hwwpfaq-admin.php';

register_activation_hook( HWWPFAQ_FILE, array( 'HWWPFAQ_DB', 'create_table' ) );

if ( is_admin() ) {
	new HWWPFAQ_Admin();
}

add_action( 'init', 'hwwpfaq_register_block' );

/**
 * Registers the Gutenberg block from block.json.
 * WP 6.1+ handles editorScript, style, and render file automatically.
 */
function hwwpfaq_register_block() {
	register_block_type( HWWPFAQ_PATH . 'block' );
}
