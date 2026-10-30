<?php
/**
 * CookieYes | GDPR Cookie Consent Verification Guard.
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
 * Class CookieYes
 *
 * Represents CookieYes | GDPR Cookie Consent in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class CookieYes extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'cookie-yes';
	}

	/**
	 * Returns the name the plugin is known by (its header says "CookieYes | GDPR Cookie Consent").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'CookieYes';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'CLI_VERSION' );
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
				id:          'cf-cookie-yes',
				title:       __( 'Cookie Consent', 'core-functionality' ),
				path:        'admin.php?page=cookie-law-info',
				label:       __( 'CookieYes', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-settings',
				title:       __( 'Cookie Consent Settings', 'core-functionality' ),
				path:        'edit.php?post_type=cookielawinfo&page=cookie-law-info',
				short_title: __( 'Settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-cookies',
				title:       __( 'Cookie List', 'core-functionality' ),
				path:        'edit.php?post_type=cookielawinfo',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-categories',
				title:       __( 'Cookie Categories', 'core-functionality' ),
				path:        'edit-tags.php?taxonomy=cookielawinfo-category&post_type=cookielawinfo',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-scanner',
				title:       __( 'Cookie Scanner', 'core-functionality' ),
				path:        'edit.php?post_type=cookielawinfo&page=cookie-law-info-cookie-scaner',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-policy-generator',
				title:       __( 'Cookie Policy Generator', 'core-functionality' ),
				path:        'edit.php?post_type=cookielawinfo&page=cookie-law-info-policy-generator',
				short_title: __( 'Policy Generator', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-script-blocker',
				title:       __( 'Script Blocker', 'core-functionality' ),
				path:        'edit.php?post_type=cookielawinfo&page=cli-script-settings',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-cookie-yes-privacy-overview',
				title:       __( 'Privacy Overview', 'core-functionality' ),
				path:        'edit.php?post_type=cookielawinfo&page=cookie-law-info-poverview',
			),
		);
	}
}
