<?php
/**
 * Beaver Builder Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
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
 * Class BeaverBuilder
 *
 * Reports whether the Beaver Builder page builder is active and started, and supplies the links
 * of its admin menu (its saved templates).
 *
 * @since 1.0.0
 */
class BeaverBuilder extends AbstractPlugin {

	/**
	 * Returns the environment id of Beaver Builder.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'beaver-builder';
	}

	/**
	 * Returns the name Beaver Builder is known by (its header says "Beaver Builder Plugin (Pro Version)").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Beaver Builder';
	}

	/**
	 * Returns what must exist to prove Beaver Builder has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// The builder's main class, in the paid plugin and in the lite version.
		return new ProofRequirement( classes: 'FLBuilder' );
	}

	/**
	 * Returns the admin toolbar links Beaver Builder offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The menu head first, then Beaver Builder's own admin menu.
		return array(
			new ToolbarShortcut(
				id:          'cf-beaver-builder',
				title:       'Beaver Builder',
				href:        admin_url( 'edit.php?post_type=fl-builder-template&fl-builder-template-type=layout' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder Templates', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-beaver-builder-layouts',
				title:       __( 'Page Templates', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=fl-builder-template&fl-builder-template-type=layout' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder Page Templates', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-beaver-builder-rows',
				title:       __( 'Saved Rows', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=fl-builder-template&fl-builder-template-type=row' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder Saved Rows', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-beaver-builder-columns',
				title:       __( 'Saved Columns', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=fl-builder-template&fl-builder-template-type=column' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder Saved Columns', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-beaver-builder-modules',
				title:       __( 'Saved Modules', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=fl-builder-template&fl-builder-template-type=module' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder Saved Modules', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-beaver-builder-categories',
				title:       __( 'Template Categories', 'core-functionality' ),
				href:        admin_url( 'edit-tags.php?taxonomy=fl-builder-template-category&post_type=fl-builder-template' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder Template Categories', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Categories', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-beaver-builder-add-new',
				title:       __( 'Add Template', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=fl-builder-template&page=fl-builder-add-new' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Beaver Builder: Add New Template', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Add New', 'core-functionality' ),
			),
		);
	}
}
