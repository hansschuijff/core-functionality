<?php
/**
 * Admin Toolbar Flexbox Wrapping Fix Feature.
 *
 * Admin functions to change the styles of WordPress Admin bar
 * so that it doesn't wrap when it gets crowded.
 *
 * @package DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\StylesFixer
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\StylesFixer;

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class StylesFixer
 *
 * Prevents the native WordPress admin bar from wrapping on larger viewports.
 *
 * @since 1.0.0
 */
class StylesFixer extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'fix_admin_bar_styles',
			name:          __( 'Style Fixes', 'core-functionality' ),
			description:   __( 'The Admin Toolbar Styles Fixer Feature solves some styling issues in the admin toolbar.', 'core-functionality' ),
		);
	}

	/**
	 * Launches the operational execution lifecycle for this micro-feature.
	 *
	 * @return void
	 */
	public function launch(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fix_styles' ), 9999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_fix_styles' ), 9999 );
	}

	/**
	 * Enqueues the flexbox override styles safely if the admin bar is active.
	 *
	 * @return void
	 */
	public function enqueue_fix_styles(): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$url = plugins_url( 'assets/css/styles-fixer.css', __FILE__ );

		wp_enqueue_style(
			'dwp-admin-bar-wrap-fix',
			$url,
			array(),
			$this->plugin->get_data( 'version' )
		);
	}
}
