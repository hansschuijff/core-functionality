<?php
/**
 * AI Hide Live Abilities Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\HideLiveAbilities
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\HideLiveAbilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;
use function add_filter;
use function apply_filters;
use function array_merge;
use function in_array;
use function is_array;

/**
 * Class HideLiveAbilities
 *
 * Hides abilities of other plugins from the MCP Adapter when they change the live site or send
 * e-mail without a step where the site owner can confirm, so an AI assistant cannot use them, whatever its role allows. This keeps the rule
 * "the assistant writes, the site owner publishes" also for abilities this plugin does not own.
 * Reading abilities stay available. Abilities that do not exist are simply never matched.
 *
 * @since 1.0.0
 */
class HideLiveAbilities extends AbstractFeature {

	/**
	 * Priority of the registration filter: late, so it wins over filters that expose abilities.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	private const FILTER_PRIORITY = 99;

	/**
	 * The hidden ability names, built once per request.
	 *
	 * @since 1.0.0
	 * @var   string[]|null
	 */
	private ?array $hidden_abilities = null;

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'ai_access_hide_live_abilities',
			name:        __( 'Hide Live Changes', 'core-functionality' ),
			description: __( 'An AI assistant cannot use abilities of other plugins that change the live site or send e-mail.', 'core-functionality' ),
		);
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'wp_register_ability_args', array( $this, 'hide_from_mcp' ), self::FILTER_PRIORITY, 2 );
	}

	/**
	 * Hides an ability from the MCP Adapter when it is on the list.
	 *
	 * @since  1.0.0
	 * @param  mixed  $args The registration arguments of the ability.
	 * @param  string $name The ability name.
	 * @return mixed The registration arguments.
	 */
	public function hide_from_mcp( mixed $args, string $name ): mixed {
		if ( ! is_array( $args ) || ! in_array( $name, $this->get_hidden_abilities(), true ) ) {
			return $args;
		}

		$args['meta']['mcp']['public'] = false;

		return $args;
	}

	/**
	 * Returns the names of the abilities that are hidden from the MCP Adapter.
	 *
	 * @since  1.0.0
	 * @return string[] The ability names.
	 */
	private function get_hidden_abilities(): array {
		if ( null !== $this->hidden_abilities ) {
			return $this->hidden_abilities;
		}

		$defaults = array_merge(
			$this->get_yoast_seo_abilities(),
			$this->get_woocommerce_abilities(),
			$this->get_jetpack_forms_abilities(),
			$this->get_wp_rocket_abilities(),
			$this->get_fluent_crm_abilities()
		);

		/**
		 * Filters the abilities of other plugins that an AI assistant may not use through MCP.
		 *
		 * @since 1.0.0
		 * @param string[] $abilities The ability names, such as 'yoast-seo/update-post-seo-data'.
		 */
		$abilities = apply_filters( 'core-functionality/ai-access-hidden-abilities', $defaults );

		$this->hidden_abilities = is_array( $abilities ) ? $abilities : $defaults;

		return $this->hidden_abilities;
	}

	/**
	 * Yoast SEO: changing the SEO data of a (published) item directly, and the site settings.
	 * The SEO fields of drafts and rewrite copies go through this plugin's own abilities.
	 *
	 * @since  1.0.0
	 * @return string[] The ability names.
	 */
	private function get_yoast_seo_abilities(): array {
		return array(
			'yoast-seo/update-post-seo-data',
			'yoast-seo/update-site-representation',
			'yoast-seo/set-llms-txt-status',
			'yoast-seo/set-open-graph-status',
			'yoast-seo/set-schema-aggregation-endpoint-status',
			'yoast-seo/set-schema-framework-status',
			'yoast-seo/set-x-card-status',
			'yoast-seo/set-xml-sitemap-status',
		);
	}

	/**
	 * WooCommerce: changing orders and products.
	 *
	 * @since  1.0.0
	 * @return string[] The ability names.
	 */
	private function get_woocommerce_abilities(): array {
		return array(
			'woocommerce/order-add-note',
			'woocommerce/order-update-status',
			'woocommerce/product-create',
			'woocommerce/product-update',
			'woocommerce/product-delete',
		);
	}

	/**
	 * Jetpack Forms: changing forms and their responses.
	 *
	 * @since  1.0.0
	 * @return string[] The ability names.
	 */
	private function get_jetpack_forms_abilities(): array {
		return array(
			'jetpack-forms/create-form',
			'jetpack-forms/delete-form',
			'jetpack-forms/update-response',
			'jetpack-forms/bulk-update-responses',
		);
	}

	/**
	 * WP Rocket: changing settings, clearing the cache and changing the performance monitoring.
	 *
	 * @since  1.0.0
	 * @return string[] The ability names.
	 */
	private function get_wp_rocket_abilities(): array {
		return array(
			'wp-rocket/set-option',
			'wp-rocket/clear-url-cache',
			'wp-rocket/clear-website-cache',
			'wp-rocket/add-page-insights',
			'wp-rocket/retest-page-insights',
			'wp-rocket/remove-page-insights',
		);
	}

	/**
	 * FluentCRM: sending e-mail and scheduling campaigns. Managing contacts, tags, lists, campaign
	 * drafts, sequences and automations stays available, to help with the CRM (Hans, November 2026);
	 * FluentCRM's own permissions still apply there.
	 *
	 * @since  1.0.0
	 * @return string[] The ability names.
	 */
	private function get_fluent_crm_abilities(): array {
		return array(
			'fluent-crm/send-test-email',
			'fluent-crm/send-email-to-contact',
			'fluent-crm/change-campaign-status',
		);
	}
}
