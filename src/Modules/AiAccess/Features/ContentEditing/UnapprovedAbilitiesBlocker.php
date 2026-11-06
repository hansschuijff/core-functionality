<?php
/**
 * Unapproved Abilities Blocker.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.1.1
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use function add_filter;
use function apply_filters;
use function array_merge;
use function in_array;
use function is_array;

/**
 * Class UnapprovedAbilitiesBlocker
 *
 * Hides abilities of other plugins from the MCP Adapter when they change the site or send e-mail
 * without a step where the site owner can review and approve the change first, so an AI assistant
 * cannot use them, whatever its role allows. This keeps the rule "the assistant writes, the site
 * owner publishes" also for abilities this plugin does not own. Reading abilities stay available;
 * abilities that do not exist are simply never matched.
 *
 * Part of Content Editing, so the block is on wherever AI Access works. The feature "Other Changes
 * Without Asking for Approval" lifts it (filter core-functionality/ai-access-hide-abilities).
 *
 * @since 1.1.1
 */
class UnapprovedAbilitiesBlocker {

	/**
	 * Priority of the registration filter: late, so it wins over filters that expose abilities.
	 *
	 * @since 1.1.1
	 * @var   int
	 */
	private const FILTER_PRIORITY = 99;

	/**
	 * The hidden ability names, built once per request.
	 *
	 * @since 1.1.1
	 * @var   string[]|null
	 */
	private static ?array $hidden_abilities = null;

	/**
	 * Adds the hook that hides the abilities.
	 *
	 * @since  1.1.1
	 * @return void
	 */
	public function launch(): void {
		add_filter( 'wp_register_ability_args', array( $this, 'hide_from_mcp' ), self::FILTER_PRIORITY, 2 );
	}

	/**
	 * Hides an ability from the MCP Adapter when it is on the list and the block is on.
	 *
	 * @since  1.1.1
	 * @param  mixed  $args The registration arguments of the ability.
	 * @param  string $name The ability name.
	 * @return mixed The registration arguments.
	 */
	public function hide_from_mcp( mixed $args, string $name ): mixed {
		if ( ! is_array( $args ) || ! self::is_blocking() || ! in_array( $name, self::get_hidden_abilities(), true ) ) {
			return $args;
		}

		$args['meta']['mcp']['public'] = false;

		return $args;
	}

	/**
	 * Returns whether the abilities on the list are hidden.
	 *
	 * @since  1.1.1
	 * @return bool True when they are hidden.
	 */
	private static function is_blocking(): bool {
		/**
		 * Filters whether abilities of other plugins that change the site without approval are hidden
		 * from MCP. The feature "Other Changes Without Asking for Approval" returns false.
		 *
		 * @since 1.1.1
		 * @param bool $hide Whether to hide them. Default true.
		 */
		return (bool) apply_filters( 'core-functionality/ai-access-hide-abilities', true );
	}

	/**
	 * Returns the names of the abilities that are hidden from the MCP Adapter while the block is on.
	 *
	 * @since  1.1.1
	 * @return string[] The ability names.
	 */
	public static function get_hidden_abilities(): array {
		if ( null !== self::$hidden_abilities ) {
			return self::$hidden_abilities;
		}

		$defaults = array_merge(
			self::get_yoast_seo_abilities(),
			self::get_woocommerce_abilities(),
			self::get_jetpack_forms_abilities(),
			self::get_wp_rocket_abilities(),
			self::get_fluent_crm_abilities()
		);

		/**
		 * Filters the abilities of other plugins that an AI assistant may not use through MCP.
		 *
		 * @since 1.1.0
		 * @param string[] $abilities The ability names, such as 'yoast-seo/update-post-seo-data'.
		 */
		$abilities = apply_filters( 'core-functionality/ai-access-hidden-abilities', $defaults );

		self::$hidden_abilities = is_array( $abilities ) ? $abilities : $defaults;

		return self::$hidden_abilities;
	}

	/**
	 * Yoast SEO: changing the SEO data of a (published) item directly, and the site settings.
	 * The SEO fields of drafts and rewrite copies go through this plugin's own abilities.
	 *
	 * @since  1.1.1
	 * @return string[] The ability names.
	 */
	private static function get_yoast_seo_abilities(): array {
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
	 * WooCommerce: changing orders and products. A published product cannot be changed in a draft
	 * first (Yoast Duplicate Post leaves products out when WooCommerce is active).
	 *
	 * @since  1.1.1
	 * @return string[] The ability names.
	 */
	private static function get_woocommerce_abilities(): array {
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
	 * @since  1.1.1
	 * @return string[] The ability names.
	 */
	private static function get_jetpack_forms_abilities(): array {
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
	 * @since  1.1.1
	 * @return string[] The ability names.
	 */
	private static function get_wp_rocket_abilities(): array {
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
	 * drafts, sequences and automations stays available, to help with the CRM and its migration;
	 * what to close there is decided after the migration (Hans, November 2026). FluentCRM's own
	 * permissions still apply.
	 *
	 * @since  1.1.1
	 * @return string[] The ability names.
	 */
	private static function get_fluent_crm_abilities(): array {
		return array(
			'fluent-crm/send-test-email',
			'fluent-crm/send-email-to-contact',
			'fluent-crm/change-campaign-status',
		);
	}
}
