=== Core Functionality ===
Contributors: hansschuijff
Tags: core-functionality
Requires at least: 7.0
Tested up to: 7.1.4
Requires PHP: 8.2
Stable tag: 1.1.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
A modular core functionality plugin build using OOP concepts and responding to the availability of plugins and themes in the environment to activate and de-activate parts of it's functionality according to the needs of modules and features.

= Features =

* Smart Timing Check: Timing of the loading of the plugin can be tuned in settings.
* Customizable: Uses settings and a settings page whenever needed to customize the functionality and content.
* Ability to synchronize: has builtin call when ready technology, so dependent plugins or themes can know when to start their functionality.
* Fully modular: Build so new features and modules can be added easily.
* Lightweight: Build to be efficient in resource usage.
* Fully Translatable: Built from the ground up to support internationalization. You can easily add your own language files using tools like Loco Translate (a full language template and a Dutch translation file are included).

== Installation ==
1. Upload the `core-functionality` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Mock question? =
Mock answer

== Changelog ==

= 1.1.1 =
* AI Access: abilities of other plugins that change the site or send e-mail without approval (Yoast SEO, WooCommerce, Jetpack Forms, WP Rocket, FluentCRM sending) are now blocked by Content Editing by default. It replaces the feature Hide Live Changes of 1.1.0, which never reached production.
* AI Access: new feature Other Changes Without Asking for Approval lifts that block. Off by default, with a warning when it is switched on and a list of the actions it allows on this site.
* AI Access: Rewrite Copies is now called Edit Published Content as a Draft; clearer descriptions for all AI Access and admin toolbar features.
* Feature Activation: every module card has an Explanation with the description of its features.
* A feature with an activation warning is never switched on automatically, also not after an update or a status change.

= 1.1.0 =
* AI Access: abilities of other plugins that change the live site or send e-mail can be hidden from MCP (feature Hide Live Changes; did not reach production, replaced in 1.1.1).
* Core functions: the plugin's own functions are decided in one place, each with one level, set on the Release Management tab.
* Push notices: the real API addresses of Telegram and Pushover, and the driver gets the settings the tab saves.
* A notice instead of a fatal error when the autoloader is missing, and a request to reinstall when the components register is missing.
* The feature scan only runs where the developer tools are allowed.
* Tested up to WordPress 7.1.4.

= 1.0.0 =
* Initial Release, a complete OOP based redesign of the previous core functionality plugin.