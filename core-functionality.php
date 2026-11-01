<?php
/**
 * Plugin Name:       Core Functionality OOP
 * Description:       Central Microkernel framework managing standalone modules and contextual guards.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Hans Schuijff
 * Author URI:        https://dewitteprins.nl
 * License:           GPL-2.0-only
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       core-functionality
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: https://github.com/hansschuijff/core-functionality-oop
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package DeWittePrins\CoreFunctionality
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Onwrikbare poortwachter.
}

if ( ! defined( 'DWP_CF_PLUGIN_DIR' ) ) {
	define( 'DWP_CF_PLUGIN_DIR', __DIR__ );
}


// 1. Laat Composer de complete klasse-distributie (PSR-4) beheren
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// 2. Start de verkeersleider.
$dwp_cf_plugin = new \DeWittePrins\CoreFunctionality\Plugin( __FILE__ );

// 3. Temporary: let the legacy plugin step aside for the features that took it over.
( new \DeWittePrins\CoreFunctionality\Migration\LegacyHandover( $dwp_cf_plugin ) )->register();
