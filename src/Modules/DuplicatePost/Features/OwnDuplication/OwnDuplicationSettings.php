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
use DeWittePrins\CoreFunctionality\Modules\DuplicatePost\DuplicatePost;
use DeWittePrins\CoreFunctionality\Plugin;
use Override;
use WP_Error;
use WP_Post_Type;

use function __;
use function array_diff;
use function array_filter;
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
 * The Duplicate Post settings tab: the post types whose own plugin duplicates them, for which
 * Duplicate Post shows no links. Saved as a whole (an unchecked type stays unchecked); until the
 * first save the defaults file in settings/modules/duplicate-post/ applies.
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
		return __( 'Content types whose own plugin duplicates them, taking care of what belongs to them (such as the ticket products of an event or the lessons of a course). Duplicate Post shows no links for these, so there is one way to duplicate them. Duplicate Post itself stays enabled for them. Types without an admin screen (such as tickets) come from the defaults file.', 'core-functionality' );
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
	 * @return array{own_duplication_post_types: string[]} The choices.
	 */
	public static function get_values( Plugin $plugin, bool $defaults_only = false ): array {
		$module   = DuplicatePost::get_id();
		$defaults = $plugin->settings->get_default( self::SETTINGS_KEY, $module );
		$defaults = is_array( $defaults ) ? $defaults : array();
		$saved    = $defaults_only ? $defaults : $plugin->settings->get_whole( self::SETTINGS_KEY, $module, $defaults );

		return self::normalize( ( is_array( $saved ) ? $saved : array() ) + $defaults );
	}

	/**
	 * Gives every choice a value of the right type.
	 *
	 * @since  1.0.0
	 * @param  array $values The raw choices.
	 * @return array{own_duplication_post_types: string[]} The choices.
	 */
	private static function normalize( array $values ): array {
		$post_types = is_array( $values['own_duplication_post_types'] ?? null ) ? $values['own_duplication_post_types'] : array();

		return array(
			'own_duplication_post_types' => array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) ),
		);
	}

	/**
	 * Outputs the tab: a checkbox per content type.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Whether to show the defaults instead of the saved choices.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$chosen = self::get_values( $this->plugin, $defaults_only )['own_duplication_post_types'];

		$choices = array();
		foreach ( $this->get_post_types() as $post_type ) {
			$choices[] = $this->checkbox( $post_type, in_array( $post_type->name, $chosen, true ) );
		}

		$label = esc_html__( 'Own duplication', 'core-functionality' );
		$list  = implode( '', $choices );

		echo '<table class="form-table" role="presentation"><tr><th scope="row">' . $label . '</th><td><fieldset>' . $list . '</fieldset></td></tr></table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	/**
	 * Returns the content types that can be chosen: those with an admin screen.
	 *
	 * @since  1.0.0
	 * @return WP_Post_Type[] The post types.
	 */
	private function get_post_types(): array {
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		unset( $post_types['attachment'], $post_types['wp_block'], $post_types['wp_navigation'], $post_types['wp_template'], $post_types['wp_template_part'] );

		return $post_types;
	}

	/**
	 * Returns the checkbox of one content type, with its label and code name (labels can repeat,
	 * such as "Berichten" for posts and for Sensei's messages).
	 *
	 * @since  1.0.0
	 * @param  WP_Post_Type $post_type The post type.
	 * @param  bool         $checked   Whether it is checked.
	 * @return string The checkbox.
	 */
	private function checkbox( WP_Post_Type $post_type, bool $checked ): string {
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

		// Nothing checked sends no post types at all.
		$checked = is_array( $input['own_duplication_post_types'] ?? null ) ? $input['own_duplication_post_types'] : array();

		// Types without an admin screen (such as tickets) are not on the tab; keep them as they were.
		$current = self::get_values( $this->plugin )['own_duplication_post_types'];
		$hidden  = array_diff( $current, array_keys( $this->get_post_types() ) );

		$input['own_duplication_post_types'] = array_merge( $hidden, $checked );

		return $this->plugin->settings->save( self::SETTINGS_KEY, self::normalize( $input ) );
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
