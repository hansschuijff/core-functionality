<?php
/**
 * Plugin Environment Configuration, translate internal plugin and theme tokens
 * to their names in WordPress.
 *
 * Returns a static dictionary mapping abstract dependency identification keys
 * to their physical active WordPress plugin directory and file basenames.
 *
 * @return array{
 *     plugins: array<string, string|string[]>,                    // array<plugin_id, basename or basenames>
 *     themes: array<string, array{name: string, author?: string}> // array<theme_id, array{name, author(optioneel)}>
 * }
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'plugins' => array(
		// Every key equals the get_id() of a guard class in src/Environment/Plugins/ (the class name in
		// kebab-case). Basenames marked "confirm" are not installed on the local site and are unverified.
		// A plugin whose folder name differs between sites gets a list; the installed one is used.

		// Core & Common Utilities.
		'yoast-seo'                               => 'wordpress-seo/wp-seo.php',
		'jetpack'                                 => 'jetpack/jetpack.php',
		'gravity-forms'                           => 'gravityforms/gravityforms.php',
		'carbon-fields'                           => 'carbon-fields/carbon-fields.php',
		'carbon-fields-helper'                    => 'carbon-fields-helper/carbon-fields-helper.php',
		'searchwp'                                => 'searchwp/searchwp.php',
		'debug-toolkit'                           => array( '__debug-toolkit/debug-toolkit.php', '_debug-toolkit/debug-toolkit.php' ),
		'broken-link-checker'                     => 'broken-link-checker/broken-link-checker.php',
		'fluent-smtp'                             => 'fluent-smtp/fluent-smtp.php',
		'loco-translate'                          => 'loco-translate/loco.php',
		'seo-redirection-premium'                 => 'seo-redirection-premium/seo-redirection-premium.php',
		'updraft-plus'                            => 'updraftplus/updraftplus.php',
		'wp-forms'                                => 'wpforms/wpforms.php',
		'redis-cache'                             => 'redis-cache/redis-cache.php',
		'beaver-builder'                          => 'bb-plugin/fl-builder.php', // The Pro version; the lite version is beaver-builder-lite-version/fl-builder.php.
		'spectra-legacy'                          => 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php',
		'spectra-blocks'                          => 'spectra-blocks/spectra-blocks.php',
		'wp-server-stats'                         => 'wp-server-stats/wp-server-stats.php',
		'reader-experience'                       => 'reader-experience/reader-experience.php',
		'duplicate-post'                          => 'duplicate-post/duplicate-post.php',
		'akismet'                                 => 'akismet/akismet.php',
		'ai'                                      => 'ai/ai.php',
		'site-kit'                                => 'google-site-kit/google-site-kit.php',
		'cookie-yes'                              => 'cookie-law-info/cookie-law-info.php',
		'tablepress'                              => 'tablepress/tablepress.php',
		'code-snippets'                           => array( 'code-snippets-pro/code-snippets.php', 'code-snippets/code-snippets.php' ),
		'wp-crontrol'                             => 'wp-crontrol/wp-crontrol.php',
		'action-scheduler'                        => 'action-scheduler/action-scheduler.php',
		'wp-rocket'                               => 'wp-rocket/wp-rocket.php',
		'yoast-seo-premium'                       => 'wordpress-seo-premium/wp-seo-premium.php',
		'fluent-crm'                              => 'fluent-crm/fluent-crm.php',
		'users-insights'                          => 'users-insights/users-insights.php',
		'temporary-login'                         => 'temporary-login-without-password/temporary-login-without-password.php',
		'username-updater'                        => 'username-updater/init.php',
		'countdown-timer'                         => 'countdown-time/plugin.php',
		'top-ten'                                 => 'top-10/top-10.php',
		'yarpp'                                   => 'yet-another-related-posts-plugin/yarpp.php',
		'simple-comment-editing'                  => 'simple-comment-editing/index.php',
		'add-anchor-links'                        => 'add-anchor-links/add-anchor-links.php',
		'footable'                                => 'footable/footable.php',
		'otter-blocks'                            => 'otter-blocks/otter-blocks.php',

		// Updates and licences.
		'git-updater'                             => 'git-updater/git-updater.php',
		'git-remote-updater'                      => 'git-remote-updater/git-remote-updater.php',
		'festinger-vault'                         => 'festinger-vault/festingervault.php', // confirm: local site also has festingervault/ (v5 beta).
		'nobuna'                                  => 'nobuna-plugins-manager/nobuna-plugins-manager.php',

		// WooCommerce Core & Extensions.
		'woocommerce'                             => 'woocommerce/woocommerce.php',
		'activewoo-api-helper'                    => 'activewoo-api-helper/activewoo-api-helper.php',
		'woocommerce-active-campaign-integration' => 'woocommerce-active-campaign/woocommerce-active-campaign.php',
		'mollie-payments-for-woocommerce'         => 'mollie-payments-for-woocommerce/mollie-payments-for-woocommerce.php',
		'woocommerce-pdf-invoices'                => 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php',
		'woocommerce-subscriptions'               => 'woocommerce-subscriptions/woocommerce-subscriptions.php',
		'woocommerce-customer-order-csv-export'   => 'woocommerce-customer-order-csv-export/woocommerce-customer-order-csv-export.php',
		'woocommerce-pre-orders'                  => 'woocommerce-pre-orders/woocommerce-pre-orders.php',
		'woocommerce-product-addons'              => 'woocommerce-product-addons/woocommerce-product-addons.php',
		'woocommerce-smart-coupons'               => 'woocommerce-smart-coupons/woocommerce-smart-coupons.php',
		'automatewoo'                             => 'automatewoo/automatewoo.php',
		'woocommerce-chained-products'            => 'woocommerce-chained-products/woocommerce-chained-products.php',
		'autocomplete-woocommerce-orders'         => 'autocomplete-woocommerce-orders/autocomplete-woocommerce-orders.php',
		'genesis-connect-woocommerce'             => 'genesis-connect-woocommerce/genesis-connect-woocommerce.php',
		'aelia-eu-vat-assistant'                  => 'woocommerce-eu-vat-assistant/woocommerce-eu-vat-assistant.php',
		'aelia-foundation-classes'                => 'wc-aelia-foundation-classes/wc-aelia-foundation-classes.php',
		'eu-vat-compliance'                       => 'woocommerce-eu-vat-compliance/eu-vat-compliance.php', // The free version.
		'wp-menu-cart'                            => 'woocommerce-menu-bar-cart/wp-menu-cart.php',
		'extended-coupon-features'                => 'woocommerce-auto-added-coupons/woocommerce-jos-autocoupon.php',

		// The Events Calendar Stack.
		'the-events-calendar'                     => 'the-events-calendar/the-events-calendar.php',
		'the-events-calendar-pro'                 => 'events-calendar-pro/events-calendar-pro.php',
		'event-tickets'                           => 'event-tickets/event-tickets.php',
		'event-tickets-plus'                      => 'event-tickets-plus/event-tickets-plus.php',
		'schema-glue-yoast-tec'                   => 'schema-glue-for-yoast-the-events-calendar/schema-glue.php',

		// Sensei LMS Stack.
		'sensei-lms'                              => 'sensei-lms/sensei-lms.php',
		'sensei-pro'                              => 'woothemes-sensei/woothemes-sensei.php', // Sensei Pro (WC Paid Courses).
		'sensei-content-drip'                     => 'sensei-content-drip/sensei-content-drip.php',
		'sensei-paid-courses'                     => 'sensei-paid-courses/sensei-paid-courses.php',
		'sensei-certificates'                     => 'sensei-certificates/woothemes-sensei-certificates.php',
		'sensei-post-to-course'                   => 'sensei-post-to-course/sensei-post-to-course.php',

		// Media and images.
		'instant-images'                          => 'instant-images/instant-images.php',
		'tiny-png'                                => 'tiny-compress-images/tiny-compress-images.php',
		'quick-featured-images'                   => 'quick-featured-images/quick-featured-images.php',
		'regenerate-thumbnails'                   => 'regenerate-thumbnails/regenerate-thumbnails.php',
		'wp-original-media-path'                  => 'wp-original-media-path/wp_original_media_path.php',

		// Social & Feeds.
		'scriptless-social-sharing'               => 'scriptless-social-sharing/scriptless-social-sharing.php',
		'shared-counts'                           => 'shared-counts/shared-counts.php',
		'send-images-to-rss'                      => 'send-images-rss/send-images-rss.php',

		// Genesis Utility Plugins.
		'display-featured-image-genesis'          => 'display-featured-image-genesis/display-featured-image-genesis.php',
		'genesis-simple-faq'                      => 'genesis-simple-faq/genesis-simple-faq.php',
		'be-like-content'                         => array( 'be-like-content/be-like-content.php', 'BE-Like-Content/be-like-content.php' ),
		'genesis-blocks-pro'                      => 'genesis-page-builder/genesis-page-builder.php',
	),
	'themes'  => array(
		'any-genesis' => array(
			'name'   => 'Genesis',
			'author' => 'StudioPress',
		),
		'essence-pro' => array(
			'name'   => 'Essence Pro',
			'author' => 'StudioPress',
		),
	),
);
