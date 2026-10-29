<?php
/**
 * Duplicate Post Settings Tab.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\DuplicatePost\Features\OwnDuplication
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\DuplicatePost\Features\OwnDuplication;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Environment\Registers\PostTypeRegister;
use DeWittePrins\CoreFunctionality\Modules\DuplicatePost\DuplicatePost;
use DeWittePrins\CoreFunctionality\Plugin;
use Override;
use WP_Error;
use WP_Post_Type;

use function __;
use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_post_types;
use function implode;
use function in_array;
use function is_array;
use function wp_unslash;

/**
 * Class OwnDuplicationSettings
 *
 * The Duplicate Post settings tab: the plugins (and loose post types) that duplicate their content
 * themselves, for which Duplicate Post shows no links. A plugin stands for all the post types its
 * guard names (PostTypeRegister); post types of no known plugin are chosen one by one. Saved as a
 * whole (an unchecked choice stays unchecked); until the first save the defaults file in
 * settings/modules/duplicate-post/ applies.
 *
 * @since 1.0.0
 */
class OwnDuplicationSettings implements SettingsSectionInterface {

	/**
	 * The settings key of all choices of this tab.
	 *
	 * @var string
	 */
	public const SETTINGS_KEY = 'duplicate-post-settings';

	/**
	 * The name of the form field array.
	 *
	 * @var string
	 */
	private const FIELD = 'dwp_cf_duplicate_post';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Returns the id of this settings section (used in the page URL).
	 *
	 * @since  1.0.0
	 * @return string The section id.
	 */
	#[Override]
	public static function get_id(): string {
		return 'cf-duplicate-post-settings';
	}

	/**
	 * Returns the dashicon class shown next to the tab title.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-admin-page';
	}

	/**
	 * Returns the title of this settings tab.
	 *
	 * @since  1.0.0
	 * @return string The tab title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Duplicate Post', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Content types whose own plugin duplicates them, taking care of what belongs to them (such as the ticket products of an event or the lessons of a course). Duplicate Post shows no links for these, so there is one way to duplicate them. Duplicate Post itself stays enabled for them. Choose a plugin and all its content types follow, also those without an admin screen (such as tickets).', 'core-functionality' );
	}

	/**
	 * Returns the stylesheets this tab needs (none).
	 *
	 * @since  1.0.0
	 * @return array Empty array.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array();
	}

	/**
	 * Returns the scripts this tab needs (none).
	 *
	 * @since  1.0.0
	 * @return array Empty array.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array();
	}

	/**
	 * Returns the saved choices, or the defaults until the tab has been saved.
	 *
	 * @since  1.0.0
	 * @param  Plugin $plugin        The main plugin instance.
	 * @param  bool   $defaults_only Whether to return the defaults instead of the saved choices.
	 * @return array{own_duplication_providers: string[], own_duplication_post_types: string[]} The choices.
	 */
	public static function get_values( Plugin $plugin, bool $defaults_only = false ): array {
		$module   = DuplicatePost::get_id();
		$defaults = $plugin->settings->get_default( self::SETTINGS_KEY, $module );
		$defaults = is_array( $defaults ) ? $defaults : array();
		$saved    = $defaults_only ? $defaults : $plugin->settings->get_whole( self::SETTINGS_KEY, $module, $defaults );

		return self::normalize( ( is_array( $saved ) ? $saved : array() ) + $defaults );
	}

	/**
	 * Returns the post types the choices stand for: those of the chosen plugins, plus the loose ones.
	 *
	 * @since  1.0.0
	 * @param  Plugin $plugin The main plugin instance.
	 * @return string[] Post type names.
	 */
	public static function get_chosen_post_types( Plugin $plugin ): array {
		$values     = self::get_values( $plugin );
		$post_types = $values['own_duplication_post_types'];

		foreach ( $values['own_duplication_providers'] as $provider_id ) {
			$post_types = array_merge( $post_types, PostTypeRegister::get_post_types( $provider_id ) );
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Gives every choice a value of the right type.
	 *
	 * @since  1.0.0
	 * @param  array $values The raw choices.
	 * @return array{own_duplication_providers: string[], own_duplication_post_types: string[]} The choices.
	 */
	private static function normalize( array $values ): array {
		return array(
			'own_duplication_providers'  => self::list_of_keys( $values['own_duplication_providers'] ?? array() ),
			'own_duplication_post_types' => self::list_of_keys( $values['own_duplication_post_types'] ?? array() ),
		);
	}

	/**
	 * Returns a list of clean, unique, non-empty keys.
	 *
	 * @since  1.0.0
	 * @param  mixed $items The raw list.
	 * @return string[] The keys.
	 */
	private static function list_of_keys( mixed $items ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $items ) ? $items : array() ) ) ) );
	}

	/**
	 * Outputs the tab: a checkbox per active plugin with post types, then one per loose post type.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Whether to show the defaults instead of the saved choices.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$values = self::get_values( $this->plugin, $defaults_only );

		$plugins = array();
		foreach ( PostTypeRegister::get_active() as $provider_id => $post_types ) {
			$plugins[] = $this->render_provider_checkbox( $provider_id, $post_types, $this->is_provider_chosen( $provider_id, $post_types, $values ) );
		}

		$loose = array();
		foreach ( $this->get_loose_post_types() as $post_type ) {
			$loose[] = $this->render_post_type_checkbox( $post_type, in_array( $post_type->name, $values['own_duplication_post_types'], true ) );
		}

		$rows = $this->render_row( esc_html__( 'Per plugin', 'core-functionality' ), implode( '', $plugins ) );
		if ( array() !== $loose ) {
			$rows .= $this->render_row( esc_html__( 'Other content types', 'core-functionality' ), implode( '', $loose ) );
		}

		echo '<table class="form-table" role="presentation">' . $rows . '</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	/**
	 * Returns whether a plugin counts as chosen: chosen itself, or (from choices saved per post type
	 * before) one of its post types.
	 *
	 * @since  1.0.0
	 * @param  string   $provider_id The plugin id.
	 * @param  string[] $post_types  Its post types.
	 * @param  array    $values      The choices.
	 * @return bool True when it is chosen.
	 */
	private function is_provider_chosen( string $provider_id, array $post_types, array $values ): bool {
		return in_array( $provider_id, $values['own_duplication_providers'], true )
			|| array() !== array_intersect( $post_types, $values['own_duplication_post_types'] );
	}

	/**
	 * Returns the content types with an admin screen that belong to no known plugin.
	 *
	 * @since  1.0.0
	 * @return WP_Post_Type[] The post types, keyed by name.
	 */
	private function get_loose_post_types(): array {
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		unset( $post_types['attachment'], $post_types['wp_block'], $post_types['wp_navigation'], $post_types['wp_template'], $post_types['wp_template_part'] );

		return array_filter(
			$post_types,
			fn ( WP_Post_Type $post_type ) => null === PostTypeRegister::get_provider( $post_type->name )
		);
	}

	/**
	 * Returns one row of the settings table.
	 *
	 * @since  1.0.0
	 * @param  string $label   The row label, already escaped.
	 * @param  string $choices The checkboxes (HTML).
	 * @return string The row.
	 */
	private function render_row( string $label, string $choices ): string {
		return '<tr><th scope="row">' . $label . '</th><td><fieldset>' . $choices . '</fieldset></td></tr>';
	}

	/**
	 * Returns the checkbox of a plugin, with its post types as a hint.
	 *
	 * @since  1.0.0
	 * @param  string   $provider_id The plugin id.
	 * @param  string[] $post_types  Its post types.
	 * @param  bool     $checked     Whether it is checked.
	 * @return string The checkbox.
	 */
	private function render_provider_checkbox( string $provider_id, array $post_types, bool $checked ): string {
		$name  = esc_attr( self::FIELD . '[own_duplication_providers][]' );
		$value = esc_attr( $provider_id );
		$label = esc_html( PostTypeRegister::get_name( $provider_id ) );
		$types = esc_html( implode( ', ', $post_types ) );

		return '<label style="display:block"><input type="checkbox" name="' . $name . '" value="' . $value . '"' . checked( $checked, true, false ) . ' /> ' . $label . ' <code>' . $types . '</code></label>';
	}

	/**
	 * Returns the checkbox of one content type, with its label and code name (labels can repeat).
	 *
	 * @since  1.0.0
	 * @param  WP_Post_Type $post_type The post type.
	 * @param  bool         $checked   Whether it is checked.
	 * @return string The checkbox.
	 */
	private function render_post_type_checkbox( WP_Post_Type $post_type, bool $checked ): string {
		$name  = esc_attr( self::FIELD . '[own_duplication_post_types][]' );
		$value = esc_attr( $post_type->name );
		$label = esc_html( $post_type->labels->name );
		$code  = esc_html( $post_type->name );

		return '<label style="display:block"><input type="checkbox" name="' . $name . '" value="' . $value . '"' . checked( $checked, true, false ) . ' /> ' . $label . ' <code>' . $code . '</code></label>';
	}

	/**
	 * Validates the submitted form data before it is saved (nothing to check: only checkboxes).
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return bool|WP_Error True.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		return true;
	}

	/**
	 * Saves the submitted form data, as a whole.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return string|bool A success message, true, or false on failure.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		$input = $posted_data[ self::FIELD ] ?? array();
		$input = is_array( $input ) ? wp_unslash( $input ) : array();

		// Nothing checked sends nothing at all.
		$checked_providers  = self::list_of_keys( $input['own_duplication_providers'] ?? array() );
		$checked_post_types = self::list_of_keys( $input['own_duplication_post_types'] ?? array() );

		// What is not on the tab keeps its choice: inactive plugins, and loose types without a screen.
		// Loose types of a plugin on the tab are dropped: the plugin's checkbox decides for them now.
		$current        = self::get_values( $this->plugin );
		$shown_plugins  = array_keys( PostTypeRegister::get_active() );
		$hidden_plugins = array_diff( $current['own_duplication_providers'], $shown_plugins );
		$hidden_types   = array();
		foreach ( array_diff( $current['own_duplication_post_types'], array_keys( $this->get_loose_post_types() ) ) as $post_type ) {
			if ( ! in_array( PostTypeRegister::get_provider( $post_type ), $shown_plugins, true ) ) {
				$hidden_types[] = $post_type;
			}
		}

		$choices = array(
			'own_duplication_providers'  => array_merge( $hidden_plugins, $checked_providers ),
			'own_duplication_post_types' => array_merge( $hidden_types, $checked_post_types ),
		);

		return $this->plugin->settings->save( self::SETTINGS_KEY, self::normalize( $choices ) );
	}

	/**
	 * Returns the button under the form of this tab.
	 *
	 * @since  1.0.0
	 * @return array The action buttons.
	 */
	#[Override]
	public function get_action_buttons(): array {
		$label = esc_html__( 'Save', 'core-functionality' );

		return array(
			'save' => '<button type="submit" name="dwp_vault_matrix_submit" value="1" class="button button-primary button-large">' . $label . '</button>',
		);
	}
}
