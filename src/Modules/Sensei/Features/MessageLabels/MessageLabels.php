<?php
/**
 * Sensei Message Labels Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Sensei\Features\MessageLabels
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Sensei\Features\MessageLabels;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use Override;

use function __;
use function add_filter;
use function array_merge;
use function is_array;
use function mb_strtolower;
use function sprintf;

/**
 * Class MessageLabels
 *
 * Calls Sensei's messages between learners and teachers "Sensei messages" instead of "Messages".
 * In Dutch both those and posts are called "Berichten", which is confusing wherever post types are
 * listed together (such as the Duplicate Post settings). Sensei's own submenu item stays as it is:
 * under the Sensei menu it is clear.
 *
 * @since 1.0.0
 */
class MessageLabels extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'sensei_message_labels',
			name:        __( 'Message labels', 'core-functionality' ),
			description: __( 'Calls Sensei\'s messages "Sensei messages", so they are not mixed up with posts (both "Berichten" in Dutch).', 'core-functionality' ),
			status:      Deployment::PRODUCTION,
		);
	}

	/**
	 * Adds the hooks of this feature. Sensei registers its post types on init 10, after the kernel.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'sensei_register_post_type_sensei_message', array( $this, 'filter_post_type_args' ) );
	}

	/**
	 * Replaces the labels in the registration arguments of the sensei_message post type.
	 *
	 * @since  1.0.0
	 * @param  mixed $args The registration arguments.
	 * @return mixed The arguments.
	 */
	public function filter_post_type_args( mixed $args ): mixed {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$labels         = is_array( $args['labels'] ?? null ) ? $args['labels'] : array();
		$args['labels'] = array_merge( $labels, $this->get_labels() );

		return $args;
	}

	/**
	 * Returns the labels, built the way Sensei builds its own, so the other labels match.
	 *
	 * @since  1.0.0
	 * @return array<string, string> The labels.
	 */
	private function get_labels(): array {
		$singular = __( 'Sensei message', 'core-functionality' );
		$plural   = __( 'Sensei messages', 'core-functionality' );
		$lower    = mb_strtolower( $plural, 'UTF-8' );

		// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.WP.I18n.MissingTranslatorsComment -- Sensei's own label patterns, so they read like its other post types.
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'add_new_item'       => sprintf( __( 'Add New %s', 'sensei-lms' ), $singular ),
			'edit_item'          => sprintf( __( 'Edit %s', 'sensei-lms' ), $singular ),
			'new_item'           => sprintf( __( 'New %s', 'sensei-lms' ), $singular ),
			'all_items'          => $plural,
			'view_item'          => sprintf( __( 'View %s', 'sensei-lms' ), $singular ),
			'search_items'       => sprintf( __( 'Search %s', 'sensei-lms' ), $plural ),
			'not_found'          => sprintf( __( 'No %s found', 'sensei-lms' ), $lower ),
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash', 'sensei-lms' ), $lower ),
			'menu_name'          => $plural,
		);
		// phpcs:enable
	}
}
