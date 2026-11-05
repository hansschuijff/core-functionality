=== Core Functionality ===
Contributors: hansschuijff
Tags: core-functionality
Requires at least: 7.0
Tested up to: 7.1.4
Requires PHP: 8.2
Stable tag: 1.0.0
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

= 1.0.0 =
* Initial Release, a complete OOP based redesign of the previous core functionality plugin.
* AI Access: abilities of other plugins that change the live site or send e-mail are hidden from MCP (feature Hide Live Changes).