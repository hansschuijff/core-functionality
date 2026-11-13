# Core Functionality

The site-specific functionality of De Witte Prins, independent of the theme, as a small microkernel.
The plugin finds its own modules and features and starts only those that fit the site: the active
plugins and theme, the server tier (development, staging, production), admin or front end, and what is
switched on under Settings → Core Functionality.

Requires WordPress 7.0 and PHP 8.2. Updates through [Git Updater](https://git-updater.com/) from the
GitHub releases. Architecture and decisions: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

This is the OOP successor of the legacy plugin, now called Core Functionality Legacy (folder
`core-functionality-legacy`). Both are installed side by side while features move over; for every
switched-on feature that took over a part of it, the legacy part steps aside (`src/Migration/LegacyHandover.php`).

## First time

    composer install            # autoloader and the coding standards (phpcs)

The release zip contains `vendor/` without the development packages, so a site needs no Composer.

## Adding a feature

A feature lives in its own folder under its module and is found automatically:
`src/Modules/<Module>/Features/<Feature>/<Feature>.php`. It describes itself in `get_meta()` and
does its work in `launch()`; everything else comes from `AbstractFeature`.

```php
<?php
/**
 * Hide Comments Feature.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Cleaner\Features\HideComments;

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Orientation;
use Override;

use function __;
use function add_filter;

/**
 * Class HideComments
 *
 * Closes comments on every post.
 *
 * @since 1.0.0
 */
class HideComments extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'cleaner_hide_comments',
			name:         __( 'Hide comments', 'core-functionality' ),
			description:  __( 'Closes comments on every post.', 'core-functionality' ),
			orientation:  Orientation::FRONTEND,   // Default: Orientation::BOTH.
			dependencies: 'woocommerce',           // Optional: environment id(s) that must be ready.
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
		add_filter( 'comments_open', '__return_false' );
	}
}
```

Then, on the settings page, **Feature Activation → Scan for new features** adds it to the
components register, after which you can switch it on and set its deployment tier. `$this->plugin`
(services such as `settings`) and `$this->module` are available in the feature.

On a site that gets the feature through a plugin update, the register is refreshed automatically: the
new feature gets its enabled state and tier from `kernel/`, and an administrator sees a notice.

## Adding a module

A module only groups features: `src/Modules/<Module>/<Module>.php`, extending `AbstractModule` with
just `get_meta()`. Its features go in `src/Modules/<Module>/Features/`.

## Adding a plugin or theme to the environment

A guard class represents an outside plugin or theme: it proves the plugin really started and supplies
the plugin's admin links, from which the quick menu in the admin toolbar is composed.

1. Add `src/Environment/Plugins/<ClassName>.php` extending `AbstractPlugin`. `get_id()` returns the class
   name in kebab-case (`MyPlugin` → `my-plugin`).
2. Add the id and the plugin basename to `settings/environment-index.php`
   (`'my-plugin' => 'my-plugin/my-plugin.php'`).
3. `get_proof_requirements()` returns what exists once the plugin has started (a class, function or
   constant), checked on `init`. Classes are checked without autoloading.
4. `get_toolbar_nodes()` returns the plugin's admin toolbar links; `get_menu_nodes()` (optional, default
   none) its admin menu blocks. The toolbar links of
   every known plugin are offered in the menu builder, also when the plugin is not active, so
   `get_toolbar_nodes()` may only call WordPress functions directly: a URL that needs the plugin
   itself goes in a closure (`href: fn () => wc_get_page_permalink( 'shop' )`). The `orientation` of a
   shortcut says whether it links to an admin page or to the site; where it is shown is set in the
   menu builder.
5. `get_post_types()` returns the post types the plugin registers (plain names), if any. Features use
   them to offer choices per plugin, such as the Duplicate Post tab.

Themes work the same in `src/Environment/Themes/` with the id suffix `-theme`; their index entry holds
the theme name and author.

## AI Access

The module AI Access lets an AI assistant (for example Claude through the
[MCP Adapter](https://wordpress.org/plugins/mcp-adapter/)) find, read and write drafts. It never
publishes, schedules or deletes: published items are changed in a Rewrite & Republish copy of Yoast
Duplicate Post, which replaces the original only when you publish it. The assistant logs in as a
WordPress user with an application password and has that user's rights.

| Ability | What it does |
|---|---|
| `core-functionality/find-content` | Search by type, status, text and term |
| `core-functionality/get-content` | One item in full: block markup, excerpt, terms, extra fields |
| `core-functionality/list-terms` | Existing categories, tags and other terms |
| `core-functionality/create-draft` | A new item as a draft; refuses content with markup it would remove, such as inline SVG |
| `core-functionality/update-draft` | Change a draft, an item pending review, or a copy; refuses content with markup it would remove, such as inline SVG |
| `core-functionality/replace-in-draft` | Replace pieces of the content of the same items; the rest stays byte for byte the same |
| `core-functionality/submit-for-review` | Set a draft to pending review |
| `core-functionality/start-rewrite` | Copy of a published item (with Duplicate Post) |
| `core-functionality/list-venues-and-organizers` | Venues and organizers (with The Events Calendar) |

Posts and pages are always available; events (The Events Calendar), courses and lessons (Sensei LMS) and
the Yoast SEO title and description come with their plugin. Per content type, the "AI Access" tab sets
what the assistant may do, in steps: read, make and change drafts, change published content (in a copy). Other code can add content types, fields and
abilities with the hooks below.

## Hooks

| Hook | Type | Default | Arguments | Example |
|---|---|---|---|---|
| `core-functionality/ready` | action | | | `add_action( 'core-functionality/ready', 'my_start' );` |
| `core-functionality/launch-timing` | filter | `init`, priority `0` | `array{hook: string, priority: int} $timing` | `add_filter( 'core-functionality/launch-timing', fn () => array( 'hook' => 'wp_loaded', 'priority' => 10 ) );` |
| `core-functionality/register-settings-sections` | action | | `AdminSettingsPage $page` | `add_action( 'core-functionality/register-settings-sections', fn ( $page ) => $page->register_section( MySettings::class ) );` |
| `core-functionality/configuration-file` | filter | `wp-content/core-functionality/configuration.php` | `string $path` | `add_filter( 'core-functionality/configuration-file', fn () => '/srv/shared/cf-configuration.php' );` |
| `core-functionality/release-settings` | filter | The release file, adjusted by the configuration file | `array<string, array{status: string, development: string, staging: string, production: string}> $settings` (FQCN => entry; status `development`/`staging`/`production`, rules `auto-on`/`adjustable`/`auto-off`) | `add_filter( 'core-functionality/release-settings', function ( $s ) { $s[ MyFeature::class ]['production'] = 'auto-off'; return $s; } );` |
| `core-functionality/admin-menu-config-nodes` | filter | The admin menu items from the settings files | `AdminMenuConfigNode[] $config_nodes` | `add_filter( 'core-functionality/admin-menu-config-nodes', fn ( $nodes ) => $nodes );` |
| `core-functionality/admin-menu-removals` | filter | The admin menu slugs to remove | `string[] $slugs_to_remove` | `add_filter( 'core-functionality/admin-menu-removals', fn ( $slugs ) => array_merge( $slugs, array( 'edit-comments.php' ) ) );` |
| `core-functionality/ai-access-content-types` | filter | Posts and pages, plus the types of active integrations | `ContentTypeProfileInterface[] $profiles` (keyed by post type) | `add_filter( 'core-functionality/ai-access-content-types', fn ( $p ) => $p + array( 'faq' => new ContentTypeProfile( 'faq' ) ) );` |
| `core-functionality/ai-access-field-handlers` | filter | The field groups of active integrations | `FieldHandlerInterface[] $handlers` | `add_filter( 'core-functionality/ai-access-field-handlers', fn ( $h ) => array_merge( $h, array( new MyFields() ) ) );` |
| `core-functionality/ai-access-abilities` | filter | The content abilities | `AbilityInterface[] $abilities`, `ContentServices $services` | `add_filter( 'core-functionality/ai-access-abilities', fn ( $a, $s ) => array_merge( $a, array( new MyAbility( $s ) ) ), 10, 2 );` |
| `core-functionality/ai-access-content-summary` | filter | Id, type, status, title, dates, links | `array $summary`, `WP_Post $post` | `add_filter( 'core-functionality/ai-access-content-summary', fn ( $s, $p ) => $s + array( 'author' => $p->post_author ), 10, 2 );` |
| `core-functionality/ai-access-editable-statuses` | filter | `draft`, `pending` (publishing statuses are always refused) | `string[] $statuses`, `WP_Post $post` | `add_filter( 'core-functionality/ai-access-editable-statuses', fn ( $s ) => array( 'draft' ) );` |
| `core-functionality/ai-access-rewrite-available` | filter | `false`; `true` with the Rewrite Copies feature | `bool $available` | `add_filter( 'core-functionality/ai-access-rewrite-available', '__return_false' );` |
| `core-functionality/ai-access-hidden-abilities` | filter | Abilities of Yoast SEO, WooCommerce, Jetpack Forms, WP Rocket and FluentCRM that change the site or send e-mail without approval | `string[] $abilities` | `add_filter( 'core-functionality/ai-access-hidden-abilities', fn ( $a ) => array_diff( $a, array( 'wp-rocket/clear-url-cache' ) ) );` |
| `core-functionality/ai-access-hide-abilities` | filter | `true`; the feature Other Changes Without Asking for Approval returns `false` | `bool $hide` | `add_filter( 'core-functionality/ai-access-hide-abilities', '__return_false' );` |
| `core-functionality/genesis-only-plugins` | filter | Plugins that only work with Genesis (Dambuster, Featured Page Advanced, Connect for WooCommerce and Sensei LMS, eNews Extended, Simple Sidebars and others) | `string[] $plugins` (basenames) | `add_filter( 'core-functionality/genesis-only-plugins', fn ( $p ) => array_merge( $p, array( 'my-genesis-addon/my-genesis-addon.php' ) ) );` |
| `core-functionality/duplicate-post-hidden-types` | filter | The post types chosen on the "Hide Yoast duplicate links" tab | `string[] $post_types` | `add_filter( 'core-functionality/duplicate-post-hidden-types', fn ( $t ) => array_merge( $t, array( 'product' ) ) );` |

To run code from another plugin once Core Functionality has started, whether or not that already
happened:

```php
if ( did_action( 'core-functionality/ready' ) ) {
	my_start();
} else {
	add_action( 'core-functionality/ready', 'my_start' );
}
```

(A public API for this is planned; see the open points in `docs/ARCHITECTURE.md`.)

## Code style and checks

WordPress Coding Standards with PSR-4 class files, `declare( strict_types=1 );` everywhere and
`core-functionality/<name>` hook names. See [`CLAUDE.md`](CLAUDE.md) for the conventions.

    composer phpcs              # check
    composer phpcbf             # fix what can be fixed automatically
    composer lint               # php -l on all PHP files

## Translations

Source strings are English, text domain `core-functionality`; `languages/` holds the `.pot` and the
Dutch translation. After changing strings (in DDEV, from the site folder):

    ddev wp i18n make-pot wp-content/plugins/core-functionality wp-content/plugins/core-functionality/languages/core-functionality.pot --domain=core-functionality --exclude=vendor,___,kernel,src/Modules/ActiveWoo/dev-notes

Then update `languages/core-functionality-nl_NL.po` (Poedit or Loco Translate) and create the `.mo`:

    ddev wp i18n make-mo wp-content/plugins/core-functionality/languages/core-functionality-nl_NL.po wp-content/plugins/core-functionality/languages

## Release

1. Raise the version in the plugin header of `core-functionality.php` and the `Stable tag` in
   `readme.txt`, and add a changelog entry in `readme.txt`.
2. Commit, tag `vX.Y.Z` and push the tag. `.github/workflows/release.yml` builds the zip (with
   `vendor/`, without development files) and attaches it to the GitHub release.
