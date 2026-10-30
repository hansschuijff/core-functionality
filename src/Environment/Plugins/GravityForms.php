<?php
/**
 * Gravity Forms Verification Guard.
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
 * Class GravityForms
 *
 * Represents Gravity Forms in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class GravityForms extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'gravity-forms';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: array( 'GFForms', 'GFCommon' ) );
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
				id:      'cf-gravity-forms',
				title:   __( 'Forms', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=gf_edit_forms' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Gravity Forms', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-new',
				title:       __( 'New Form', 'core-functionality' ),
				path:        'admin.php?page=gf_new_form',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-entries',
				title:       __( 'Form Entries', 'core-functionality' ),
				path:        'admin.php?page=gf_entries',
				short_title: __( 'Entries', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-settings',
				title:       __( 'Gravity Forms Settings', 'core-functionality' ),
				path:        'admin.php?page=gf_settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-import-export',
				title:       __( 'Gravity Forms Import/Export', 'core-functionality' ),
				path:        'admin.php?page=gf_export',
				short_title: __( 'Import/Export', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-add-ons',
				title:       __( 'Gravity Forms Add-ons', 'core-functionality' ),
				path:        'admin.php?page=gf_addons',
				short_title: __( 'Add-ons', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-system-status',
				title:       __( 'Gravity Forms System Status', 'core-functionality' ),
				path:        'admin.php?page=gf_system_status',
				short_title: __( 'System Status', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-gravity-forms-help',
				title:       __( 'Gravity Forms Help', 'core-functionality' ),
				path:        'admin.php?page=gf_help',
				short_title: __( 'Help', 'core-functionality' ),
			),
		);
	}
}
