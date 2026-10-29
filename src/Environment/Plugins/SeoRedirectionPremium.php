<?php
/**
 * Seo Redirection Premium Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class SeoRedirectionPremium
 *
 * Represents Seo Redirection Premium in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class SeoRedirectionPremium extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'seo-redirection-premium';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'SR_PLUGIN_NAME' );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-seo-redirection-all',
				title:   __( 'Redirects', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=seo-redirection-premium.php' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'SEO Redirects', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-seo-redirection-404',
				title:   __( 'Redirect 404s', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=seo-redirection-premium.php&SR_tab=404_manager' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Redirect 404s', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
