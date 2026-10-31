<?php
/**
 * Short Bulk Action Labels Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\WooCommerce\Features\ShortBulkActionLabels
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\ShortBulkActionLabels;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Orientation;
use Override;

use function __;
use function add_filter;
use function array_intersect_key;

/**
 * Class ShortBulkActionLabels
 *
 * Gives the status actions in the bulk action select of the order list short names that say what
 * the action does: "Process", "Put on hold", "Complete" and "Cancel" instead of WooCommerce's
 * "Change status to processing" and so on. Only the labels change; the action keys stay WooCommerce's
 * own, so the handling is untouched.
 *
 * @since 1.0.0
 */
class ShortBulkActionLabels extends AbstractFeature {

	/**
	 * Bulk action filters of the order list: the legacy posts table and the HPOS table.
	 *
	 * @var string[]
	 */
	private const BULK_ACTION_FILTERS = array( 'bulk_actions-edit-shop_order', 'bulk_actions-woocommerce_page_wc-orders' );

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'woocommerce_short_bulk_action_labels',
			name:        __( 'Short bulk actions for orders', 'core-functionality' ),
			description: __( 'Short names for the status actions in the bulk action select of the order list: "Process", "Put on hold", "Complete" and "Cancel".', 'core-functionality' ),
			orientation: Orientation::ADMIN,
		);
	}

	/**
	 * Hooks into the bulk actions of both order lists, after WooCommerce has added its own.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		foreach ( self::BULK_ACTION_FILTERS as $filter ) {
			add_filter( $filter, array( $this, 'shorten_labels' ), 20 );
		}
	}

	/**
	 * Replaces the labels of the status actions that are in the list.
	 *
	 * An action that is not in the list (such as in the trash view) is not added.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $actions The bulk actions, key => label.
	 * @return array<string, string> The bulk actions with the short labels.
	 */
	public function shorten_labels( array $actions ): array {
		$short_labels = array_intersect_key( $this->get_short_labels(), $actions );

		foreach ( $short_labels as $key => $label ) {
			$actions[ $key ] = $label;
		}

		return $actions;
	}

	/**
	 * Returns the short labels by WooCommerce's action key.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Action key => label.
	 */
	private function get_short_labels(): array {
		return array(
			'mark_processing' => __( 'Process', 'core-functionality' ),
			'mark_on-hold'    => __( 'Put on hold', 'core-functionality' ),
			'mark_completed'  => __( 'Complete', 'core-functionality' ),
			'mark_cancelled'  => __( 'Cancel', 'core-functionality' ),
		);
	}
}
