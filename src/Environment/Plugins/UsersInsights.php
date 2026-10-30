<?php
/**
 * Users Insights Verification Guard.
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
 * Class UsersInsights
 *
 * Represents Users Insights in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class UsersInsights extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'users-insights';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'USIN_VERSION' );
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
				id:          'cf-users-insights',
				title:       __( 'Users Insights', 'core-functionality' ),
				path:        'admin.php?page=users_insights',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-users-insights-groups',
				title:       __( 'User Groups', 'core-functionality' ),
				path:        'edit-tags.php?taxonomy=usin_group',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-users-insights-custom-fields',
				title:       __( 'User Custom Fields', 'core-functionality' ),
				path:        'admin.php?page=usin_custom_fields',
				short_title: __( 'Custom Fields', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-users-insights-reports',
				title:       __( 'User Reports', 'core-functionality' ),
				path:        'admin.php?page=usin_reports',
				short_title: __( 'Reports', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-users-insights-settings',
				title:       __( 'Users Insights Settings', 'core-functionality' ),
				path:        'admin.php?page=usin_modules',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
