<?php
/**
 * Asset manifest for block/editor.js.
 *
 * This file is read by register_block_type() to enqueue the correct
 * WordPress script dependencies without needing @wordpress/scripts.
 */
return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-i18n',
	),
	'version' => '1.0.0',
);
