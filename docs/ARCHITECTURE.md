# Core Functionality (OOP): architecture and decisions

Status: October 2026, version 1.0.0 in development. Not in production; runs on the local DDEV site next to
the legacy `core-functionality` plugin, whose features move over step by step. Written by Claude from the
code and Hans's explanations; points marked "(confirm)" are Claude's reading and still need Hans's check.

## Goal

One plugin that holds all site-specific functionality of Hans's WordPress sites, independent of the theme.
It is a small microkernel: it finds its own modules and features, and starts only those that fit the current
site (active plugins and themes, server tier, admin or front end, and what the site owner switched on).
New customizations are added as new features without touching the kernel.

## Principles

- **Modules are containers, features do the work.** A module groups features and carries shared metadata;
  all behaviour lives in features.
- **Convention over registration.** A feature is found by its place on disk
  (`src/Modules/<Module>/Features/<Feature>/<Feature>.php`); there is no list to edit by hand.
- **Check before creating.** Metadata is static, so orientation, environment and interdependencies are
  checked before any object exists.
- **Environment aware.** A component only starts when the plugins, themes and server tier it needs are
  really there, proven at runtime (class, function or action present), not only "activated".
- **Fail closed.** If the settings files are missing or corrupt, the plugin stops completely and shows one
  admin notice; the rest of the site keeps working. An unknown server tier counts as production.
- **Code and site data apart.** Code is generic; site-specific data (toolbar menus, links, licence data)
  comes from settings files with database overrides.

## Boot sequence

1. `core-functionality.php` loads the Composer autoloader and creates `Plugin`.
2. `Plugin::__construct()` builds `Settings` (stops here on failure, see Principles), then
   `InterDependenciesGuard`, `EnvironmentLookup` (server tier), `Environment`, `FileService`,
   `ComponentsRegister` and the `Kernel`.
3. `plugins_loaded`, admin only: `Plugin::setup()` builds `Notices`, the settings page and the
   `PluginIntegrityWatch`.
4. `init` priority 0 (filter `core-functionality/launch-timing`): `Kernel::boot()` does everything in one step. It lets
   every active environment guard register its admin menu nodes, takes the launchable components
   from the `ComponentsRegister` (already filtered on activation, status, environment rule and orientation), keeps
   those whose environment dependencies are ready, creates the modules and their features, drops features
   whose required features are missing (`uses_features()`), calls `launch()` on every instance, marks the
   plugin ready and fires `core-functionality/ready`. All plugins and the theme have loaded by then, so the guards see
   what really started; features can still hook into `init` itself.
5. Other code waits for that with `Plugin::call_when_ready()` or the `core-functionality/ready` action.

Until October 2026 the Kernel scheduled on `plugins_loaded` and launched on `init`, a leftover from when
components were scheduled from outside; the filter `dwp_cf_plugin_load_timing` went with it.

## Structure

```
core-functionality/
├── core-functionality.php        bootstrap
├── src/
│   ├── Plugin.php                container: services, paths, option keys, ready state
│   ├── Kernel.php                schedules on plugins_loaded, launches on init
│   ├── Interfaces/               ModuleInterface, FeatureInterface, LoggerInterface
│   ├── Abstracts/                AbstractModule, AbstractFeature, DescribedByMeta (getters from get_meta())
│   ├── Enums/                    Orientation, Deployment, EnvironmentRule, Register, ...
│   ├── DTO/                      AdminMenuBlock, ToolbarShortcut, ComponentMeta
│   ├── ValueObjects/             module, feature and environment validators
│   ├── Services/                 ComponentsRegister (+ ComponentsRegisterFiles), Settings (+ SettingsFiles,
│   │                             Options), InterDependenciesGuard, PluginIntegrityWatch, ClassParser,
│   │                             FileSystem/ (FileService, the scanners)
│   ├── Environment/              Environment, EnvironmentLookup, Core/WordPress,
│   │                             Plugins/, Themes/ (one guard class per id), Registers/
│   ├── Admin/Services/Settings/  settings page (Settings → Core Functionality) with sections (tabs)
│   ├── Notifiers/                admin notices; push notices with Pushover and Telegram drivers
│   ├── Diagnostics/Logger.php
│   ├── Api/                      CF facade and functions for other plugins
│   └── Modules/
│       ├── AdminToolbar/  MenuBuilder, Cleaner, StylesFixer, Visualizer
│       ├── AdminMenu/     AdminMenuArchitect
│       ├── ActiveWoo/     KeepLicenseAlive
│       └── Cleaner/       TransientOptimizer
├── settings/                     default settings files (site data), see Settings
└── kernel/                       generated components registers
```

## Components register

`ComponentsRegister` keeps track of which modules and features exist and which may load in the current
site. It combines:

- **what exists**: found by scanning `src/Modules/` (`rebuild()`), stored as module class → feature classes;
- **what is switched on**: the Feature Activation tab of the settings page;
- **what the release decides** (`ReleaseRegister`, see "Release settings" below): the **status** (how far
  the code is ready; a development server loads everything, staging loads staging and production
  components, production only production components, `Deployment::allows()`) and the **rule** on this
  environment (always active, adjustable, always inactive). `is_available_here()` combines
  them; `is_switched_on()` adds the site's choice;
- **orientation**: admin, front end or both (`get_orientation()`).

The kernel then checks the environment dependencies of whatever passes. The registers are kept in the
database through the options vault and also written to `kernel/components-register.php`,
`components-enabled-register.php`; when the database has no value
the files are read and copied into it (through `ComponentsRegisterFiles`, which knows which register lives
in which file). The options are there for speed; the files are a fallback and are
only written when the `kernel/` folder is writable (on production it often is not, and the option then
keeps the change).

After a plugin update the components option would still describe the old version. The plugin version is
therefore stored with the registers (`components-register-version`). On a different version, before
anything reads the registers, the components option is removed so it is refilled from the new version's
file; components that are new get their enabled state from the file, while the
site's choices for existing components stay. An administrator gets a notice (on `admin_init`) listing
what was added. During development the version does not change, so a rescan on the settings page is
still how new code is picked up locally.

**Confirmation before activation.** New components follow the register file, so they are usually on after
an update; that is the point (a new feature is welcome). A component whose `ComponentMeta` sets
`confirm_activation: true` is the exception, used when switching it on can clash with something the plugin
cannot see, such as a snippet doing the same thing (Hans, October 2026). It does not launch until an
administrator decided on it on this site, even when the register enables it: `filter()` skips it through
`awaits_activation_confirmation()`, which also requires that it may run here and that its rule leaves the
choice to the site (always active asks nothing). The question is one notice on every admin page
(`Notifiers\ActivationQuestion`, `manage_options`): a vertical list of the waiting components ("Feature
(Module)"), "Mag ik ze voor je activeren?" with "Ja" and "Nee" for the whole list, and "Meer lezen" with the
descriptions and where to activate them later. Choosing per component is what the Feature Activation tab
is for. The notice has no close button and does not return once answered; only "Ja" gets a message, and
the text avoids words like "final" that make a click feel risky (Hans, October 2026). "Ja" also switches a
feature's module on. Saving the Feature Activation
tab counts as the decision for every waiting component (they are marked there); the factory reset forgets
all decisions, because it switches everything on. Decisions live only in the setting
`components-activation-decided` (FQCN => true), never in the register files: they belong to one site.
The flag is a safety net for now; whether it gets more uses or companions is open.

**Release settings.** Two different limit questions sit above a site's own choice (Hans, October 2026):
how far is the code ready (the **status**), and does what the component does suit this kind of environment
(the **rule**: an e-mail blocker belongs on development and never on production, the way the old
`mu-plugins-cf` handled such plugins). Both are plugin management properties, not properties of the code,
so they live in one release file, `settings/components-release.php` (FQCN => `status` plus a rule per
environment: `auto-on`, `adjustable`, `auto-off`), which is committed and travels with the release. A
component that is not in it has status development and is adjustable everywhere. The file is
written by the Release Management tab (`ReleaseSettings`, section id `cf-release-management`), which is only
registered on a development environment, so the sites that install the plugin do not get it.
"Always active" runs a component without the site's choice and without the confirmation question, also
after a database from another environment was restored. "Always inactive" is the friendly word for "not allowed here". The Feature
Activation tab notes these cases behind the name and disables their checkbox (a hidden field keeps the
saved choice for when the release lets go). The tab's header shows the build status and, grouped, the
activation per environment; its explanation for developers is collapsed (Hans, November 2026).

**A module follows its features** (Hans, November 2026). A module is only the container of its features,
so the release decides about features; a module with features gets no entry of its own
(ReleaseRegister::with_module_entries()). It takes the widest setting of its features: the highest
status, and per environment the widest rule among the features whose status reaches it (always active
before adjustable before always inactive). So an always-active feature makes its module always active, and
a module is always inactive only when all its features are. The Release Management tab has no module row:
the module's name heads its table, and only feature entries are saved; a module without features keeps its
own row with selects.

**The release gets a filtered configuration** (Hans, November 2026). Like the components register, which
holds everything but passes on what fits the circumstances: a rule on an environment the build status does
not reach yet makes no sense in the release, so the file gets "always inactive" there. The developer's
choice for later is kept in the setting `components-release-choices` on the development site, and the tab
shows those choices, so they apply in the file once the status is raised. This hard-wires the rule that a module has no functionality of its own outside its
features (CLAUDE.md: a module contains only get_meta()). Considered and dropped: the module as a
ceiling with its own rule, which needed extra rules between module and feature rules.

An installation adjusts the release without touching the plugin, in two layers that may raise a limit as
well as lower it, as both are conscious choices by whoever may change its files:
`wp-content/core-functionality/configuration.php` (same format, entries may be partial; a plugin update does
not overwrite it and an organisation can roll one file out to many sites; path filter
`core-functionality/configuration-file`), then the filter `core-functionality/release-settings`. The
Release Management tab notes a component the configuration adjusts. No constant: Hans prefers filters and
files to constants (October 2026).

This replaced an earlier model with a status in `ComponentMeta` plus a per-site "Draait op" value, a
question when a new release changed it, and a Deployment tab: a per-site value lives in the database,
which is exactly what a restore from another environment overwrites, and it duplicated the status.

**Newly available counts as new.** The components that may run on this server are kept in
`components-available`. After an update or a save of the release file (`components-availability-check`),
`handle_newly_available_components()` (init -5: after the translations, before the Kernel) compares the list:
a component that could not run here before and can now is switched on, or, with `confirm_activation`, its
decision is forgotten so the question is asked. The first list only gets stored. On DDEV everything is
available (development), so this only shows on staging and production.

The Feature Activation tab has a "Show only disabled" filter. It works on the saved state (a data
attribute), not on the checkboxes, so a row stays while it is ticked; a component that waits for
confirmation counts as disabled.

## Environment

A dependency is an id. `Environment` turns it into a guard class by convention: `woocommerce` →
`Environment\Plugins\Woocommerce`, `<id>-theme` → `Environment\Themes\…`, and `wordpress-core` →
`Environment\Core\WordPress`. Ids are kebab-case
(Hans's preference); the key in `environment-index.php` is the id without the `-theme` suffix. Plugin guards extend
`AbstractPlugin` and prove the plugin is alive with a `ProofRequirement` (classes, functions, actions);
results are cached per request. `settings/environment-index.php` maps plugin ids to plugin basenames and
theme ids to theme name and author. A plugin whose folder name differs between sites gets a list of
basenames (Debug Toolkit: `__debug-toolkit` locally, `_debug-toolkit` on production; BE Like Content); the
first one installed is used (`EnvironmentLookup::get_plugin_basename()`), and the Kernel accepts all of them. `get_dependencies()` returns one id or an `and` / `or` array of ids.

`Environment` creates each guard once per request: `get_plugin( $id )` and `get_theme( $id )` keep the
instances per kind (plugins and themes are registered separately), `get_wordpress()` the core guard.
There are no server guards: the server tier is handled by the release settings of the components, and
switching plugins per server by a separate mu-plugin (the `-environment` guards were removed in October
2026).

The plugin and theme guards also supply admin toolbar and admin menu links for their plugin or theme
(`get_toolbar_nodes()`, `get_menu_nodes()`), collected in `AdminToolbarRegister` and `AdminMenuRegister`.
This is deliberate: a guard is the plugin's internal representative of something outside, so it is the
one that knows that plugin's screens. The links are building blocks ("lego bricks") from which Hans
composes his own quick menu in the admin toolbar, because the admin menu is long and needs scrolling.
Custom links can be added on the settings page as well. Blocks of a plugin that is not active must not
show in the menu. (The legacy plugin did it the other way round, from config, and had to test every
item's dependencies itself.)

**Toolbar catalogue.** `AdminToolbarRegister` holds the toolbar nodes of *every known* plugin and theme
(every guard class in `Plugins/` and `Themes/`) plus the WordPress core, each provider with a
`ProviderStatus`. This plugin has a guard of its own there (`Plugins\CoreFunctionality`, e.g. the link to
its settings page): it extends `AbstractPlugin` like every plugin guard, but is always ready, needs no
index entry, and should anything name it as a dependency it answers true. The statuses:
active (`is_ready()`), inactive (installed) or not installed. `get_all()` serves the settings page, so a
menu can be set up once, before a plugin is installed or activated; `get_active()` serves the toolbar, so
activating or deactivating a plugin switches its nodes on and off. The register is filled in memory on
first use after `init` (`Environment::load_toolbar_catalogue()`, set as its loader); it is not cached in
the database, because hrefs can be closures and titles are translated. Because nodes of inactive plugins
are built too, `get_toolbar_nodes()` may only call WordPress functions directly; anything that needs the
plugin itself goes in a closure as href (`ToolbarShortcut::get_href()` resolves it). The admin menu nodes
still go to `AdminMenuRegister` from `is_ready()`.

**Post type catalogue.** Plugin guards and the core guard also name the post types their plugin registers
(`get_post_types()`, `PostTypeProviderInterface`; plain names only, so it works for inactive plugins too;
default none). `PostTypeRegister` collects them per provider with the same status and name, filled on first
use after `init` by `Environment::load_post_type_catalogue()`; both catalogues share the status per provider
(found once per request). Features use it to offer choices per plugin instead of per post type and to find
the plugin of a post type (`get_provider()`), such as the Duplicate Post tab and the grouping on the AI
Access tab (Hans, October 2026). Themes name no post types. Sensei Pro extends the Sensei LMS guard but
names none: the course types belong to Sensei LMS.

`ToolbarShortcut::$orientation` is the kind of link (to an admin page or to the site), shown as a badge in
the menu builder. Where a node is shown is not decided by the guard but per node in the menu builder.

**Menu builder.** The saved tree (settings key `menu-builder-hierarchical-tree`) holds per node: `id`,
`type` (`shortcut` or `submenu`), `title` (empty: the shortcut's own, translated title), `href` (submenus
only; a shortcut's URL always comes from the guard), `visibility` (`admin`, `frontend` or `both`, the
default) and `children`, at most four levels (`MenuBuilderSettings::MAX_DEPTH`; the runtime has no limit of its own). At runtime a shortcut whose provider is not active is left
out together with its children, and a submenu without a link of its own is left out when none of its
children is shown. The settings tab renders the catalogue and the tree as JSON; `menu-builder-settings.js`
builds the pool and the tree from it (one place for the markup). A new node takes the depth of the node it
is inserted before (at the end: of the last node), so it never takes over a submenu; moving a node moves
its branch. With one node selected, "Add" (the checked pool items) inserts below it (inside it when it is a submenu
without a link). The admin menu feature has a similar, latent mechanism for menu items.

Visibility is shown as a one-click switch per node. A node is shown only where its parents are shown
too (the runtime checks every node), but a child keeps its own setting under a restricted parent: its
setting usually follows from where its link goes, and changing it automatically would lose that when the
parent is changed back. The editor shows the parent's restriction and marks a conflicting child (never
shown) without changing it. Nodes can be selected (shift-click for a range) and changed in bulk:
visibility, reset titles, remove, and dragging or keyboard moves of the whole selection, each node with
its branch. The pool is grouped per source with search and an "only active" filter; tree branches can be
collapsed (remembered in the browser). The tree is an ARIA tree with keyboard control (arrows, Alt+arrows
to move and indent, Space to select, Ctrl+Space or Ctrl-click for a node with its branch, Enter, Delete,
Esc); "Keyboard shortcuts" (or ?) shows the overview. When the columns fit side by side, the builder fills
the visible part of the settings page, so only the lists scroll. Branches and the explanations above the
pool and the menu start collapsed (one "Explanation" button above either column opens both). The pool's
controls are one row: a filter switch for "only active", search (with a × inside the field that empties
it) and Add; selecting a whole source goes
with the checkbox in its heading. A collapsed branch that receives a node is opened, and depths at the end of the
menu follow the last visible node, so nothing disappears into a closed branch. Every change can be undone
and redone (Ctrl+Z / Ctrl+Y, up to 50 steps; serialize() records the state before each change).

Dragging: the drop depth follows the pointer sideways (one indent from where the drag started, or entered
the tree, is one level), between the depth of the node below and one deeper than the node above; an
indented line shows where it lands, so a node dragged sideways in place is indented or outdented.
Dragging a checked pool item takes all checked items along; dragging a source's heading drops a submenu
named after the source with its shortcuts (at the deepest level only the shortcuts). The pool shows each
shortcut's URL and the search covers it. "Preview" shows the menu as the toolbar would, for admin pages or
the site, with all plugins or only those active here; submenus start closed and open with a click.
(Hans had a toolbar-like flyout on hover in mind; the list is fine for now.)

The menu can travel with the plugin: "Save as default" (on a site where the settings folder is writable,
in practice development; deliberately a separate button, in case a production site can write there too)
writes it to `settings/modules/admin-toolbar/menu-builder.php` and lets the site follow it; committed, it
goes along with the release. A site without a menu of its own uses that default (`Settings::get_whole()`);
saving a menu equal to the default drops the site's own copy, so it follows the default again. A status
line above the tree says which applies. "Import / export" opens a dialog: export downloads the menu in the
editor as JSON; import takes the default or a JSON file into the editor (undoable, saved with "Save
menu"), replacing the menu or merging. Merging adds the items the menu lacks, recognised by instance id so
nothing is added twice: an item goes after its predecessor from the source if the menu has it, else first
under its parent, else at the end of the top level. The menu's own items, titles, visibility and
arrangement stay; removed items come back, which is what merging means (quicker to remove again than to
rebuild a structure). This is a deliberate action, unlike `Settings::get()`'s merge on every read, which
would make removals impossible. Instance ids differ per site, so merging a file from another site adds
all of its items.

The settings page frame is fixed beside the admin menu (from 783px wide), so a long admin menu scrolls on
its own and the buttons stay in view. The WordPress footer is hidden on this page: with the frame fixed,
the window itself still scrolls, and the footer would slide over the buttons.

Titles: a menu item has a full title (clear on its own: "Venue Categories", "Add Plugin") and may have a
short one ("Categories", "New") with a group (what it is about: plugins, posts, venues; default its source).
Without a title of its own, a menu item under a parent of the same group shows the short title, elsewhere
the full one; a custom submenu counts as the group all its menu items share. The pool shows full titles.
The toolbar (MenuBuilder.php) and the editor (serialize()) apply the same rule, so nothing is stored.

Menu item ids read `cf-<source>-<page>`: `cf-woocommerce-orders`, `cf-events-calendar-settings-seo`; the
source alone is its menu head (`cf-automatewoo`), and `view-` marks a page on the site
(`cf-woocommerce-view-my-account`). Saved menus store these ids, so renaming one means converting the
saved menus too (done once, in October 2026, while the plugin ran on the development site only). Each
guard names its source (`get_name()`), shown in the pool.

**Catalogue contents.** In October 2026 the catalogue was brought in line with the admin menu of
tinekevanurk.nl (Hans's list in `Naslag/adminmenu-tinekevanurk.txt`; the guards' own list, regenerated from
the code, is `Naslag/adminmenu-catalogus-guards.txt`). Rules followed: upsell pages ("Upgrade", "Free vs
Pro", "Contact us") and hidden pages are left out; each page belongs to the plugin that registers it
(WooCommerce core vs. its extensions, TEC vs. ECP vs. Event Tickets (Plus), Yoast free vs. Premium, Sensei
LMS vs. Sensei Pro's glossary and groups); a new guard only gets a proof checked in the plugin's source, so
plugins not installed locally wait (Bloom, Ultimate Blocks, Yoast Local SEO and WooCommerce SEO, Google for
WooCommerce, Jetpack Boost, Genesis Dambuster, Genesis Simple Sidebars, ActiveCampaign subscription forms).
Most admin pages are made with `ToolbarShortcut::to_admin_page()`. A node's group is not changed once it is
in use: a custom submenu takes the group its items share, so a changed group changes titles in saved menus.
Dutch labels: coupons are "Waardebonnen" (WooCommerce's own term), Sensei's students "cursisten", and
settings pages name the subject first ("Duplicate Post-instellingen", "Waardebon instellingen", Hans).

Cleanup point (minor): the catalogue holds some duplicate links (the same URL from two sources, such as
Sensei Pro repeating Sensei LMS, a menu head equal to its first item, or two WooCommerce links with `#`).
Harmless, at most a little inefficient.

### Reader Experience

Hans's Reader Experience plugin has no options; it is steered by filters only. The module
`ReaderExperience` (dependency: the `reader-experience` guard) has one feature, `Configuration`, with a
settings tab: heading anchors (on/off, post types, button position and texts, containers, extra reserved
ids, CSS/JS), reading time (words per minute) and feedback (mail the admin, spam check). The choices are
saved as a whole (`get_whole()`, so an unchecked post type stays unchecked), with defaults in
`settings/modules/reader-experience/`. The feature answers the plugin's filters at the default priority;
it launches on init 0, before the plugin builds its modules (init 10) and reads the filters, so a theme or
mu-plugin on a later priority still has the last word. Left out: the module list and share channels
(objects and callables) and the heading id filter (a function, not a setting). It replaces the stopgap
mu-plugin `dwp-reader-experience-setup.php`.

### WooCommerce

The module `WooCommerce` (dependency: the `woocommerce` guard, which covers its features) holds
additions to WooCommerce. Feature `MultiAddToCart`: one link adds several products with their own
quantities, `?add-to-cart=101:1,102:3`, used by the ticket forms of Remote Tickets. It runs on wp_loaded
15, before WooCommerce's own handler (20), which it removes for such a request; each product passes
`woocommerce_add_to_cart_validation`, and the customer gets WooCommerce's message and is sent to the
cart. Taken over from Remote Tickets Server Add-ons (`WooCartExtension`): while both are active, the
feature removes that plugin's hook; the class can go from that plugin when CF goes live.

Three admin features for the order list (legacy screen `edit-shop_order` and HPOS
`woocommerce_page_wc-orders`; the speeltuin runs HPOS), so status changes need fewer clicks (Hans,
October 2026):

- `OrderPreviewCancel`: a "Cancelled" button among the status buttons of the order preview (eye icon),
  through `woocommerce_admin_order_preview_actions` and WooCommerce's own `woocommerce_mark_order_status`
  route. Shown for pending, on-hold, processing and completed: a completed order may need correcting after a
  wrong click, and refunds go by bank transfer (not through Mollie), so cancelling a paid order is only a
  status change. Red border plus a confirmation; for a subscription's parent order the button is filled
  and the question says the subscription ends too (WooCommerce Subscriptions cancels it on
  `woocommerce_order_status_cancelled`, which cannot be undone).
- `OrderPreviewTotals`: the rows of `WC_Order::get_order_item_totals()` (as on the invoice, minus the
  payment method) under the products, through `woocommerce_admin_order_preview_get_order_details` (key
  `dwp_cf_totals_html`) and a placeholder on `woocommerce_admin_order_preview_end`. Its CSS also caps the
  preview's content height so it scrolls and the footer buttons stay on screen.
- `ShortBulkActionLabels`: the status bulk actions read as actions: "In behandeling nemen", "In de wacht
  zetten", "Afronden", "Annuleren"; keys stay WooCommerce's own. WooCommerce has had its own
  `mark_cancelled` bulk action for some time, so the legacy plugin's
  `bulk-order-cancellation.php` is not ported.

Feature `BusinessInvoiceTotals` (extra dependency: the `woocommerce-pdf-invoices` guard): PDF invoices
of business orders meet the Dutch invoice requirements (art. 35a Wet OB; Hans, October 2026). An order
is a business order when `wpo_wcpdf_get_order_customer_vat_number()` finds a customer VAT number (it
knows the meta of EU VAT Assistant, `vat_number`, and EU VAT Compliance, `VAT Number`; tinekevanurk.nl
runs both). Through `wpo_wcpdf_woocommerce_totals` (priority 20) the totals become: the order line rows,
"Totaal exclusief btw" (total minus tax, so excl. + VAT = incl. to the cent), per rate the VAT with its
percentage from the order's tax item ("BTW Hoog (21%)"), with more than one rate also each rate's amount
excluding VAT (the products, shipping and fees that carry it), then "Totaal inclusief btw" and the payment
method. The row keys are the template's row classes, so the heavy line above the excl. total is CSS on
the row's cells (`assets/css`, printed through `wpo_wcpdf_custom_styles` for business documents only);
the snippet's line on a span inside the cell gave an extra thin line. Reverse charge (EU customer outside
the shop's country, no VAT, EU VAT Assistant did not reject the number): a "Btw 0%" row and a notice on
`wpo_wcpdf_after_order_details`, "Intracommunautaire levering" (art. 138, goods) and/or "Btw verlegd"
(art. 196, services: virtual products), with the customer's VAT number. Invoices of partly refunded
orders keep the original amounts (the PDF plugin removes the refund rows); credit notes (`credit-note`,
PDF Pro only) get the same layout with the refund's amounts and the parent order's VAT data. Shop prices
stay as they are: production shows prices incl. VAT, so the line rows are incl. and the excl. total
follows them. Takes over Hans's production snippet (`wpo_wcpdf_clean_b2b_invoice_totals`,
`wpo_wcpdf_b2b_collapse_borders`) by removing their hooks, and the legacy `render_tax_exempt_notice()`
(LegacyHandover stops the legacy file); the snippet can go once CF runs in production. It no longer asks
for confirmation: it is for Hans's own sites and handles the snippet itself (Hans, November 2026).

Feature `InvoiceDetails` (same extra dependency) covers every PDF invoice, business or not (Hans,
November 2026), and takes over the rest of the legacy `pdf-invoice-customizations.php`:

- Paid or not is one decision: a payment date, or a status WooCommerce counts as paid. The legacy rows read
  the two apart and printed "Wacht op betaling" next to "Betaald op" (seen on production invoices). A paid
  invoice gets a "Paid on" row (payment date, else the completion date) and no due date
  (`wpo_wcpdf_document_due_date` → 0). An unpaid invoice keeps the PDF plugin's own due date setting,
  titled "Pay before" ("Betalen vóór"); production sets 14 days there itself. The "Payment status" row is
  gone: "Paid on" or "Pay before" says enough.
- The customer's checkout note moves from the narrow column next to the totals (where long notes ran into
  the totals) to under the totals, full width: `wpo_wcpdf_shipping_notes` returns '' for the invoice and
  the feature prints the note on `wpo_wcpdf_after_order_details` (priority 20, after the reverse charge
  notice), with the plugin's own title and `customer-notes` class.
- The legacy notes to the customer (order notes written by the shop) are not ported: they are messages, not
  part of an invoice, and Hans does not miss them (November 2026).
- CSS (`wpo_wcpdf_custom_styles`, all documents): 8 mm between the logo and the shop address, the logo
  never wider than its cell. The logo height stays the PDF plugin's setting per site: 2cm suits the wide
  De Witte Prins logo. Tineke's logo had transparent space on its left in the file itself; a cropped copy
  is in Hans's Naslag folder (`logo-tinekevanurk-nl-retina-bijgesneden.png`) to set on that site.

### Events

The module `Events` runs when The Events Calendar or Event Tickets is ready (an "or" dependency:
`array( 'or' => array( array( 'the-events-calendar' ), array( 'event-tickets' ) ) )`; a flat list would
mean "and"). Feature `TemplateFallback` adds the site's own template overrides, kept apart from the
theme in `wp-content/core-functionality/templates/` (same layout as in a theme: `tribe/<plugin>/…`, legacy
`tribe-events/…`). They are site data, not part of a release: which changes a site needs differs per
site, so the plugin ships none. The lookup order is child theme (10), parent theme (15), CF templates
(16), the plugin's original (20): a theme can still override, a theme without overrides gets these.
Current views get the folder through `tribe_template_theme_path_list`; legacy views through
`tribe_events_template`, swapping only a plugin's own file. `TemplateLocations` holds the override
folders, the lookup order and the collecting, shared by the check and the settings tab.

The settings tab `CfTemplatesSettings` lists the CF templates (in use, or which theme overrides
them), and downloads a zip of the CF templates or of every template the site uses now (themes
included). A zip upload replaces the whole folder as a block, like a theme or plugin update, so it is
always clear what is there: only PHP files under the override folders are taken (one enclosing folder
in the zip is fine; other files are reported as skipped), the new set is built in `templates.new` and
swapped in, and the version before is kept in `templates.previous`; restoring swaps the two. Changing
the folder takes `install_plugins`, no `DISALLOW_FILE_MODS` and direct file access; the parent folder
gets an `index.php` and an `.htaccess` that denies direct requests. After a change the template check
result is cleared and opcache is told to reread the templates. Downloads are answered in `save()`,
which runs on `admin_init` before any output. The settings form posts as `multipart/form-data` for this.
The CF templates folder was chosen over the uploads folder because many hosts and security plugins refuse PHP
there, and over copying templates into the theme on a theme switch because copies would mix with the
theme's own overrides and be lost on a theme update.

Feature `TemplateMonitor` (admin only, for users who manage plugins, kept 12 hours) finds per template
the copy the site uses, in that order, and compares its `@version` with the original; it also reports
an outdated CF template a theme overrides now, and theme copies identical to the CF template (they
can go). It replaces the monitor in the Twenty Twenty-Five child theme, which only looked at the child.
The notice (and the scan) only runs on the dashboard, the plugins and updates pages and the event
templates tab; it can be closed for the page view, and is informational when only theme copies are
left. Whether theme copies are reported at all is a choice on the tab (`events-settings`, defaults in
`settings/modules/events/`); the scan keeps them, the notice leaves them out. "CF templates" is the
name: the templates hang on Core Functionality, and other plugins may get the same later.

### AI Access

The module `AiAccess` ("AI-toegang"; October 2026) gives an AI assistant such as Claude abilities of the
WordPress Abilities API, which the MCP Adapter exposes (`meta.mcp.public`). The assistant logs in as a
WordPress user with an application password and works with that user's rights; each ability checks
`edit_posts` and per item `edit_post`. Ability names are `core-functionality/<name>`, category
`content-editing`.

Fixed rule, in `ContentWriter`, whatever the role: the assistant never publishes, schedules, makes private,
trashes or deletes. New items are drafts; existing items change only while they are `draft` or `pending`
(filter `core-functionality/ai-access-editable-statuses`, publishing statuses are always left out). Content
goes through `wp_kses_post()`, terms must exist in a taxonomy of the type's profile, and a content change
that drops a protected block (an event's date or ticket block, a course outline, a lesson's quiz) is
refused.

Feature `ContentEditing` (no dependency) holds the base: `ContentTypes` (profiles per post type, from
`core-functionality/ai-access-content-types`, with the choices of the "AI Access" tab; defaults in
`settings/modules/ai-access/`),
`FieldHandlers` (groups of extra fields under `fields`, from `core-functionality/ai-access-field-handlers`),
`ContentReader`, `ContentWriter`, and the abilities `find-content`, `get-content`, `list-terms`,
`create-draft`, `update-draft`, `submit-for-review` (list filterable: `core-functionality/ai-access-abilities`,
which also hands over the shared `ContentServices`). The other features plug in through those filters and
each has its own dependency (listed below).

**Levels per content type** (Hans, October 2026: every checkbox must say which action it allows):
`read` (find and read, published items too: `find-content`, `get-content`, `list-terms`), `write` (make and
change drafts and items pending review, submit for review: `create-draft`, `update-draft`,
`submit-for-review`; "making" covers layout in blocks, terms and fields too; uploading media is not possible
yet) and `rewrite` (change published content in a copy: `start-rewrite`). They are steps: a level counts only
when every lower one is on (`ContentTypes::has_level()`), saving switches the lower ones on, and a small
script on the tab keeps the checkboxes of a card in steps. Defaults: read and write on, rewrite as the
profile says. The former single choice `enabled` is read as read plus write. Publishing is never an option.
The tab has a short intro and a collapsed explanation (`SettingsExplanation`).

- `RewriteCopies` (`duplicate-post`): ability `start-rewrite` makes a Rewrite & Republish copy of a
  published item (or returns the existing one) as a draft; the assistant changes the copy, the site owner
  publishes it, and Duplicate Post merges it into the original, which keeps its id. Switches on
  `core-functionality/ai-access-rewrite-available`; summaries get `rewrite_of` / `rewrite_copy_id`.
- `EventContent` (`the-events-calendar`): profile `tribe_events`, field group `event` (start, end, all day,
  time zone, venue, organizers, cost) saved through `Tribe__Events__API::saveEventMeta()` with the current
  values filled in (that API resets what it misses; the repository cannot find a draft without dates
  because of TEC's custom tables), ability `list-venues-and-organizers`. Tickets are left alone.
- `CourseContent` (`sensei-lms`): profiles `course` and `lesson`, field group `course`: a lesson's course,
  module and position, placed through `Sensei_Course_Structure` (as the outline block does); a course
  shows its structure read-only; modules are not created here. Quizzes and enrolments are left alone.
- `SeoFields` (`yoast-seo`): field group `seo` (Yoast title and meta description). Yoast's own meta
  watcher rebuilds its indexable after these meta changes (on production; Yoast keeps no indexables on a
  development site).

Tested through MCP on the speeltuin (October 2026): posts, pages, events (dates, venue, organizer, TEC's
custom tables), Sensei (lessons placed in a course, a module and at a position), Yoast's fields and a
rewrite copy of a post merged back by publishing it (the original kept its id, URL and terms), plus
the refusals (published item, unknown term or module, a protected block left out).

Rewrite copies are on by default for posts and pages only; events, courses and lessons get them after a
test that their own data survives copying and merging (Hans, October 2026).

### Duplicate Post

The module `DuplicatePost` (dependency: the `duplicate-post` guard) adjusts Yoast Duplicate Post; it is not
an AI feature, which only needs Duplicate Post to be active. Feature `HideLinks` (named after what it does;
"OwnDuplication" read as owning, Hans, October 2026) hides its links (list rows, admin toolbar, editor,
through `duplicate_post_show_link`) and
bulk actions for the post types whose own plugin duplicates them, such as events (new ticket products per
copy) and Sensei courses (the lessons come along). A plain copy of post and meta would share those and mix
up two items; one duplicate link per item also avoids confusion (Hans, October 2026). The post types stay
enabled in Duplicate Post, so code (the AI Access rewrite copies) can still use it; this refines Yoast's own
setting per content type into one per plugin. The choice is made on the tab "Hide Yoast duplicate links"
(`duplicate-post-settings`, defaults in `settings/modules/duplicate-post/`): one checkbox per active plugin
from the post type catalogue, with "Content types: …" as a small hint under it (all its post types follow,
also those without an admin screen), and "Loose content types" of no known plugin. A collapsed explanation
mentions the AI assistant only as a side note. Stored as `hidden_providers` and `hidden_post_types`;
choices for plugins not on the tab (inactive) are kept on save, and loose types that belong to a plugin on
the tab are dropped (its checkbox decides). Defaults: The Events Calendar (Pro), Event Tickets (Plus) and
Sensei LMS. The filter `core-functionality/duplicate-post-hidden-types` adjusts the resulting post types in
code. The bulk
action filters are added on `admin_init`, so the catalogue is not built on the front end for them.

### Sensei LMS

The module `Sensei` (dependency: the `sensei-lms` guard) adjusts Sensei LMS. Feature `MessageLabels` calls
the post type `sensei_message` "Sensei messages" ("Sensei-berichten") through Sensei's filter
`sensei_register_post_type_sensei_message`, because in Dutch both those and posts are "Berichten", which
confuses lists of post types (Hans, October 2026). The other labels use Sensei's own patterns. Sensei's
submenu item stays "Berichten": under the Sensei menu it is clear.

## Legacy handover

Temporary, until the legacy plugin is gone (agreed with Hans, November 2026).

- Names: the legacy plugin is "Core Functionality Legacy" (folder and repository
  `core-functionality-legacy`, main file still `core-functionality.php`, namespace
  `DeWittePrins\CoreFunctionalityLegacy`, from 1.9.0). This plugin is "Core Functionality" in folder and
  repository `core-functionality`, from 1.0.0. The legacy rename went to production first (1 November
  2026, folder renamed by FTP, themes updated for the namespace): the old legacy install pointed at
  `hansschuijff/core-functionality`, so Git Updater would otherwise have replaced it with this plugin.
- Kept outside the features, so it can be removed in one go: `src/Migration/LegacyHandover.php` and its
  call at the end of the main file. The register is the class constant `TAKEN_OVER` (feature class →
  legacy `files` and/or `callbacks`), not a settings file: it is code knowledge, and `::class` keys break
  loudly when a feature moves.
- Files: the legacy plugin asks `core-functionality-legacy/load-file` (filter, legacy 1.9.1:
  `may_load_file()` in its plugin loader and in every module loader) whether a file, relative to its
  `src/` and without `.php`, may load. This runs while the legacy plugin loads, long before the Kernel
  boots, so "switched on" is read from the register (available here, and automatically on or enabled, for
  feature and module). Plugin dependencies and activation confirmations are not known yet then, so only
  use `files` for features without `confirm_activation`.
- Callbacks: for a file that is only partly taken over, the callback is removed on
  `core-functionality/ready`, and only when the feature really launched.
- Load order: WordPress loads plugins in the order of `active_plugins`, which it sorts as text, and
  `core-functionality-legacy/` sorts before `core-functionality/` (`-` before `/`). The handover keeps this
  plugin directly in front of the legacy plugin (`pre_update_option_active_plugins`, plus one re-save on
  the first request where the order is wrong).
- Not mapped yet: AdminMenuArchitect. It is not complete (no menu data yet; what the legacy
  `admin-nav/src/admin-menu/add-items` and `remove-items` read from config should come from a settings
  section, like the toolbar's), so those legacy files keep loading until it is. The legacy admin menu
  module was hardly used either, so the design is still open (Hans, November 2026).
- Mapped so far: KeepLicenseAlive, Cleaner,
  MenuBuilder, StylesFixer, Visualizer ("Show Node IDs"; its legacy file is commented out in the legacy
  `admin-nav/config/autoload.php`, mapped anyway), ShortBulkActionLabels (`woocommerce/src/bulk-order-cancellation`:
  not ported, WooCommerce has its own `mark_cancelled`; the feature is on by default on the sites)
  BusinessInvoiceTotals and InvoiceDetails (both `woocommerce/src/pdf-invoice-customizations`: the
  file is split over the two, and either one stops it; Hans accepts that, as both are meant to be on
  wherever the shop runs, November 2026) (files). No callbacks are mapped at the moment; the mechanism
  stays for a file that cannot be stopped as a whole.
- Granularity: files are enough. A module loader (`<module>/module-loader`) also passes the filter, so
  stopping it stops the whole legacy module; no folder matching needed.
- Not mapped on purpose (Hans, November 2026): the legacy `debug/` files load on development only
  (`config/autoload-files-env.php`; only `debug/write-to-log` may load on production). TemplateFallback
  takes over theme overrides, not legacy files: the theme comes first, and Hans removes its copies once
  the CF templates are in place. MultiAddToCart and BusinessInvoiceTotals remove the hooks of the code
  they replace (Remote Tickets Server Add-ons, Hans's snippet) themselves while they run; Hans prefers
  that, the new code being better (November 2026).

## Settings and options

- **Settings files** (`settings/`) hold defaults and site data as PHP files returning arrays. General files
  sit in `settings/` (`environment-index.php`, `push-notice-drivers.php`); each module has its own folder
  under `settings/modules/<module-folder>/`. `settings/setup/modules-settings-path.php` maps a module id to
  its folder, and `settings/setup/key-slugs-translation.php` maps a settings key to a file in that folder.
  That is why `Settings::get( $key, $module_id )` takes the module id as second argument. A settings file
  is read as part of `Settings`, so it can compose its value with `$this->get()`.
- **Database overrides**: `Settings::get()` merges the file with the stored option
  (`array_replace_recursive`); `$reset_to_defaults` returns the file only. For lists and trees, where a
  removed item must stay removed, `Settings::get_whole()` takes the stored value as a whole, or else the
  file.
- **Writable defaults**: `Settings::save_default()` writes a value into its settings file, where the folder
  is writable (development); the file then goes along with git and the release. `can_save_default()`,
  `get_default()` and `has_saved()` support a "save as default / follow the default" flow (used by the menu
  builder); following the default again is `Settings::reset()`.
- `Settings` is the holder of all settings, with two helpers: **`SettingsFiles`** for the files (mapping,
  reading, writing) and the **Options vault** (`Options`) for the database: it maps setting tokens to real
  option names, either standalone or grouped in one option, and keeps unknown tokens in a quarantine pool
  until they are assigned. All option access belongs behind `Settings`.
- **Files**: `FileService` is the general reader and writer of data files (PHP files returning an array,
  or JSON, by extension; it creates missing folders). The layers on top say what the files are:
  `SettingsFiles` for the settings defaults, `ComponentsRegisterFiles` for the registers in `kernel/`.

## Admin

Settings → Core Functionality (`dwp-cf-settings`): one page with a sidebar of sections. Core sections:
Feature Activation, Push Notices, Options Management, and on a development environment Release Management. Features add their own section on
`core-functionality/register-settings-sections`. `PluginIntegrityWatch` reports structural problems such as duplicate
ids in the error log and as an admin notice.

The sidebar shows the core sections first, then one group per module with the module's name as title
(modules sorted by name, tabs in registration order), so a tab keeps the context of its module (Hans, October
2026). The module follows from the folder rule (`…\Modules\<Module>\…` → `…\Modules\<Module>\<Module>`);
its name is also a search term. There is no "Other" group: a feature only loads with its module, so a
section in the Modules namespace without a module class is a programming error, written to the PHP error log
and left out of the menu. Sections outside the Modules namespace are plugin settings and stay in the core
group. The footer button is "Save"; a tab can bring its own buttons (`get_action_buttons()`) and reuse
`AdminSettingsPage::get_save_button()` and `get_factory_reset_button()`, as Feature Activation does with
"Scan for new features" between them; the factory reset button stands apart at the far right in red, as the
exceptional action (Hans). The page title is the tab's name (no "Settings" behind it). Tabs can show an
explanation with `SettingsExplanation`: closed by default, with an info icon and a bold summary that
invite to open it. Shared card styles in
`admin-settings.css` (`dwp-cf-card-group` light grey, `dwp-cf-card` white on top, like a difference in
height, with the choices along a blue line) give a calm vertical layout; the AI Access tab uses them, one
card per content type with its levels under each other.

## Naming

| What | Convention |
|---|---|
| PHP namespace | `DeWittePrins\CoreFunctionality\`, PSR-4 under `src/` |
| Text domain | `core-functionality` (final folder name) |
| Hooks | `core-functionality/<kebab-name>` |
| Options, nonces, cron events | `dwp_cf_…` |
| Settings page slug | `dwp-cf-settings` |
| Module | `src/Modules/<Module>/<Module>.php` |
| Feature | `src/Modules/<Module>/Features/<Feature>/<Feature>.php` |
| Environment ids | kebab-case of the guard's class name; the `-theme` suffix picks the guard folder |

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `core-functionality/ready` | action | Everything is launched |
| `core-functionality/register-settings-sections` | action | Add a section to the settings page (`AdminSettingsPage` argument) |
| `core-functionality/launch-timing` | filter | `array( hook, priority )` on which the Kernel boots (default `init`, 0) |
| `core-functionality/configuration-file` | filter | Path of the installation's configuration file that adjusts the release settings |
| `core-functionality/release-settings` | filter | Status and rule per environment of every component, as they apply here |
| `core-functionality/admin-menu-config-nodes` | filter | Admin menu nodes |
| `core-functionality/admin-menu-removals` | filter | Admin menu items to remove |
| `core-functionality/ai-access-content-types` | filter | Content type profiles an AI assistant may use |
| `core-functionality/ai-access-field-handlers` | filter | Groups of extra fields (SEO, event, course) |
| `core-functionality/ai-access-abilities` | filter | The AI Access abilities (with `ContentServices`) |
| `core-functionality/ai-access-content-summary` | filter | The summary of an item the abilities return |
| `core-functionality/ai-access-editable-statuses` | filter | Statuses in which an AI assistant may change an item |
| `core-functionality/ai-access-rewrite-available` | filter | Whether Rewrite & Republish copies can be made |
| `core-functionality/duplicate-post-hidden-types` | filter | Post types for which Duplicate Post shows no links |

## Decisions

| Topic | Decision | Reason |
|---|---|---|
| Successor, not refactor | New plugin next to the legacy one; features move one by one | The site keeps working while the new structure grows |
| Modules only contain features | No behaviour in module classes | One place for behaviour; switching a feature off really switches it off |
| Discovery by folder | Scan `src/Modules/`, strict folder and namespace rules | Adding a feature is adding a folder |
| Static metadata | Checks run before instantiation | Nothing loads that cannot run |
| Runtime proof | Guard classes check classes, functions or actions | "Active" in the plugin list is not proof that a plugin loaded |
| Release settings | Status plus a rule per environment (always active, adjustable, always inactive) in one release file, written on a development environment; an installation adjusts it with a configuration file or a filter | The code's maturity and what suits an environment are the developer's call; a per-site database value would be lost on a restore, and the site keeps its own on/off within the limits |
| Settings as files plus overrides | Files are the defaults and live in git, the database holds changes | Site data is versioned and can be reset to defaults |
| Fail closed | Stop the whole plugin on corrupt settings | Half a kernel is worse than none |
| One boot step on `init` 0 | Check, create and launch together | The register delivers a complete list; on `init` plugins and theme have loaded, so proofs can check what started |
| PHP 8.2+ | Enums, readonly properties, named arguments | Use current PHP features without going far back; production runs PHP 8.5 |
| Guards supply links | Each plugin/theme guard hands in its own toolbar and menu links | The guard knows the external plugin; the menu is composed from these blocks |
| Hook names | `core-functionality/<name>` (October 2026, before production) | "core" alone suggests WordPress core; replaces `dwp_cf_` and `dwp_core_` hooks |
| External contract | Only the ready moment (`core-functionality/ready`, `call_when_ready()`) is promised to other code | Internal timing may change |
| Metadata in one DTO | A static `get_meta()` returns `ComponentMeta`; abstract base classes answer the getters | A feature class holds only its functionality (Hans's idea, October 2026) |
| Version sync of the registers | Plugin version stored with the registers; on a new version the components option is rebuilt from the file and new components get file defaults | An update's new features appear without a manual rescan, and the site's choices stay |
| Risky components ask first | `confirm_activation` in `ComponentMeta`; held back until an administrator answers once (no auto-on, no nagging) | New features stay welcome by default; only where a clash is possible the site decides (Hans, October 2026) |
| Register files optional | Written only when `kernel/` is writable | They are a fallback for the database; production folders are often read-only |
| Toolbar catalogue of all known plugins | The register holds the nodes of every guard with a provider status; the toolbar shows only active ones | A menu is set up once; installing or activating a plugin switches its links on and off |
| Catalogue in memory only | Built per request on first use, not stored in the database | Hrefs can be closures and titles are translated; the cost is small |
| Visibility per menu node | Admin, front end or both, set in the menu builder; the guard's orientation only says what kind of link it is | Where a link is useful depends on the menu, not on the plugin |
| AI writes, Hans publishes | AI Access abilities only write drafts; published items change in a Rewrite & Republish copy (October 2026) | Hans wants to work together with AI but keep the last word on what goes live |
| AI Access extensible | Content types, field groups and abilities come in through filters, one feature per plugin integration | Hans expects to extend and adjust it later; each integration switches on with its own plugin |

## Open points

Known issues:

- The public API is not worked out yet (parked): `CF::initialize()` is never called, so the `Api` facade
  and functions answer "not ready", and `src/Api/helpers.php` is a placeholder (not autoloaded; it calls a
  `Core` class that no longer exists).
- phpcs works again since October 2026 (it silently exited before, see `src/Api/functions.php`) and the
  plugin is clean. Fixing the findings also fixed three bugs: the
  Transient Optimizer compared UTC expiry times with local time (transients removed up to two hours
  early), the WooCommerce category link always used an example slug, and two buttons were never
  translated.
- Built (October 2026): the release settings (status and rule per environment in
  `settings/components-release.php`, the Release Management tab on development, the configuration file and
  filters) and "newly available counts as new" (see Components register). Every built module and feature
  has status production, except the AdminMenu module and its feature, which stay at development (Hans,
  October 2026: they run well and do nothing dangerous); every rule is still "freely adjustable". Tested
  with a simulation of the three environments (DDEV is always development). Testing a production target
  before its status is production needs a development site that behaves like production; accepted for now
  (Hans).
- Open: an info page where modules and features introduce themselves, for "Meer lezen" in the activation
  question and for anyone who does not know them yet (Hans, October 2026). Until then "Meer lezen" shows
  the descriptions.
- Tested in a logged-in admin (October 2026): the "new components" notice after a simulated update, the
  integrity check and all settings tabs. The two issues found there (the toolbar Cleaner reading
  `$node->id` from an array, and feature names ending in a period) are fixed. The plugin is active again
  on the local site.
- All option access goes through `Settings` (October 2026); options of WordPress or another plugin use
  `get_unmanaged()` / `save_unmanaged()`. Transients (as in `KeepLicenseAlive`) still use the WordPress
  functions directly.
- Menu builder (October 2026): titles saved before this version are stored as overrides, also where
  they equal the English title; "Reset titles" on a selection makes nodes follow the translation again.
- Local databases may still hold the old option `wp_cf_options_token_translations` (now `dwp_cf_…`).

Environment proofs (reviewed October 2026):

- Most proofs were chosen when the Kernel still checked on `plugins_loaded` 10, so they use what a plugin's
  main file defines. Now that the Kernel boots on `init`, they could check a plugin's successful start
  instead (for example Event Tickets Plus, which starts on `plugins_loaded` 50). Optional tightening.
- Guards for plugins that are not installed locally (Carbon Fields, Carbon Fields Helper, SearchWP,
  ActiveWoo API Helper, Schema Glue, Sensei Paid Courses) only use the activation check until a proof is
  confirmed.
- The Tribe guards check the Tribe template functions, which catch a Tribe plugin that switched itself
  off after a version conflict. (A `not_actions` check on `tribe_extensions_failed_requirements` was
  removed: that action does not exist in the Tribe plugins.)
- Verified on the local site at Kernel timing: The Events Calendar (+ Pro), Event Tickets (+ Plus),
  WooCommerce, Loco Translate, SEO Redirection Premium, Debug Toolkit. Checked in the sources only: the
  other installed plugins, including Redis Cache and Sensei Pro (WC Paid Courses, folder
  `woothemes-sensei`, which bundles Sensei LMS; its guard extends SenseiLms for the course links).
  Festinger Vault: which folder production uses, left for later. `sensei-paid-courses` may be an old
  name of Sensei Pro (confirm).
- AutomateWoo replaced WooCommerce Follow-Up Emails; the guards for Follow-Up Emails, EU VAT Assistant
  (legacy) and WP phpMyAdmin (not needed) were removed. The legacy AdminToolbar settings files from the
  old plugin went with the LegacyMenuBuilder feature (October 2026); `settings/modules/admin-toolbar/`
  now only holds the menu builder's default (`menu-builder.php`) once one is saved.
- Site-specific URLs in guard code (`/evenementen/`, `/agenda/categorie/familieopstellingen/`,
  `/artikelen/`, the `course-payment-plans` product category) stay for now; they could also become custom
  links on the settings page.

Considered and set aside: keeping a feature's administration (its meta) on a settings screen; options do
not travel with an update to another site.

Later:

- Retiring the legacy plugin: see Legacy handover. Still open there: which features cover the rest of the
  legacy files, and when the legacy plugin can be deactivated on production.
- Logging and debugging (direction, November 2026): the Logger stays a central service, always callable,
  that does nothing when logging is off (switched from inside, through its settings: on/off, log file,
  dump function, later per module or feature via the caller's namespace and a level). An
  `is_enabled()` check only where building the data is expensive. The Logger becomes one instance owned by
  `Plugin` (now it is only created for `InterDependenciesGuard`), so features use `$this->plugin->logger`.
  Outside the plugin, code checks the plugin through the public API, which is still parked (see above);
  only the readiness check is to be switched on before the rest is designed. Readiness has two moments
  (Hans, November 2026; exact names are for the API design): the plugin core is loaded (end of the `Plugin`
  constructor: Settings and Logger available, before `plugins_loaded`) and the features are ready (the
  Kernel has launched them, now `core-functionality/ready`). Each gets a hook, and its check can be
  `did_action()` on that hook. Avoid "kernel" in the first name: the Kernel is what starts the features.
  Today the first moment only covers Settings and the Logger; it is kept as its own moment because more
  core services may follow (open, Hans). The debug helpers of
  the legacy `debug/` folder (plugins list, shortcodes metabox, debug to console, TEC template check)
  become features in a Development module; `write-to-log` and `pretty-printing` go into the Logger. The
  legacy settings (`dewitteprins_core_settings`: `active-logger`, `log-file`, `var-printer`) are the
  starting point; where the Logger's current option `dwp_log_settings` comes from still needs checking.

## Release and translations

`README.md` describes both. A pushed `vX.Y.Z` tag runs `.github/workflows/release.yml`: Composer installs
the autoloader without development packages, the PHP syntax and the header version are checked, and a
zip of the `core-functionality` folder goes to the GitHub release, where Git Updater picks it up.
`languages/` holds the `.pot` and the nl_NL `.po`/`.mo`; the text domain is loaded on `init`.

## Testing

No automated tests. Checks: `composer lint`, `composer phpcs` (WordPress-Core and WordPress-Docs, PHP files
only, PSR-4 file names allowed, `-` and `/` allowed in hook names), and manual checks on the DDEV site
`https://speeltuin.ddev.site`, where the plugin is active. The site's debug log only records fatal errors,
so tests use a temporary mu-plugin (removed afterwards) that logs warnings, notices, `_doing_it_wrong()`
and fatal errors from this plugin. For a plugin that is not active, such a mu-plugin can load it for a
single request.
