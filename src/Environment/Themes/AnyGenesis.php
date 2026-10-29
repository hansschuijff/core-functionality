<?php
/**
 * Genesis Framework / Parent Theme Verification Guard.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Themes
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Themes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class AnyGenesis
 *
 * Verifies theme asset deployment patterns evaluating if the running system layout
 * theme (or parent design scaffolding framework) is securely powered by Genesis.
 *
 * @since 1.0.0
 */
class AnyGenesis extends AbstractTheme {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'any-genesis-theme';
	}

	/**
	 * Declares specific technical proof criteria required for framework verification.
	 *
	 * Defensively checks for core Genesis constants to guarantee the framework is fully loaded.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The structured proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Genesis defines this on genesis_init, while its functions.php loads (before the Kernel boots on init).
		return new ProofRequirement( constants: 'PARENT_THEME_VERSION' );
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this framework can offer.
	 *
	 * Transforms your custom link data directly into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of theme toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The menu head first, then Genesis's own admin menu.
		return array(
			new ToolbarShortcut(
				id:      'cf-genesis',
				title:   'Genesis',
				href:    admin_url( 'admin.php?page=genesis' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis Framework', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-genesis-theme-settings',
				title:   __( 'Theme Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=genesis' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis Theme Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-genesis-seo-settings',
				title:   __( 'SEO Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=seo-settings' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis SEO Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-genesis-import-export',
				title:   __( 'Genesis Import/Export', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=genesis-import-export' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis Import/Export', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Import/Export', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-genesis-plugins',
				title:   __( 'Genesis Plugins', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=genesis-plugins' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis Plugins', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-genesis-getting-started',
				title:   __( 'Child Theme Setup', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=genesis-getting-started' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis Child Theme Setup', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
