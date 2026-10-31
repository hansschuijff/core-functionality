<?php
/**
 * Options Vault and Quarantine Management Settings Section Component.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Admin\Services\Settings\Sections
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Admin\Services\Settings\Sections;

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Plugin;
use DeWittePrins\CoreFunctionality\Services\Options;
use NBHelpers\HTML;
use WP_Error;
use Override;

// Import global PHP and WordPress core abstraction functions.
use function __;
use function esc_html;
use function esc_attr;
use function is_array;
use function is_string;
use function sanitize_key;
use function sanitize_text_field;
use function count;
use function strlen;
use function sprintf;
use function _n;
use function array_keys;
use function str_starts_with;
use function str_replace;
use function wp_nonce_field;
use function wp_verify_nonce;
use function current_user_can;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptionsManagementSettings
 *
 * Exposes a developer utility to review quarantines, migrate configurations, and delete feature settings tokens.
 *
 * @since 1.0.0
 */
class OptionsManagementSettings implements SettingsSectionInterface {

	/**
	 * OptionsManagementSettings Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The central plugin class instance acting as a provider.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Returns the unique section string identifier used for dashboard tabs routing.
	 *
	 * @since  1.0.0
	 * @return string The URL slug identifier handle.
	 */
	#[Override]
	public static function get_id(): string {
		return 'cf-options-vault-management';
	}

	/**
	 * Returns the dashicon icon string handle assigned to this tab interface.
	 *
	 * @since  1.0.0
	 * @return string The WordPress dashicon layout name.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-database';
	}

	/**
	 * Returns the localized menu tab presentation text title header.
	 *
	 * @since  1.0.0
	 * @return string The translatable core layout navigation text title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Options Vault', 'core-functionality' );
	}

	/**
	 * Returns the short narrative summary explaining user dashboard management parameters.
	 *
	 * @since  1.0.0
	 * @return string The translatable core instructional description header text.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Review unmapped feature settings inside the Quarantine pool. Choose to transport them in bulk to standalone or grouped database keys, or delete them permanently from the system.', 'core-functionality' );
	}

	/**
	 * Registers standalone cascading style sheets file dependencies maps registers.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Array mapping unique style handles to absolute file asset URLs.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array(); }

	/**
	 * Registers interactive execution JavaScript script file assets handles maps bindings.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Array mapping unique script handles to absolute file asset URLs.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array(); }

	/**
	 * Orchestrates the rendering pipeline generating the complete feature settings dashboard views layout.
	 *
	 * Combines active mappings structures and unrouted pools inside an integrated administrative panel.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Optional. Force resolution to bypass active database overrides. Default false.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$options_service = $this->plugin->settings->get_options();

		// Use the completed flat-architecture delegated methods out of the Options vault directly.
		$keys               = $options_service->get_keys();
		$quarantine_pool    = $options_service->get_quarantine_pool();
		$standalone_options = $options_service->get_standalone_keys();
		$grouped_options    = $options_service->get_grouped_keys();
		$keys_in_use        = array_merge( array_keys( $grouped_options ), array_values( $standalone_options ) );

		// INJECT THE NATIVE WordPress NONCE FIELDS IMMEDIATELY AT THE TOP.
		wp_nonce_field( 'dwp_vault_management_action', 'dwp_vault_management_nonce' );

		echo '<div class="dwp-options-management-dashboard" style="max-width: 900px; display: flex; flex-direction: column; gap: 30px;">';

		// Section 1: The Active Quarantine Pool List Panel Component.
		$this->render_quarantine_section( $quarantine_pool, $keys_in_use );

		// Section 2: The Active Registered Feature Settings Aligned Grid Component.
		$this->render_registered_keys_section( $keys, $standalone_options, $grouped_options );

		// Section 3: The Isolated Danger Zone (Moved safely out of bulk action areas).
		// if ( ! empty( $quarantine_pool ) || ! empty( $keys ) ) {
		// $this->render_danger_zone_panel();
		// }.

		echo '</div>';
	}

	/**
	 * Pre-flight validation gate verifying input fields structures before save execution.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST context parameters array forwarded by the engine.
	 * @return bool|WP_Error      True if valid, false or WP_Error object matching field errors on failure.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		if ( isset( $posted_data['dwp_vault_bulk_action'] ) && 'custom_standalone' === $posted_data['dwp_vault_bulk_action'] ) {
			$custom_dest = sanitize_text_field( $posted_data['dwp_vault_custom_destination'] ?? '' );
			if ( empty( $custom_dest ) ) {
				return new WP_Error( 'missing_field', __( 'Please provide a custom destination option name.', 'core-functionality' ) );
			}
		}
		return true;
	}

	/**
	 * Security guard checking user permissions and cryptographic nonce verification.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST context fields dataset forwarded by the controller.
	 * @return bool               True if the active request satisfies all security gates, false otherwise.
	 */
	private function allowed_to_save( array $posted_data ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->plugin->notices->add( 'error', __( 'Critical Error: You do not have sufficient permissions to modify the Options Vault.', 'core-functionality' ) );
			return false;
		}

		$nonce = $posted_data['dwp_vault_management_nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'dwp_vault_management_action' ) ) {
			$this->plugin->notices->add( 'error', __( 'Security Check Failed: The security token has expired or is invalid. Please refresh and try again.', 'core-functionality' ) );
			return false;
		}

		return true;
	}

	/**
	 * Single point of entry handling bulk mutations transactions safely.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST context fields array values dataset.
	 * @return string|bool        Translatable notification string text on execution success, false on failure.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		if ( ! $this->allowed_to_save( $posted_data ) ) {
			return false;
		}

		// Distribute processing targets explicitly matching specific submission button triggers origins.
		if ( isset( $posted_data['dwp_vault_quarantine_submit'] ) ) {
			return $this->handle_quarantine_pool_actions( $posted_data );
		}

		if ( isset( $posted_data['dwp_vault_matrix_submit'] ) ) {
			return $this->handle_key_option_actions( $posted_data );
		}

		if ( isset( $posted_data['dwp_vault_permanent_purge_submit'] ) ) {
			return $this->handle_permanent_purge_action( $posted_data );
		}

		return true;
	}

	/*
	 * Private render methods.
	 */

	/**
	 * Renders the isolated dashboard panel table wrapper collecting unmapped fallback records indicators.
	 *
	 * @since  1.0.0
	 * @param  array $quarantine_pool Decoupled rogue feature setting tokens array maps.
	 * @param  array $keys_in_use     Unique consolidated physical database keys currently in use.
	 * @return void
	 */
	private function render_quarantine_section( array $quarantine_pool, array $keys_in_use ): void {
		$this->render_quarantine_section_header();

		if ( empty( $quarantine_pool ) ) {
			$this->render_quarentine_section_empty();
		} else {
			foreach ( $quarantine_pool as $q_token => $q_data ) {
				$this->render_quarantine_row( $q_token, $q_data );
			}
		}

		$this->render_quarantine_section_footer( $keys_in_use, empty( $quarantine_pool ) );
	}

	/**
	 * Renders the section card panel layout opening markers and table headers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_quarantine_section_header(): void {
		$title = esc_html__( 'Feature Tokens in Quarantine Pool', 'core-functionality' );

		echo <<<HTML
		<div class="dwp-card-panel" style="background: #fff; border: 1px solid #ccd0d4; padding: 25px; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
			<h2 style="font-size: 18px; font-weight: 600; margin-top: 0; margin-bottom: 15px; color: #b91c1c;">{$title}</h2>
			<table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px; border: 1px solid #e2e8f0;">
				<thead>
					<tr>
						<th style="width: 40px; padding: 12px; text-align: center;">
							<input type="checkbox" onclick="var cs = document.querySelectorAll('.js-bulk-q'); cs.forEach(c => c.checked = this.checked);" />
						</th>
						<th style="font-weight: 600; padding: 12px; width: 45%; color: #334155;"># Unmapped Token</th>
						<th style="font-weight: 600; padding: 12px; color: #334155;">Data Size</th>
					</tr>
				</thead>
				<tbody>
		HTML;
	}

	/**
	 * Outputs fallback feedback rows when no rogue parameters targets reside in the pool buffer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_quarentine_section_empty(): void {
		$empty_msg = esc_html__( '🎉 Beautiful! The quarantine pool is currently completely empty.', 'core-functionality' );
		echo "<tr><td colspan='3' style='padding: 15px; color: #16a34a; font-style: italic; font-weight: 600;'>{$empty_msg}</td></tr>";
	}

	/**
	 * Outputs an individual standalone table layout grid data row summarizing unmapped payload attributes.
	 *
	 * @since  1.0.0
	 * @param  string $q_token Unrouted secure tracing key signature name.
	 * @param  mixed  $q_data  Raw metadata structure payload allocations evaluated inside the storage cell.
	 * @return void
	 */
	private function render_quarantine_row( string $q_token, mixed $q_data ): void {
		$token_attr = esc_attr( $q_token );
		$token_html = esc_html( $q_token );
		$size_info  = is_array( $q_data )
			/* translators: %d: number of elements in the array. */
			? sprintf( __( 'Array (%d elements)', 'core-functionality' ), count( $q_data ) )
			/* translators: %d: number of characters in the string. */
			: sprintf( __( 'String (%d characters)', 'core-functionality' ), strlen( (string) $q_data ) );

		echo <<<HTML
		<tr>
			<td style="padding: 12px; text-align: center;">
				<input type="checkbox" class="js-bulk-q" name="dwp_bulk_quarantine_tokens[]" value="{$token_attr}" />
			</td>
			<td style="padding: 12px; font-family: monospace; font-size: 13px; color: #b91c1c; font-weight: 600;">{$token_html}</td>
			<td style="padding: 12px; font-size: 13px; color: #475569;">{$size_info}</td>
		</tr>
		HTML;
	}

	/**
	 * Closes the layout structures table container tags and conditionally outputs actions.
	 * Fixed: All structural HTML tags are tightly closed to prevent browser DOM corruption.
	 *
	 * @since 1.0.0
	 * @param array $keys_in_use         Unique grouped keys collections.
	 * @param bool  $is_empty_quarantine Active status indicator boolean flag tracking pool absence metrics.
	 * @return void
	 */
	private function render_quarantine_section_footer( array $keys_in_use, bool $is_empty_quarantine ): void {

		echo '</tbody></table>';

		if ( $is_empty_quarantine ) {
			echo '</div>'; // Closes the central .dwp-card-panel box.
		}

		$action_title = esc_html__( 'Transport Quarantine Tokens:', 'core-functionality' );
		$label_select = esc_html__( 'Choose Transport Target Key:', 'core-functionality' );
		$opt_auto     = esc_html__( 'Automatically map to own row: dwp-cf-{token}', 'core-functionality' );
		$group_shared = esc_attr__( 'Add to existing grouped feature settings option:', 'core-functionality' );
		$group_manual = esc_attr__( 'Manual / Custom inputs allocation:', 'core-functionality' );
		$opt_manual_s = esc_html__( 'New manual Standalone option key...', 'core-functionality' );
		$opt_manual_m = esc_html__( 'New manual Grouped feature settings option...', 'core-functionality' );
		$label_custom = esc_html__( 'Type manual option name:', 'core-functionality' );

		echo <<<HTML
			<div class="dwp-transport-form-box" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-left: 4px solid #475569; margin-top: 15px; border-radius: 4px;">
				<h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; font-weight: 600; color: #334155;">{$action_title}</h3>
				<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
					<div style="flex: 1; min-width: 280px;">
						<label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155;">{$label_select}</label>
						<select name="dwp_vault_bulk_action" style="width: 100%; height: 35px; border-color: #cbd5e1;" onchange="
							var wrap = document.getElementById('js-custom-dest-wrap');
							wrap.style.display = (this.value === 'custom_standalone' || this.value === 'custom_shared') ? 'block' : 'none';
							">
							<option value="auto_map_dash">{$opt_auto}</option>
		HTML;

		if ( ! empty( $keys_in_use ) ) {
			echo "<optgroup label='{$group_shared}'>";
			foreach ( $keys_in_use as $group ) {
				$group_attr = esc_attr( $group );
				echo "<option value='shared:{$group_attr}'>{$group_attr}</option>";
			}
			echo '</optgroup>';
		}

		echo <<<HTML
						<optgroup label="{$group_manual}">
							<option value="custom_standalone">{$opt_manual_s}</option>
							<option value="custom_shared">{$opt_manual_m}</option>
						</optgroup>
					</select>
				</div>
				<div id="js-custom-dest-wrap" style="flex: 1; min-width: 250px; display: none;">
					<label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155;">{$label_custom}</label>
					<input type="text" name="dwp_vault_custom_destination" class="regular-text" style="width: 100%; height: 35px; margin: 0; border-color: #cbd5e1;" placeholder="e.g. custom_option_name" />
				</div>
			</div>
		</div>
		HTML;

		echo '</div>'; // Closes the central .dwp-card-panel box.
	}

	/**
	 * Renders the active registered feature settings overview panel component module grid.
	 *
	 * @since  1.0.0
	 * @param  array $keys               The current raw registered token routing translations array maps.
	 * @param  array $standalone_options Segregated collection filtering standalone rows key indexes.
	 * @param  array $grouped_options    Segregated and geoclustered array mappings structure list records.
	 * @return void
	 */
	private function render_registered_keys_section( array $keys, array $standalone_options, array $grouped_options ): void {

		$this->render_registered_keys_section_header( empty( $keys ) );

		foreach ( $grouped_options as $master_key => $tokens ) {
			// hele div.
			$this->render_grouped_option_block( $master_key, $tokens );
		}

		if ( ! empty( $standalone_options ) ) {
			// hele div.
			$this->render_standalone_options_list( $standalone_options );
		}

		if ( ! empty( $keys ) ) {
			echo '</div>'; // Sluit de .dwp-matrix-clusters-container loepzuiver.

			$this->render_registered_keys_section_actions();
		}

		echo '</div>'; // Sluit de hoofd .dwp-card-panel box loepzuiver.
	}

	/**
	 * Outputs the header title panel context wrapping markers for the active configurations maps list area.
	 *
	 * @since 1.0.0
	 * @param bool $is_empty_keys Status flag tracking key registry presence values.
	 * @return void
	 */
	private function render_registered_keys_section_header( bool $is_empty_keys ): void {
		$section_title = esc_html__( 'Active Registered Feature Settings', 'core-functionality' );

		echo <<<HTML
		<div class="dwp-card-panel" style="background: #fff; border: 1px solid #ccd0d4; padding: 25px; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
			<h2 style="font-size: 18px; font-weight: 600; margin-top: 0; margin-bottom: 20px; color: #1d2327;">{$section_title}</h2>
		HTML;

		if ( $is_empty_keys ) {
			$empty_txt = esc_html__( 'There are currently no active feature settings configured.', 'core-functionality' );
			echo <<<HTML
				<div style='padding: 15px; color: #64748b; font-style: italic; border: 1px dashed #cbd5e1; background: #f8fafc;'>
					{$empty_txt}
				</div>
			<!-- </div> -->
			HTML;
			return;
		}

		echo <<<HTML
			<div class="dwp-matrix-clusters-container" style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 20px;">
		HTML;
	}

	/**
	 * Renders an individual multi-tenant geoclustered shared database key option block module card.
	 *
	 * @since  1.0.0
	 * @param  string        $master_key The physical database row option parent key string token.
	 * @param  array<string> $tokens     List of virtual abstract tokens residing inside this shared collection option payload.
	 * @return void
	 */
	private function render_grouped_option_block( string $master_key, array $tokens ): void {
		$master_html = esc_html( $master_key );
		/* translators: %d: number of entries. */
		$entry_count = sprintf( _n( '%d entry', '%d entries', count( $tokens ), 'core-functionality' ), count( $tokens ) );

		echo <<<HTML
			<div class="dwp-matrix-cluster-block" style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden;">
				<div class="dwp-cluster-heading" style="background: #f1f5f9; padding: 10px 15px; border-bottom: 1px solid #cbd5e1; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-portfolio" style="color: #1e40af; font-size: 18px; width: 18px; height: 18px;"></span>
					<strong style="font-size: 13px; color: #1e3a8a; font-family: monospace;">{$master_html}</strong>
					<span style="font-size: 11px; background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 10px; font-weight: 600; margin-left: auto;">{$entry_count}</span>
				</div>
				<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
					<tbody>
		HTML;

		foreach ( $tokens as $token ) {
			$token_attr = esc_attr( $token );
			$token_html = esc_html( $token );
			echo <<<HTML
			<tr>
				<td style="width: 40px; padding: 10px; text-align: center; border-bottom: none;">
					<input type="checkbox" class="js-bulk-m" name="dwp_bulk_matrix_tokens[]" value="{$token_attr}" />
				</td>
				<td style="padding: 10px; font-family: monospace; font-size: 13px; color: #334155; border-bottom: none;">{$token_html}</td>
			</tr>
			HTML;
		}

		echo <<<HTML
					</tbody>
				</table>
			</div>
		HTML;
	}

	/**
	 * Renders the single standalone database choices allocations options registry block layout card module.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $standalone_options Collection list matching active single standalone row keys assignments.
	 * @return void
	 */
	private function render_standalone_options_list( array $standalone_options ): void {
		$block_title = esc_html__( 'Standalone Database Options (Own Row)', 'core-functionality' );
		$th_sel      = esc_html__( 'Sel.', 'core-functionality' );
		$th_token    = esc_html__( 'Abstract Intent Token', 'core-functionality' );
		$th_key      = esc_html__( 'Physical Database Option Key', 'core-functionality' );

		echo <<<HTML
			<div class="dwp-matrix-cluster-block" style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden;">
				<div class="dwp-cluster-heading" style="background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #cbd5e1; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-admin-links" style="color: #475569; font-size: 18px; width: 18px; height: 18px;"></span>
					<strong style="font-size: 13px; color: #334155;">{$block_title}</strong>
				</div>
				<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none; border-bottom: 1px solid #e2e8f0;">
					<thead>
						<tr>
							<th style="width: 40px; padding: 10px; text-align: center; background: #fff; font-weight: 600; border-bottom: 1px solid #e2e8f0;">{$th_sel}</th>
							<th style="font-weight: 600; padding: 10px; background: #fff; border-bottom: 1px solid #e2e8f0;">{$th_token}</th>
							<th style="font-weight: 600; padding: 10px; background: #fff; border-bottom: 1px solid #e2e8f0;">{$th_key}</th>
						</tr>
					</thead>
					<tbody>
		HTML;

		foreach ( $standalone_options as $token => $db_key ) {
			$token_attr = esc_attr( $token );
			$token_html = esc_html( $token );
			$key_html   = esc_html( $db_key );
			echo <<<HTML
			<tr>
				<td style="width: 40px; padding: 10px; text-align: center;">
					<input type="checkbox" class="js-bulk-m" name="dwp_bulk_matrix_tokens[]" value="{$token_attr}" />
				</td>
				<td style="padding: 10px; font-family: monospace; font-size: 13px; color: #334155; font-weight: 600;">{$token_html}</td>
				<td style="padding: 10px; font-family: monospace; font-size: 13px; color: #2563eb;">{$key_html}</td>
			</tr>
			HTML;
		}

		echo <<<HTML
					</tbody>
				</table>
			</div>
		HTML;
	}

	/**
	 * Renders the aligned bulk actions management controller box for the active feature settings list.
	 * Notice: Submit button is completely omitted and delegated to the centralized footer page factory!
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_registered_keys_section_actions(): void {
		$action_title     = esc_html__( 'Active Settings Bulk Actions Panel:', 'core-functionality' );
		$label_select     = esc_html__( 'Choose Action for Selected Active Tokens:', 'core-functionality' );
		$opt_quarantine   = esc_html__( '↩️ De-register: Move tokens back to Quarantine pool', 'core-functionality' );
		$label_select_all = esc_html__( 'Select all active features tokens across all blocks', 'core-functionality' );

		echo <<<HTML
		<div class="dwp-transport-form-box" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-left: 4px solid #2271b1; margin-top: 15px; border-radius: 4px;">
			<h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; font-weight: 600; color: #1e40af;">{$action_title}</h3>
			<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 5px;">
				<div style="flex: 1; min-width: 280px;">
					<label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155;">{$label_select}</label>
					<select name="dwp_vault_matrix_action" style="width: 100%; height: 35px; border-color: #cbd5e1;">
						<option value="quarantine">{$opt_quarantine}</option>
					</select>
				</div>
				<div style="flex: 1; min-width: 250px; padding-bottom: 8px;">
					<label style="font-size: 12px; color: #64748b; cursor: pointer;">
						<input type="checkbox" onclick="var cs = document.querySelectorAll('.js-bulk-m'); cs.forEach(c => c.checked = this.checked);" style="margin: 0 4px 0 0; vertical-align: middle;" />
						{$label_select_all}
					</label>
				</div>
			</div>
		</div>
		HTML;
	}

	/**
	 * Renders an isolated Danger Zone panel at the absolute bottom for critical destructive actions.
	 * Notice: The submit button is completely omitted here and integrated inside the footer factory!
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_danger_zone_panel(): void {
		$zone_title = esc_html__( 'Danger Zone Panel Area', 'core-functionality' );
		$zone_desc  = esc_html__( 'Erase Configuration Strategy: Select feature settings tokens via checkboxes across either list above to submit them for hard erasure. Applying the button action inside the footer below will permanently scrub the keys and clear out data structures directly from the WordPress database rows context allocations.', 'core-functionality' );

		echo <<<HTML
		<div class="dwp-danger-zone-panel" style="background: #fff5f5; border: 1px solid #fee2e2; border-left: 4px solid #ef4444; padding: 20px; margin-top: 30px; border-radius: 4px;">
			<h3 style="margin-top: 0; margin-bottom: 8px; font-size: 14px; font-weight: 700; color: #991b1b;">⚠️ {$zone_title}</h3>
			<p style="margin-top: 0; margin-bottom: 0; font-size: 13px; color: #7f1d1d; line-height: 1.5;">{$zone_desc}</p>
		</div>
		HTML;
	}

	/**
	 * Delivers custom context-aware action buttons to the host administration factory footer container.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Dictionary indexing raw HTML button element blocks strings.
	 */
	public function get_action_buttons(): array {
		$btn_transport = esc_attr__( 'Add keys to options', 'core-functionality' );
		$btn_matrix    = esc_attr__( 'Remove keys from options', 'core-functionality' );
		$btn_purge     = esc_attr__( 'Permanently Erase Options', 'core-functionality' );
		$confirm_purge = esc_attr__( 'CRITICAL WARNING: Are you absolutely certain you want to permanently erase the selected tokens and all their entries from storage? This action cannot be reverted.', 'core-functionality' );

		return array(
			'transport' => <<<HTML
				<button type="submit" name="dwp_vault_quarantine_submit" value="1" class="button button-primary" style="height: 35px; background: #334155; border-color: #1e293b;">
					{$btn_transport}
				</button>
			HTML,
			'matrix'    => <<<HTML
				<button type="submit" name="dwp_vault_matrix_submit" value="1" class="button button-secondary" style="height: 35px; border-color: #2271b1; color: #2271b1;">
					{$btn_matrix}
				</button>
			HTML,
			'purge'     => <<<HTML
				<button type="submit" name="dwp_vault_permanent_purge_submit" value="1" class="button button-link" style="color: #dc2626; border: 1px solid #dc2626; padding: 0 12px; height: 35px; line-height: 33px; text-decoration: none; background: #fff; border-radius: 3px; font-weight: 600; margin-left: auto;" onclick="
					var checkedCount = document.querySelectorAll('.js-bulk-q:checked, .js-bulk-m:checked').length;
					if (checkedCount === 0) {
						alert('Please select at least one checkbox element from the lists above to execute deep purge operations.');
						return false;
					}
					return confirm('{$confirm_purge}');
				">
					{$btn_purge}
				</button>
			HTML,
		);
	}

	/**
	 * Processes execution operations triggered from the unmapped Quarantine pool bulk panel list.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST context fields.
	 * @return string|bool        Localized execution message text on success, false on failure.
	 */
	private function handle_quarantine_pool_actions( array $posted_data ): string|bool {
		$selected_tokens = isset( $posted_data['dwp_bulk_quarantine_tokens'] ) ? (array) $posted_data['dwp_bulk_quarantine_tokens'] : array();
		$selected_action = sanitize_text_field( $posted_data['dwp_vault_bulk_action'] ?? '' );

		if ( empty( $selected_tokens ) ) {
			$this->plugin->notices->add( 'warning', __( 'No Tokens were selected. Please select the quarantine tokens you want processed.', 'core-functionality' ) );
			return false;
		}

		if ( empty( $selected_action ) ) {
			$this->plugin->notices->add( 'error', __( 'Please select an action you want to perform on the tokens you selected.', 'core-functionality' ) );
			return false;
		}

		$options_service = $this->plugin->settings->get_options();
		$count           = 0;

		$target_key = $this->resolve_target_group( $selected_action, $posted_data );

		if ( 'auto_map_dash' === $selected_action ) {
			// Resolved autonomously inside transport loop execution path.
		} elseif ( empty( $target_key ) ) {
			$this->plugin->notices->add( 'error', __( 'Bulk Action Failed: Target destination key cannot be empty.', 'core-functionality' ) );
			return false;
		}

		foreach ( $selected_tokens as $token ) {
			$token              = sanitize_key( $token );
			$current_target_key = ( 'auto_map_dash' === $selected_action ) ? 'dwp-cf-' . $token : $target_key;

			// Vault service entirely absorbs data relocation, structural alignment, and cleanup rules.
			if ( $options_service->assign_token_to_key( $token, $current_target_key ) ) {
				++$count;
			}
		}

		/* translators: %d: number of moved tokens. */
		return sprintf( __( 'Bulk transport complete! %d feature tokens successfully moved to active storage destinations.', 'core-functionality' ), $count );
	}

	/**
	 * Processes execution routines triggered from the active registered feature settings checkboxes lists.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST context fields map datasets.
	 * @return string|bool        Localized response notices text string on mutation completion, false on failures.
	 */
	private function handle_key_option_actions( array $posted_data ): string|bool {
		if ( empty( $posted_data['dwp_bulk_matrix_tokens'] ) ) {
			$this->plugin->notices->add( 'warning', __( 'Bulk Action Failed: No active features tokens were selected for processing.', 'core-functionality' ) );
			return false;
		}

		$tokens          = (array) $posted_data['dwp_bulk_matrix_tokens'];
		$action_type     = sanitize_text_field( $posted_data['dwp_vault_matrix_action'] ?? '' );
		$options_service = $this->plugin->settings->get_options();
		$count           = 0;

		if ( 'quarantine' === $action_type ) {
			foreach ( $tokens as $token ) {
				$token = sanitize_key( $token );

				// Centralize routing decoupling into the primary quarantine service method block.
				if ( $options_service->quarantine_token( $token ) ) {
					++$count;
				}
			}
			/* translators: %d: number of keys. */
			return sprintf( __( '%d keys successfully de-registered and safely returned to quarantine via the Vault.', 'core-functionality' ), $count );
		}

		return true;
	}

	/**
	 * Processes absolute permanent erasure transaction requests from the standalone bottom Danger Zone bar.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST dataset.
	 * @return string|bool        Localized notice string text on success.
	 */
	private function handle_permanent_purge_action( array $posted_data ): string|bool {
		$quarantine_tokens = isset( $posted_data['dwp_bulk_quarantine_tokens'] ) ? (array) $posted_data['dwp_bulk_quarantine_tokens'] : array();
		$matrix_tokens     = isset( $posted_data['dwp_bulk_matrix_tokens'] ) ? (array) $posted_data['dwp_bulk_matrix_tokens'] : array();

		// Merge checked selections from both sections into a clean consolidated loop.
		$tokens_to_purge = array_merge( $quarantine_tokens, $matrix_tokens );

		if ( empty( $tokens_to_purge ) ) {
			$this->plugin->notices->add( 'warning', __( 'Deep Purge Failed: No tokens were checked from either section lists.', 'core-functionality' ) );
			return false;
		}

		$options_service = $this->plugin->settings->get_options();
		$count           = 0;

		foreach ( $tokens_to_purge as $token ) {
			$token = sanitize_key( $token );

			// Invoke pristine core delete method absorbing standalone, grouped, and pool deletions.
			if ( $options_service->delete( $token ) ) {
				++$count;
			}
		}

		/* translators: %d: number of erased configurations. */
		return sprintf( __( 'Deep Purge Completed! %d configurations tokens and data structures fully erased from the database.', 'core-functionality' ), $count );
	}

	/*
	 * Private utility and lookup helpers.
	 */

	/**
	 * Safely retrieves active translations registers out of the configuration layer via framework route.
	 *
	 * @since  1.0.0
	 * @return array Current token routing configuration translation mapping criteria schemas.
	 */
	private function get_translations(): array {
		$options_service = $this->plugin->settings->get_options();
		return $options_service->get_keys();
	}

	/**
	 * Safely retrieves unmapped quarantine pool records straight from the Options vault framework layer.
	 *
	 * @since  1.0.0
	 * @return array Current isolated rogue tracking entities storage pools allocations.
	 */
	private function get_quarantine_pool(): array {
		$options_service = $this->plugin->settings->get_options();
		return $options_service->get_quarantine_pool();
	}

	/**
	 * Iterates over current active records rules to extract distinct physical master collection targets.
	 *
	 * Used for dynamically populating existing shared destination option index dropdown lists blocks.
	 *
	 * @since  1.0.0
	 * @param  array $keys Raw configuration routing translation layouts context maps.
	 * @return array Unique consolidated option key handles array matching live database rows configurations.
	 */
	private function extract_keys_in_use( array $keys ): array {
		$keys_in_use = array();
		foreach ( $keys as $target ) {
			if ( is_string( $target ) && ! empty( $target ) ) {
				$keys_in_use[ $target ] = $target;
			}
		}
		return $keys_in_use;
	}

	/**
	 * Segregates raw translation matrices into distinct standalone rows arrays vs shared grouped collection blocks.
	 *
	 * Performs live lookups convergence integrations.
	 *
	 * @since  1.0.0
	 * @param  array $keys Raw active translation rules configuration datasets indexes parameters.
	 * @return array Compiled layout presentation multi-tiered structure list maps array.
	 */
	private function get_tokens_and_keys( array $keys ): array {
		$options_service = $this->plugin->settings->get_options();

		$standalone_options = $options_service->get_standalone_keys();
		$grouped_options    = $options_service->get_grouped_keys();

		return array( $standalone_options, $grouped_options );
	}

	/**
	 * Resolves structural destination targets derived from dynamic custom selection actions triggers.
	 *
	 * @since  1.0.0
	 * @param  string $action_type The structural selection action type mode signature identifier string.
	 * @param  array  $posted_data Raw $_POST context fields array records.
	 * @return string The exact target parent key destination string handle name, or empty string.
	 */
	private function resolve_target_group( string $action_type, array $posted_data ): string {
		if ( str_starts_with( $action_type, 'shared:' ) ) {
			return str_replace( 'shared:', '', $action_type );
		}
		if ( 'custom_shared' === $action_type ) {
			return sanitize_text_field( $posted_data['dwp_vault_custom_destination'] ?? '' );
		}
		return '';
	}
}
