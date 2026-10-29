<?php
/**
 * Yoast SEO Application Verification Guard.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Plugins
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class YoastSeo
 *
 * Evaluates core framework indices ensuring Yoast SEO (or premium variant equivalents)
 * run safely in current layouts using automated option metrics mappings.
 *
 * @since 1.0.0
 */
class YoastSeo extends AbstractPlugin {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'yoast-seo';
	}

	/**
	 * Declares the specific technical proof criteria required for Yoast SEO verification.
	 *
	 * Utilizes the custom ProofRequirement DTO to pass class checks safely without array wrappers.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The structured proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement(
			constants: 'WPSEO_VERSION' // WPSEO_Options is autoloaded later (wpseo_init runs on plugins_loaded 14).
		);
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this plugin can offer.
	 *
	 * Transforms your link data into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of plugin toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-yoast-seo',
				title:   __( 'Yoast SEO', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wpseo_dashboard' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Yoast SEO Dashboard', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
