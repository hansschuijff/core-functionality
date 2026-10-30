<?php
/**
 * Genesis Simple FAQ Verification Guard.
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
 * Class GenesisSimpleFaq
 *
 * Reports whether Genesis Simple FAQ is active and loaded. Supplies its admin links.
 *
 * @since 1.0.0
 */
class GenesisSimpleFaq extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'genesis-simple-faq';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'GENESIS_SIMPLE_PLUGIN_VERSION' );
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
				id:          'cf-genesis-simple-faq',
				title:       __( 'FAQs', 'core-functionality' ),
				path:        'edit.php?post_type=gs_faq',
				label:       __( 'Genesis Simple FAQ', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-genesis-simple-faq-new',
				title:       __( 'New FAQ', 'core-functionality' ),
				path:        'post-new.php?post_type=gs_faq',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-genesis-simple-faq-categories',
				title:       __( 'FAQ Categories', 'core-functionality' ),
				path:        'edit-tags.php?taxonomy=gs_faq_categories&post_type=gs_faq',
				short_title: __( 'Categories', 'core-functionality' ),
			),
		);
	}
}
