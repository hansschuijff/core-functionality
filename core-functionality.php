<?php
/**
 * Plugin Name:       Core Functionality
 * Description:       Central Microkernel framework managing standalone modules and contextual guards.
 * Version:           1.2.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Hans Schuijff
 * Author URI:        https://dewitteprins.nl
 * License:           GPL-2.0-only
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       core-functionality
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: https://github.com/hansschuijff/core-functionality
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package DeWittePrins\CoreFunctionality
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality;

use function add_action;
use function current_user_can;
use function esc_html__;
use function load_plugin_textdomain;
use function plugin_basename;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Onwrikbare poortwachter.
}

if ( ! defined( 'DWP_CF_PLUGIN_DIR' ) ) {
	define( 'DWP_CF_PLUGIN_DIR', __DIR__ );
}

/**
 * Prints the notice that the plugin cannot start because its Composer autoloader is missing.
 *
 * Defined here and not in a class: without the autoloader no class of the plugin can be loaded.
 *
 * @since 1.0.1
 *
 * @return void
 */
function print_missing_autoloader_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// The Plugin class normally loads the translations, but it does not run now.
	load_plugin_textdomain( 'core-functionality', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$title = esc_html__( 'Core Functionality cannot start: the vendor folder with the autoloader is missing.', 'core-functionality' );
	$text  = esc_html__( 'This happens when the source code was installed instead of the release zip. Upload the zip attached to the release on GitHub (core-functionality-x.y.z.zip).', 'core-functionality' );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both texts are escaped above.
	echo <<<HTML
		<div class="notice notice-error">
			<p><strong>{$title}</strong></p>
			<p>{$text}</p>
		</div>
		HTML;
}

// 1. Laat Composer de complete klasse-distributie (PSR-4) beheren. Without the autoloader (a copy of
// the source code instead of the release zip) the plugin stops here with a notice, not a fatal error.
if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\print_missing_autoloader_notice' );
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

// 2. Start de verkeersleider.
$dwp_cf_plugin = new \DeWittePrins\CoreFunctionality\Plugin( __FILE__ );

// 3. Temporary: let the legacy plugin step aside for the features that took it over.
( new \DeWittePrins\CoreFunctionality\Migration\LegacyHandover( $dwp_cf_plugin ) )->register();
