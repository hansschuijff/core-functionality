<?php
/**
 * Duplicate Post Settings Tab.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\DuplicatePost\Features\HideLinks
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\DuplicatePost\Features\HideLinks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\SettingsExplanation;
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
use function sprintf;
use function wp_unslash;

/**
 * Class HideLinksSettings
 *
 * The Duplicate Post settings tab: the plugins (and loose post types) that duplicate their content
 * themselves, for which Duplicate Post shows no links. A plugin stands for all the post types its
 * guard names (PostTypeRegister); post types of no known plugin are chosen one by one. Saved as a
 * whole (an unchecked choice stays unchecked); until the first save the defaults file in
 * settings/modules/duplicate-post/ applies.
 *
 * @since 1.0.0
 */
class HideLinksSettings implements SettingsSectionInterface {

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
		return __( 'Hide Yoast duplicate links', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Hide the duplicate links of Yoast Duplicate Post for content that its own plugin duplicates better, so there is one way to duplicate it.', 'core-functionality' );
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
	 * @return array{hidden_providers: string[], hidden_post_types: string[]} The choices.
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
		$post_types = $values['hidden_post_types'];

		foreach ( $values['hidden_providers'] as $provider_id ) {
			$post_types = array_merge( $post_types, PostTypeRegister::get_post_types( $provider_id ) );
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Gives every choice a value of the right type.
	 *
	 * @since  1.0.0
	 * @param  array $values The raw choices.
	 * @return array{hidden_providers: string[], hidden_post_types: string[]} The choices.
	 */
	private static function normalize( array $values ): array {
		return array(
			'hidden_providers'  => self::list_of_keys( $values['hidden_providers'] ?? array() ),
			'hidden_post_types' => self::list_of_keys( $values['hidden_post_types'] ?? array() ),
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
			$loose[] = $this->render_post_type_checkbox( $post_type, in_array( $post_type->name, $values['hidden_post_types'], true ) );
		}

		$rows = $this->render_row( esc_html__( 'Content of these plugins', 'core-functionality' ), implode( '', $plugins ) );
		if ( array() !== $loose ) {
			$rows .= $this->render_row( esc_html__( 'Loose content types', 'core-functionality' ), implode( '', $loose ) );
		}

		$heading = esc_html__( 'Hide the duplicate links of Yoast for', 'core-functionality' );

		echo $this->render_explanation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo '<h3>' . $heading . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '<table class="form-table" role="presentation">' . $rows . '</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	/**
	 * Returns the collapsed explanation of the tab, with a side note about the AI assistant.
	 *
	 * @since  1.0.0
	 * @return string The explanation (HTML).
	 */
	private function render_explanation(): string {
		return SettingsExplanation::render(
			__( 'Explanation', 'core-functionality' ),
			array(
				__( 'Some plugins have a duplicate function of their own that also copies what belongs to their content, such as the ticket products of an event or the lessons of a course. Yoast Duplicate Post only copies the item itself. That makes it always usable to change published content in a draft copy, but not always to make new content by cloning an existing item.', 'core-functionality' ),
				__( 'Tick the plugins whose own duplicate function you use. Yoast then shows no duplicate links and buttons for their content in the lists and the editor, so there is one way to duplicate it. Choosing a plugin covers all its content types, also those without a screen of their own (such as tickets).', 'core-functionality' ),
				__( 'Yoast Duplicate Post itself sets per content type whether it is switched on. This tab refines that per plugin or group of content types: the content types stay switched on in Yoast, only the links are hidden.', 'core-functionality' ),
			),
			__( 'If you use an AI assistant (AI Access), it still uses Yoast Duplicate Post in the background to change published content in a copy. Hiding the links here does not change that.', 'core-functionality' )
		);
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
		return in_array( $provider_id, $values['hidden_providers'], true )
			|| array() !== array_intersect( $post_types, $values['hidden_post_types'] );
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
	 * Returns the checkbox of a plugin, with its content types as a small hint under it.
	 *
	 * @since  1.0.0
	 * @param  string   $provider_id The plugin id.
	 * @param  string[] $post_types  Its post types.
	 * @param  bool     $checked     Whether it is checked.
	 * @return string The checkbox.
	 */
	private function render_provider_checkbox( string $provider_id, array $post_types, bool $checked ): string {
		$name  = esc_attr( self::FIELD . '[hidden_providers][]' );
		$value = esc_attr( $provider_id );
		$label = esc_html( PostTypeRegister::get_name( $provider_id ) );
		/* translators: %s: post type names, separated by commas. */
		$hint = esc_html( sprintf( __( 'Content types: %s', 'core-functionality' ), implode( ', ', $post_types ) ) );

		return '<div class="dwp-cf-choice">'
			. '<label><input type="checkbox" name="' . $name . '" value="' . $value . '"' . checked( $checked, true, false ) . ' /> ' . $label . '</label>'
			. '<span class="dwp-cf-choice-hint">' . $hint . '</span>'
			. '</div>';
	}

	/**
	 * Returns the checkbox of one content type, with its code name as a small hint (labels can repeat).
	 *
	 * @since  1.0.0
	 * @param  WP_Post_Type $post_type The post type.
	 * @param  bool         $checked   Whether it is checked.
	 * @return string The checkbox.
	 */
	private function render_post_type_checkbox( WP_Post_Type $post_type, bool $checked ): string {
		$name  = esc_attr( self::FIELD . '[hidden_post_types][]' );
		$value = esc_attr( $post_type->name );
		$label = esc_html( $post_type->labels->name );
		/* translators: %s: a post type name. */
		$hint = esc_html( sprintf( __( 'Content type: %s', 'core-functionality' ), $post_type->name ) );

		return '<div class="dwp-cf-choice">'
			. '<label><input type="checkbox" name="' . $name . '" value="' . $value . '"' . checked( $checked, true, false ) . ' /> ' . $label . '</label>'
			. '<span class="dwp-cf-choice-hint">' . $hint . '</span>'
			. '</div>';
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
		$checked_providers  = self::list_of_keys( $input['hidden_providers'] ?? array() );
		$checked_post_types = self::list_of_keys( $input['hidden_post_types'] ?? array() );

		// What is not on the tab keeps its choice: inactive plugins, and loose types without a screen.
		// Loose types of a plugin on the tab are dropped: the plugin's checkbox decides for them now.
		$current        = self::get_values( $this->plugin );
		$shown_plugins  = array_keys( PostTypeRegister::get_active() );
		$hidden_plugins = array_diff( $current['hidden_providers'], $shown_plugins );
		$hidden_types   = array();
		foreach ( array_diff( $current['hidden_post_types'], array_keys( $this->get_loose_post_types() ) ) as $post_type ) {
			if ( ! in_array( PostTypeRegister::get_provider( $post_type ), $shown_plugins, true ) ) {
				$hidden_types[] = $post_type;
			}
		}

		$choices = array(
			'hidden_providers'  => array_merge( $hidden_plugins, $checked_providers ),
			'hidden_post_types' => array_merge( $hidden_types, $checked_post_types ),
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
