<?php
/**
 * AI Content Editing Feature.
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

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\CreateDraft;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\FindContent;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\GetContent;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\ListTerms;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\SubmitForReview;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\UpdateDraft;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\AbilityInterface;
use Override;

use function __;
use function add_action;
use function apply_filters;
use function is_array;
use function wp_register_ability;
use function wp_register_ability_category;

/**
 * Class ContentEditing
 *
 * Lets an AI assistant find, read and write content through the WordPress Abilities API (and so
 * through the MCP Adapter), without ever publishing it. Other features add content types, extra
 * fields and abilities through filters; the settings tab switches content types on and off.
 *
 * @since 1.0.0
 */
class ContentEditing extends AbstractFeature {

	/**
	 * The ability category of the content abilities.
	 *
	 * @var string
	 */
	public const ABILITY_CATEGORY = 'content-editing';

	/**
	 * The shared content services, once made.
	 *
	 * @var ContentServices|null
	 */
	private ?ContentServices $services = null;

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'ai_access_content_editing',
			name:        __( 'Content Editing', 'core-functionality' ),
			description: __( 'An AI assistant may find, read and write drafts of posts, pages and other content types. It never publishes; you do.', 'core-functionality' ),
		);
	}

	/**
	 * Adds the hooks of this feature. The abilities registry is built on first use, after init,
	 * so these hooks are in time.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		add_action(
			'core-functionality/register-settings-sections',
			function ( $settings_factory ) {
				$settings_factory->register_section( ContentEditingSettings::class );
			}
		);
	}

	/**
	 * Registers the ability category of the content abilities.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_category(): void {
		wp_register_ability_category(
			self::ABILITY_CATEGORY,
			array(
				'label'       => __( 'Content editing', 'core-functionality' ),
				'description' => __( 'Finding, reading and writing drafts of site content.', 'core-functionality' ),
			)
		);
	}

	/**
	 * Registers the content abilities.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_abilities(): void {
		foreach ( $this->get_abilities() as $ability ) {
			wp_register_ability( $ability->get_name(), $ability->get_args() );
		}
	}

	/**
	 * Returns the abilities, including those other features add.
	 *
	 * @since  1.0.0
	 * @return AbilityInterface[] The abilities.
	 */
	private function get_abilities(): array {
		$services = $this->get_services();

		$defaults = array(
			new FindContent( $services ),
			new GetContent( $services ),
			new ListTerms( $services ),
			new CreateDraft( $services ),
			new UpdateDraft( $services ),
			new SubmitForReview( $services ),
		);

		/**
		 * Filters the content abilities an AI assistant gets.
		 *
		 * @since 1.0.0
		 * @param AbilityInterface[] $abilities The abilities.
		 * @param ContentServices    $services  The shared content services, for new abilities.
		 */
		$abilities = apply_filters( 'core-functionality/ai-access-abilities', $defaults, $services );

		$valid = array();
		foreach ( is_array( $abilities ) ? $abilities : $defaults as $ability ) {
			if ( $ability instanceof AbilityInterface ) {
				$valid[] = $ability;
			}
		}

		return $valid;
	}

	/**
	 * Returns the shared content services, made once.
	 *
	 * @since  1.0.0
	 * @return ContentServices The services.
	 */
	private function get_services(): ContentServices {
		if ( null === $this->services ) {
			$types    = new ContentTypes( $this->plugin );
			$handlers = new FieldHandlers();

			$this->services = new ContentServices(
				types:    $types,
				handlers: $handlers,
				reader:   new ContentReader( $types, $handlers ),
				writer:   new ContentWriter( $types, $handlers ),
			);
		}

		return $this->services;
	}
}
