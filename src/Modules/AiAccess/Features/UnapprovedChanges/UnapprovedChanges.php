<?php
/**
 * AI Unapproved Changes Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\UnapprovedChanges
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.1.1
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\UnapprovedChanges;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentEditing;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\UnapprovedAbilitiesBlocker;
use Override;

use function __;
use function add_filter;
use function implode;

/**
 * Class UnapprovedChanges
 *
 * Lifts the block on abilities of other plugins that change the site or send e-mail without a step
 * where the site owner can review and approve the change first (see UnapprovedAbilitiesBlocker).
 * Off by default and confirmed with a warning when ticked: it opens the site in ways the rest of
 * AI Access keeps closed, so its explanation lists exactly what it opens on this site (Hans,
 * November 2026). Publishing posts, pages, events and courses stays closed: those always go
 * through a draft.
 *
 * @since 1.1.1
 */
class UnapprovedChanges extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.1.1
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:                 'ai_access_unapproved_changes',
			name:               __( 'Other Changes Without Asking for Approval', 'core-functionality' ),
			description:        self::get_long_description(),
			uses_features:      array( ContentEditing::class ),
			activation_warning: __( 'This lets the AI assistant make changes you cannot review or approve beforehand. First read in the explanation which kinds of changes this covers. Do you want to switch this on?', 'core-functionality' ),
		);
	}

	/**
	 * Returns the description: what the feature opens, what stays closed and when to use it, in
	 * paragraphs separated by blank lines.
	 *
	 * @since  1.1.1
	 * @return string The description.
	 */
	private static function get_long_description(): string {
		$paragraphs = array(
			__( 'With this, the AI assistant may also change settings and data of other plugins, such as Yoast SEO, WooCommerce, WP Rocket, Jetpack Forms and FluentCRM. Such changes have no draft: they apply on the site at once, without you being able to review them first. Think of changing a published product, clearing the cache or sending an e-mail. The list below shows which actions this concerns on this site.', 'core-functionality' ),
			__( 'Posts, pages, events and courses always go through a draft: the AI assistant may not publish them, also with this option. That stays with you.', 'core-functionality' ),
			__( 'Off by default. Only switch it on when the AI assistant may do such actions for you. What it can do then exactly depends on the user role of the AI assistant.', 'core-functionality' ),
		);

		return implode( "\n\n", $paragraphs );
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * @since  1.1.1
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'core-functionality/ai-access-hide-abilities', '__return_false' );
	}

	/**
	 * Returns the actions this feature opens on this site, one line per plugin: the blocked
	 * abilities that are registered here, by the label their plugin gives them.
	 *
	 * @since  1.1.1
	 * @return string[] The lines, as plain text.
	 */
	#[Override]
	public static function get_explanation_details(): array {
		return UnapprovedAbilitiesBlocker::get_action_lines();
	}
}
