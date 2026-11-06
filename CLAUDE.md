# Conventions for this repository

DRAFT: written from the code and from what Hans explained in October 2026. Hans: correct what is wrong;
delete what you do not want. Items marked "(confirm)" are guesses.

General working agreements and code standards live in Hans's brain (`~/.claude/CLAUDE.md` imports them);
this file only adds what is specific to this plugin. Architecture, decisions and open points:
`docs/ARCHITECTURE.md`.

Claude keeps this file and `docs/ARCHITECTURE.md` up to date with what was done or agreed, without
asking first, and says briefly what changed. Both are mainly written for Claude.

## What this plugin is

The OOP successor of the legacy plugin, now Core Functionality Legacy (folder and repo
`core-functionality-legacy`, procedural, namespace `DeWittePrins\CoreFunctionalityLegacy`, text domain
`core-functionality-dwp`). Both run side by side while features move over; this plugin has the final
folder and slug `core-functionality` (version 1.0.0, a fresh start). The temporary
`src/Migration/LegacyHandover.php` lets the legacy plugin step aside per switched-on feature (see
`docs/ARCHITECTURE.md`, Legacy handover). Not in production yet, so renames are still cheap (in their own commit). It is meant to keep growing with
new modules and features for Hans's own sites.

## How we work here

- Claude works directly in this repository (WSL, `/home/hans/projects/speeltuin/wp-content/plugins/core-functionality`)
  and commits in small separate commits. Hans pushes. Never stage files Hans is still editing; commit only
  what belongs to the change.
- Think first, then build: for anything beyond a small fix, agree on the plan before writing code.
- Verify before delivering: `composer lint` and `composer phpcs` (ruleset `phpcs.xml`, PHP files only;
  the plugin is phpcs-clean, keep it that way). There is no JS build. For behaviour, check the local site
  `https://speeltuin.ddev.site` (DDEV, PHP 8.5, plugin active). The site's mu-plugins limit the debug log
  to fatal errors, so warnings do not show there; log them with a temporary mu-plugin that is removed
  afterwards. Hans logs in in the app's browser pane for admin tests.
- New or changed translatable strings: regenerate the `.pot`, update `languages/core-functionality-nl_NL.po`
  and the `.mo` (commands in README.md).
- Do not change hook names, option keys, settings keys, module or feature ids without saying so; stored
  values and settings files depend on them.

## Plugin-specific rules

- Namespace `DeWittePrins\CoreFunctionality\`, PSR-4 under `src/`. PHP 8.2+, `declare( strict_types=1 );`
  in every file.
- Text domain `core-functionality` (the final folder name), Domain Path `/languages`, loaded by `Plugin` on
  `init` (priority -10). Source strings English, every string also in the nl_NL `.po`.
- Hooks are `core-functionality/<name>` with a kebab-case name (the brain's `<plugin_slug>/<name>`).
  Prefix `dwp_cf_` for options, nonces and cron events; settings page slug `dwp-cf-settings`.
- All option access goes through `Settings` (never `get_option()` / `update_option()` / `delete_option()`
  directly). The plugin's own settings use `get()` / `get_whole()` / `save()` / `reset()` with a token;
  `get_unmanaged()` / `save_unmanaged()` are only for options that are not part of this plugin (of
  WordPress or another plugin), under their exact name.
- No `class_exists()` / `interface_exists()` / `function_exists()` wrappers around the plugin's own
  declarations; Composer loads them once.
- Guards supply the admin toolbar and menu links of their plugin or theme on purpose (see
  `docs/ARCHITECTURE.md`, Environment); do not move them to the AdminToolbar module. Toolbar nodes are
  built for inactive plugins too (the menu builder's catalogue), so `get_toolbar_nodes()` calls a
  plugin's own functions only inside an href closure. Get guards through `Environment::get_plugin()` /
  `get_theme()`, which keep one instance per request.
- A **module** is only a container: `src/Modules/<Module>/<Module>.php`, extends `AbstractModule` and
  contains only `get_meta()`. Its release status and rules follow from its features (the widest), so it may
  have no functionality of its own (Hans, November 2026).
- A **feature** holds the functionality: `src/Modules/<Module>/Features/<Feature>/<Feature>.php`, extends
  `AbstractFeature`, contains `get_meta()` and `launch()` (where it adds its hooks) plus its own methods.
  `$this->module` and `$this->plugin` come from the base class. Its assets live next to it (`assets/css`,
  `assets/js`); a settings tab is a class implementing `SettingsSectionInterface`, registered on
  `core-functionality/register-settings-sections`.
- `get_meta()` is static and returns a `ComponentMeta` (id, name, description; orientation, dependencies
  and `uses_features` only when they differ from the defaults). The register and the Kernel read it before
  an instance exists. Names are translated, so do not call the getters before `init`.
- Dependencies use environment ids: a plugin id (`woocommerce`) or a theme id with `-theme`
  (`any-genesis-theme`). Each id needs a guard class in `src/Environment/Plugins|Themes/`; its `get_id()` is the class name in kebab-case and equals the
  key in `settings/environment-index.php` (plugins: basename, themes: name and author).
- A new guard method gets a default in the abstract base (`AbstractPlugin`, `AbstractTheme`); a guard only
  overrides it when it has something to say. No empty overrides. Every plugin guard extends
  `AbstractPlugin`, also this plugin's own guard (`Plugins\CoreFunctionality`, which only overrides
  `is_ready()` and the like). Only `Core\WordPress` implements its interfaces directly and needs every new
  interface method itself: check it first, a missing one is a fatal (Hans, October 2026).
- Admin page nodes use `ToolbarShortcut::to_admin_page()`; leave out upsell and hidden pages. Do not change
  the `group` of a node that is already in the catalogue (saved menus derive titles from it).
- A guard's proof must hold when the Kernel boots (`init` priority 0): use a constant, function or class
  that the plugin defines once it has started, verified in its source or on the local site. Proofs use
  `class_exists( $class, false )`, so a class that is only autoloaded on first use does not count.
- Site-specific data (menus, links, licences) goes into settings files, not into code:
  `settings/modules/<module-folder>/...`, mapped in `settings/setup/modules-settings-path.php` and
  `settings/setup/key-slugs-translation.php`. Read them with `$plugin->settings->get( $key, $module_id )`.
- `kernel/components-*-register.php` are generated by the plugin. Do not edit them by hand; rescan from the
  settings page instead. On the speeltuin Claude rescans and switches new modules and features on itself, in
  the app's browser pane (Settings → Core Functionality, "Zoeken naar nieuwe features", then tick and save);
  new components start switched off after a rescan (after an update they follow the register file).
  Since the first release (1.0.0) these files are committed with every change that adds a component or
  changes a status, so each release carries the right data; production cannot rescan (the scan only runs
  where the developer tools are allowed) and only knows the components in the release's files (Hans,
  November 2026).
- The status (how far the code is ready) and the rule per environment (always active,
  adjustable, always inactive) of each feature (a module follows its features) live in `settings/components-release.php`,
  written by the Release Management tab (only on a development environment) and committed; not in the
  code. Raise a status only when Hans says a component is ready. A new component is development and freely
  adjustable until it is in that file. See `docs/ARCHITECTURE.md`, Release settings.
- The plugin's own switchable parts are listed in the enum `CoreFunction`. Only
  `ReleaseRegister::is_core_function_on()` decides whether one is on; other code only asks it, never checks
  the environment itself for this. A function switches itself off (not loaded, or its public methods ask);
  that is never the caller's job. See `docs/ARCHITECTURE.md`, Core functions.
- Feature names are short within their module ("Menubouwer" under Admin-toolbar); where the module is not
  shown around a name, use `ComponentsRegister::get_display_name()` ("Menubouwer (Admin-toolbar)").
- A feature that can clash with code the plugin cannot see (it replaces a snippet or a legacy file that may
  still run) gets `confirm_activation: true` in its `ComponentMeta`: it then waits for an administrator's
  one-time answer before it runs. See `docs/ARCHITECTURE.md`, Components register.
- A feature that lifts a safeguard (such as `UnapprovedChanges`) gets an `activation_warning` in its
  `ComponentMeta`: a confirm when it is ticked, and it is never switched on automatically (not after a
  rescan, an update or a status change). Its description states the scope and risks precisely; longer
  descriptions use blank lines between paragraphs and show in the module's "Explanation" on the Feature
  Activation tab, with optional details from `get_explanation_details()` (Hans, November 2026).
- The app's browser pane blocks WordPress's own scripts on the speeltuin (`load-scripts.php`, even
  `jquery.min.js`: `ERR_BLOCKED_BY_CLIENT`, October 2026), so admin JS does not run there. Test page
  scripts by injecting jQuery from code.jquery.com and running the code with `javascript_tool`. (bevestig)
- Comments are still partly Dutch; new code is English, converting old comments is low priority.

## Git

- Repository `github.com/hansschuijff/core-functionality` (SSH remote; renamed from `core-functionality-oop`
  in November 2026), branch `main`.
- `___/` is Hans's local scratch folder (gitignored): notes, old patches, prototypes. Do not treat it as
  part of the plugin, and do not rely on what it says without checking the code.
