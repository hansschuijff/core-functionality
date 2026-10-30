<?php
/**
 * Yoast SEO Premium Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;

/**
 * Class YoastSeoPremium
 *
 * Represents Yoast SEO Premium in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class YoastSeoPremium extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'yoast-seo-premium';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'WPSEO_PREMIUM_VERSION' );
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
			ToolbarShortcut::to_admin_page(
				id:          'cf-yoast-seo-premium-redirects',
				title:       __( 'Redirects', 'core-functionality' ),
				path:        'tools.php?page=wpseo_redirects_tools',
				label:       __( 'Yoast SEO Redirects', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-yoast-seo-premium-workouts',
				title:       __( 'SEO Workouts', 'core-functionality' ),
				path:        'admin.php?page=wpseo_workouts',
				short_title: __( 'Workouts', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-yoast-seo-premium-brand-insights',
				title:       __( 'AI Brand Insights', 'core-functionality' ),
				path:        'admin.php?page=wpseo_brand_insights_premium',
			),
		);
	}
}
