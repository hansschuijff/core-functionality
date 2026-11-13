<?php
/**
 * Plugins that only work with the Genesis Framework (module Themes, feature GenesisOnlyPlugins).
 *
 * Switched off after a switch to a theme not built on Genesis, and reported when they are active
 * without Genesis. Checked against the plugins' source on 13 November 2026. Left out because they
 * work with any theme: Genesis Blocks, Genesis Blocks Pro (folder genesis-page-builder), Genesis
 * Custom Blocks (Pro) and Genesis Simple FAQ.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.2.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin basenames (folder/file.php).
return array(
	'genesis-dambuster/main.php',
	'genesis-featured-page-advanced/genesis-featured-page-advanced.php',
	'genesis-connect-woocommerce/genesis-connect-woocommerce.php',
	'genesis-connect-sensei-lms/genesis-connect-sensei-lms.php',
	'genesis-enews-extended/plugin.php',
	'genesis-simple-sidebars/plugin.php',
	'display-featured-image-genesis/display-featured-image-genesis.php',
	'featured-videos-for-genesis/genesis-featured-video.php',
	'genesis-admin-bar-plus/genesis-admin-bar-plus.php',
);
