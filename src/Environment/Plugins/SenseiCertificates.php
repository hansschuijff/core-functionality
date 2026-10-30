<?php
/**
 * Sensei LMS Certificates Verification Guard.
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
 * Class SenseiCertificates
 *
 * Represents Sensei LMS Certificates in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class SenseiCertificates extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'sensei-certificates';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'SENSEI_CERTIFICATES_VERSION' );
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
				id:          'cf-sensei-certificates',
				title:       __( 'Certificates', 'core-functionality' ),
				path:        'edit.php?post_type=certificate',
				label:       __( 'Sensei LMS Certificates', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-sensei-certificates-new',
				title:       __( 'New Certificate', 'core-functionality' ),
				path:        'post-new.php?post_type=certificate',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-sensei-certificates-templates',
				title:       __( 'Certificate Templates', 'core-functionality' ),
				path:        'edit.php?post_type=certificate_template',
				short_title: __( 'Templates', 'core-functionality' ),
			),
		);
	}
}
