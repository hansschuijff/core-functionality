<?php
/**
 * AI Access Settings Tab.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\AdminSettingsPage;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Sections\FeatureActivationSettings;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\SettingsExplanation;
use DeWittePrins\CoreFunctionality\Environment\Registers\PostTypeRegister;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\AiAccess;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\ContentTypeProfileInterface;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\UnapprovedChanges\UnapprovedChanges;
use DeWittePrins\CoreFunctionality\Plugin;
use Override;
use WP_Error;

use function __;
use function add_query_arg;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_post_type_object;
use function implode;
use function is_array;
use function is_string;
use function menu_page_url;
use function plugins_url;
use function sanitize_key;
use function sprintf;
use function wp_unslash;

/**
 * Class ContentEditingSettings
 *
 * The AI Access settings tab: per content type whether an AI assistant may use it, and whether
 * it may change published items of that type in a Rewrite & Republish copy. A type without a
 * saved choice follows its profile's defaults.
 *
 * @since 1.0.0
 */
class ContentEditingSettings implements SettingsSectionInterface {

	/**
	 * The settings key of all choices of this tab.
	 *
	 * @var string
	 */
	public const SETTINGS_KEY = 'ai-access-settings';

	/**
	 * The name of the form field array.
	 *
	 * @var string
	 */
	private const FIELD = 'dwp_cf_ai';

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
		return 'cf-ai-access-settings';
	}

	/**
	 * Returns the dashicon class shown next to the tab title.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-superhero-alt';
	}

	/**
	 * Returns the title of this settings tab.
	 *
	 * @since  1.0.0
	 * @return string The tab title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Settings', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Choose per content type what an AI assistant (such as Claude) may do on this site. It never publishes or deletes: you decide what goes live.', 'core-functionality' );
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
	 * Returns the scripts this tab needs: the one that keeps the levels of a row in steps.
	 *
	 * @since  1.0.0
	 * @return array The scripts to enqueue.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array(
			'dwp-cf-ai-access-settings-js' => plugins_url( 'assets/js/ai-access-settings.js', __FILE__ ),
		);
	}

	/**
	 * Returns the saved choices, completed with the defaults and cleaned up.
	 *
	 * @since  1.0.0
	 * @param  Plugin $plugin        The main plugin instance.
	 * @param  bool   $defaults_only Whether to return the defaults instead of the saved choices.
	 * @return array{types: array<string, array{enabled?: bool, rewrite?: bool}>} The choices.
	 */
	public static function get_values( Plugin $plugin, bool $defaults_only = false ): array {
		$module   = AiAccess::get_id();
		$defaults = $plugin->settings->get_default( self::SETTINGS_KEY, $module );
		$defaults = is_array( $defaults ) ? $defaults : array();
		$saved    = $defaults_only ? $defaults : $plugin->settings->get_whole( self::SETTINGS_KEY, $module, $defaults );

		return self::normalize( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Gives every choice a value of the right type.
	 *
	 * @since  1.0.0
	 * @param  array $values The raw choices.
	 * @return array{types: array<string, array{read?: bool, write?: bool, rewrite?: bool}>} The choices.
	 */
	private static function normalize( array $values ): array {
		$types = array();
		foreach ( is_array( $values['types'] ?? null ) ? $values['types'] : array() as $post_type => $choices ) {
			if ( is_string( $post_type ) && is_array( $choices ) ) {
				$types[ sanitize_key( $post_type ) ] = self::normalize_levels( $choices );
			}
		}

		return array( 'types' => $types );
	}

	/**
	 * Returns the levels of one content type. A level that is on includes the lower ones. The
	 * former single choice "enabled" (before October 2026) stands for reading and drafts.
	 *
	 * @since  1.0.0
	 * @param  array $choices The raw choices of the type.
	 * @return array<string, bool> Level => on, only for the levels that were chosen.
	 */
	private static function normalize_levels( array $choices ): array {
		$levels = array();
		if ( isset( $choices['enabled'] ) ) {
			$levels[ ContentTypes::LEVEL_READ ]  = ! empty( $choices['enabled'] );
			$levels[ ContentTypes::LEVEL_WRITE ] = ! empty( $choices['enabled'] );
		}

		foreach ( ContentTypes::LEVELS as $level ) {
			if ( isset( $choices[ $level ] ) ) {
				$levels[ $level ] = ! empty( $choices[ $level ] );
			}
		}

		// A higher level that is on switches the lower ones on.
		if ( ! empty( $levels[ ContentTypes::LEVEL_REWRITE ] ) ) {
			$levels[ ContentTypes::LEVEL_WRITE ] = true;
		}
		if ( ! empty( $levels[ ContentTypes::LEVEL_WRITE ] ) ) {
			$levels[ ContentTypes::LEVEL_READ ] = true;
		}

		return $levels;
	}

	/**
	 * Outputs the tab: per plugin a group, in it a card per content type with its levels.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Whether to show the defaults instead of the saved choices.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$types   = new ContentTypes( $this->plugin );
		$choices = self::get_values( $this->plugin, $defaults_only )['types'];

		$groups = array();
		foreach ( $this->group_by_plugin( $types->get_all() ) as $plugin_name => $profiles ) {
			$groups[] = $this->render_plugin_group( $plugin_name, $profiles, $choices );
		}

		echo $this->render_unapproved_changes_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo $this->render_explanation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo '<div class="dwp-cf-ai-levels">' . implode( '', $groups ) . '</div><!-- closes dwp-cf-ai-levels -->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	/**
	 * Returns a warning card while the block on other plugins' unapproved actions is lifted (by
	 * "Other Changes Without Asking for Approval" or by code): which actions the AI assistant may
	 * then use directly, and where to switch it off. Empty while the block is on (Hans, November
	 * 2026: the permission is set here, so it should be visible here).
	 *
	 * @since  1.1.2
	 * @return string The card (HTML), or ''.
	 */
	private function render_unapproved_changes_notice(): string {
		if ( UnapprovedAbilitiesBlocker::is_blocking() ) {
			return '';
		}

		/* translators: %s: feature name. */
		$title_html   = esc_html( sprintf( __( '%s is switched on', 'core-functionality' ), UnapprovedChanges::get_name() ) );
		$intro_html   = esc_html__( 'The AI assistant may also use the actions below. They apply on the site at once, without you being able to review or approve them first. Publishing posts, pages, events and courses stays with you.', 'core-functionality' );
		$actions_html = $this->render_unapproved_actions_list();
		$link_url     = esc_url( add_query_arg( array( 'section' => FeatureActivationSettings::get_id() ), menu_page_url( AdminSettingsPage::SLUG, false ) ) );
		$link_html    = esc_html__( 'Switch this off on the Feature Activation tab', 'core-functionality' );

		return '<section class="dwp-cf-warning-card">'
			. '<h3><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . $title_html . '</h3>'
			. '<p>' . $intro_html . '</p>'
			. $actions_html
			. '<p><a href="' . $link_url . '">' . $link_html . '</a></p>'
			. '</section><!-- closes dwp-cf-warning-card -->';
	}

	/**
	 * Returns the actions the AI assistant may use directly while the block is lifted, one list item
	 * per plugin.
	 *
	 * @since  1.1.2
	 * @return string The list (HTML).
	 */
	private function render_unapproved_actions_list(): string {
		$items = array();
		foreach ( UnapprovedAbilitiesBlocker::get_action_lines() as $line ) {
			$items[] = '<li>' . esc_html( $line ) . '</li>';
		}

		return '<ul>' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Returns the collapsed explanation of the tab: what each level allows, and why published
	 * content is changed in a copy.
	 *
	 * @since  1.0.0
	 * @return string The explanation (HTML).
	 */
	private function render_explanation(): string {
		return SettingsExplanation::render(
			__( 'Explanation', 'core-functionality' ),
			array(
				__( 'These options let an AI assistant help you make the content of this site. The assistant logs in as a WordPress user with an application password and never has more rights than that user. What you switch on below narrows that down to these content types and these actions.', 'core-functionality' ),
				__( 'Read: the assistant may find and read items of the type, published ones too, for example as a source for a new text.', 'core-functionality' ),
				__( 'Make and change drafts: the assistant may make new drafts, change drafts and items pending review, and put them up for review. This covers everything that belongs to making content: text, layout in blocks, terms and fields. It never publishes and never deletes: you check, change and publish a draft, or throw it away.', 'core-functionality' ),
				__( 'Change published content: the assistant may change an item that is already published, in a copy (Rewrite & Republish of Yoast Duplicate Post). The original stays unchanged and online. Only when you publish the copy does it replace the original; until then you can still change the copy or throw it away.', 'core-functionality' ),
				__( 'Although changing published content this way should be safe, check that it works well for your content before you switch it on. When in doubt, have the assistant write a new draft and paste it into the existing content yourself.', 'core-functionality' ),
				__( 'The levels are steps: a level includes the ones before it.', 'core-functionality' ),
			)
		);
	}

	/**
	 * Groups the profiles by the plugin their post type belongs to, as the post type catalogue knows
	 * it; post types of no known plugin come last, under "Other".
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface[] $profiles The profiles, keyed by post type.
	 * @return array<string, ContentTypeProfileInterface[]> Plugin name => profiles.
	 */
	private function group_by_plugin( array $profiles ): array {
		$groups = array();
		$other  = array();

		foreach ( $profiles as $post_type => $profile ) {
			$provider_id = PostTypeRegister::get_provider( $post_type );
			if ( null === $provider_id ) {
				$other[ $post_type ] = $profile;
				continue;
			}

			$groups[ PostTypeRegister::get_name( $provider_id ) ][ $post_type ] = $profile;
		}

		if ( array() !== $other ) {
			$groups[ __( 'Other', 'core-functionality' ) ] = $other;
		}

		return $groups;
	}

	/**
	 * Returns the group of one plugin: its name, and a card per content type.
	 *
	 * @since  1.0.0
	 * @param  string                        $plugin_name The plugin's name.
	 * @param  ContentTypeProfileInterface[] $profiles    The profiles of its content types.
	 * @param  array                         $choices     The saved levels per post type.
	 * @return string The group (HTML).
	 */
	private function render_plugin_group( string $plugin_name, array $profiles, array $choices ): string {
		$name  = esc_html( $plugin_name );
		$cards = '';
		foreach ( $profiles as $post_type => $profile ) {
			$cards .= $this->render_type_card( $profile, $choices[ $post_type ] ?? array() );
		}

		return '<section class="dwp-cf-card-group"><h3>' . $name . '</h3>'
			. '<div class="dwp-cf-cards">' . $cards . '</div>'
			. '</section><!-- closes dwp-cf-card-group -->';
	}

	/**
	 * Returns the card of one content type: its name, and its levels under each other.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile.
	 * @param  array                       $saved   The saved levels of this type.
	 * @return string The card (HTML).
	 */
	private function render_type_card( ContentTypeProfileInterface $profile, array $saved ): string {
		$post_type = $profile->get_post_type();
		$object    = get_post_type_object( $post_type );
		$label     = esc_html( null !== $object ? $object->labels->name : $post_type );
		$code      = esc_html( $post_type );

		$choices = '';
		foreach ( ContentTypes::LEVELS as $level ) {
			$default  = ContentTypes::LEVEL_REWRITE === $level ? $profile->allows_rewrite_by_default() : true;
			$choices .= $this->render_level_checkbox( $post_type, $level, $saved[ $level ] ?? $default );
		}

		return '<fieldset class="dwp-cf-card">'
			. '<legend class="dwp-cf-card-title">' . $label . ' <code>' . $code . '</code></legend>'
			. '<div class="dwp-cf-card-choices">' . $choices . '</div>'
			. '</fieldset><!-- closes dwp-cf-card -->';
	}

	/**
	 * Returns the checkbox of one level with its label, and a hidden field that sends "0" when it is
	 * unchecked. The data attributes let the tab's script keep the levels of a card in steps.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @param  string $level     One of the ContentTypes::LEVEL_ constants.
	 * @param  bool   $checked   Whether it is checked.
	 * @return string The checkbox (HTML).
	 */
	private function render_level_checkbox( string $post_type, string $level, bool $checked ): string {
		$name       = esc_attr( self::FIELD . '[types][' . $post_type . '][' . $level . ']' );
		$type_attr  = esc_attr( $post_type );
		$level_attr = esc_attr( $level );
		$label      = esc_html( $this->get_level_name( $level ) );

		return '<input type="hidden" name="' . $name . '" value="0" />'
			. '<label><input type="checkbox" name="' . $name . '" value="1" data-type="' . $type_attr . '" data-level="' . $level_attr . '"' . checked( $checked, true, false ) . ' /> ' . $label . '</label>';
	}

	/**
	 * Returns the name of a level.
	 *
	 * @since  1.0.0
	 * @param  string $level One of the ContentTypes::LEVEL_ constants.
	 * @return string The name.
	 */
	private function get_level_name( string $level ): string {
		return match ( $level ) {
			ContentTypes::LEVEL_READ  => __( 'Read', 'core-functionality' ),
			ContentTypes::LEVEL_WRITE => __( 'Make and change drafts', 'core-functionality' ),
			default                   => __( 'Change published content', 'core-functionality' ),
		};
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
