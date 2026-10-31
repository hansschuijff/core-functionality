<?php
/**
 * Admin Toolbar Cleaner Settings Component.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AdminToolbar\Features\Cleaner
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Cleaner;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;
use DeWittePrins\CoreFunctionality\Plugin;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Modules\AdminToolbar\AdminToolbar;
use Override;

// Import global PHP and WordPress core abstraction functions.
use function __;
use function esc_html__;
use function esc_html;
use function esc_attr;
use function checked;
use function is_array;
use function in_array;
use function esc_attr__;

/**
 * Class CleanerSettings
 *
 * Manages the dynamic grid panel controlling which discovered active top-level toolbar
 * nodes are maintained on the primary admin bar vs the More overflow menu.
 *
 * @since 1.0.0
 */
class CleanerSettings implements SettingsSectionInterface {

	/**
	 * CleanerSettings Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The central plugin class instance acting as a provider.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Returns the settings key under which this tab stores its choices.
	 *
	 * @since  1.0.0
	 * @return string The settings key.
	 */
	public static function get_settings_key(): string {
		return 'admin-toolbar-cleaner-keep-this-nodes';
	}

	/**
	 * Returns the id of this settings section (used in the page URL).
	 *
	 * @since  1.0.0
	 * @return string The section id.
	 */
	#[Override]
	public static function get_id(): string {
		return 'cf-toolbar-cleaner-settings';
	}

	/**
	 * Returns the title of this settings tab.
	 *
	 * @since  1.0.0
	 * @return string The tab title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Clean up', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Configure your top bar visibility layout strategy. Top-level shortcut nodes left unchecked will be automatically filtered and evacuated into the central "More" overflow dropdown menu.', 'core-functionality' );
	}

	/**
	 * Returns the dashicon class shown next to the tab title.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-buddicons-replies';
	}

	/**
	 * Returns the stylesheets this tab needs.
	 *
	 * @since  1.0.0
	 * @return array The stylesheets to enqueue.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array();
	}

	/**
	 * Returns the scripts this tab needs.
	 *
	 * @since  1.0.0
	 * @return array The scripts to enqueue.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array();
	}

	/**
	 * Outputs the negative selection form matrix based on logged active top-level admin bar nodes.
	 *
	 * @since  1.0.0
	 * @param  bool $reset_to_defaults Optional. Force default blueprint fallbacks bypass. Default false.
	 * @return void
	 */
	#[Override]
	public function render( bool $reset_to_defaults = false ): void {
		// Pull active matrix parameters out of the managed Settings service.
		$keep_in_place_nodes = $this->plugin->settings->get( self::get_settings_key(), AdminToolbar::get_id(), $reset_to_defaults );
		if ( ! is_array( $keep_in_place_nodes ) ) {
			$keep_in_place_nodes = array();
		}

		$movable_nodes = $this->get_movable_nodes();

		if ( empty( $movable_nodes ) ) {
			echo '<p style="font-style: italic; color: #64748b; padding: 15px; border: 1px dashed #cbd5e1; background: #f8fafc;">' . esc_html__( 'No toolbar nodes logged yet. Navigate to any other admin page or the frontend first to allow the discovery engine to index your top-level links.', 'core-functionality' ) . '</p>';
			return;
		}

		$th_node       = esc_html__( 'Top-Level Toolbar Node', 'core-functionality' );
		$th_visibility = esc_html__( 'Keep in Main Toolbar (Visible)', 'core-functionality' );
		$section_id    = esc_attr( self::get_id() );
		?>
		<table class="wp-list-table widefat fixed striped dwp-cleaner-management-table" style="border: 1px solid #ccd0d4; box-shadow: none; border-radius: 4px; overflow: hidden; margin-top: 10px;">
			<thead>
				<tr>
					<th style="font-weight: 600; background: #fff; padding: 12px 15px; border-bottom: 2px solid #e2e8f0; color: #1e293b;"><?php echo $th_node; ?></th>
					<th style="font-weight: 600; background: #fff; padding: 12px 15px; border-bottom: 2px solid #e2e8f0; color: #1e293b; width: 220px; text-align: center;"><?php echo $th_visibility; ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $movable_nodes as $node_id => $node_data ) {
					$keep_in_main = isset( $keep_in_place_nodes[ $node_id ]['main'] ) ? (bool) $keep_in_place_nodes[ $node_id ]['main'] : false;
					?>
					<tr>
						<td style="padding: 12px 15px; vertical-align: middle;">
							<strong style="font-size: 13px; color: #1e293b;"><?php echo esc_html( $node_data['label'] ); ?></strong>
							<code style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 3px; margin-left: 8px; color: #64748b; font-family: monospace;"><?php echo esc_html( $node_id ); ?></code>
						</td>
						<td style="padding: 12px 15px; text-align: center; vertical-align: middle;">
							<input type="checkbox" name="<?php echo $section_id; ?>[<?php echo esc_attr( $node_id ); ?>][main]" value="1" <?php checked( $keep_in_main, true ); ?> style="margin: 0; width: 18px; height: 18px;" />
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Gets the top_level nodes that AdminToolbar\Cleaner can move to the overflow menu.
	 *
	 * @return array
	 */
	private function get_movable_nodes(): array {
		$movable_nodes = $this->plugin->settings->get( Cleaner::get_movable_nodes_key(), AdminToolbar::get_id() );
		if ( ! is_array( $movable_nodes ) ) {
			return array();
		}
		return $movable_nodes;
	}

	/**
	 * Validates the submitted form data before it is saved.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return bool|WP_Error True when valid, an error otherwise.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		return true;
	}

	/**
	 * Saves the submitted form data.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return string|bool A success message, true, or false on failure.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		$cleaner_input = isset( $posted_data[ self::get_id() ] ) && is_array( $posted_data[ self::get_id() ] )
			? $posted_data[ self::get_id() ]
			: array();

		return $this->plugin->settings->save( self::get_settings_key(), $cleaner_input );
	}

	/**
	 * Returns the extra action buttons shown under the form of this tab.
	 *
	 * @since  1.0.0
	 * @return array The action buttons.
	 */
	#[Override]
	public function get_action_buttons(): array {
		$btn_save = esc_attr__( 'Save Toolbar Visibility Filter', 'core-functionality' );

		return array(
			'save_cleaner' => <<<HTML
				<button type="submit" name="dwp_vault_matrix_submit" value="1" class="button button-primary" style="height: 35px; background: #2271b1; border-color: #1d5c8f;">
					{$btn_save}
				</button>
			HTML,
		);
	}
}
