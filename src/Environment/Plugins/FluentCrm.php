<?php
/**
 * FluentCRM Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;

/**
 * Class FluentCrm
 *
 * Represents FluentCRM in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class FluentCrm extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'fluent-crm';
	}

	/**
	 * Returns the name the plugin is known by (its header says "FluentCRM").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'FluentCRM';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'FLUENTCRM_PLUGIN_VERSION' );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm',
				title:       __( 'FluentCRM', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-contacts',
				title:       __( 'Contacts', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/subscribers',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-companies',
				title:       __( 'Companies', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/contact-groups/companies',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-lists',
				title:       __( 'Contact Lists', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/contact-groups/lists',
				short_title: __( 'Lists', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-tags',
				title:       __( 'Contact Tags', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/contact-groups/tags',
				short_title: __( 'Tags', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-campaigns',
				title:       __( 'Email Campaigns', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/email/campaigns',
				short_title: __( 'Campaigns', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-recurring-campaigns',
				title:       __( 'Recurring Campaigns', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/email/recurring-campaigns',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-sequences',
				title:       __( 'Email Sequences', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/email/sequences',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-templates',
				title:       __( 'Email Templates', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/email/templates',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-forms',
				title:       __( 'FluentCRM Forms', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/forms',
				short_title: __( 'Forms', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-automations',
				title:       __( 'Automations', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/funnels',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-reports',
				title:       __( 'FluentCRM Reports', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/reports',
				short_title: __( 'Reports', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-settings',
				title:       __( 'FluentCRM Settings', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-fluent-crm-addons',
				title:       __( 'FluentCRM Add-ons', 'core-functionality' ),
				path:        'admin.php?page=fluentcrm-admin#/add-ons',
				short_title: __( 'Add-ons', 'core-functionality' ),
			),
		);
	}
}
