/**
 * Opens the submenus of the toolbar menu with a tap on touch screens.
 *
 * WordPress (admin-bar.js) only does this for the top level of the toolbar: a tap on an item
 * deeper down follows its link, so the items below it cannot be reached on a phone. Here a tap
 * on a menu builder item with a submenu opens or closes that submenu instead, on every level,
 * as in the admin menu on a phone. Its link is always one of its items, so it stays reachable.
 *
 * @package DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\MenuBuilder
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.1.3
 */

(function () {
	'use strict';

	// The same test as admin-bar.js: without touch, hovering opens the submenus.
	if (!('ontouchstart' in window)) {
		return;
	}

	/**
	 * Closes an open submenu and the open submenus inside it.
	 *
	 * @param {HTMLElement} item The menu item (li) whose submenu closes.
	 */
	function closeSubmenu(item) {
		[ item ].concat(Array.from(item.querySelectorAll('li.hover'))).forEach(function (open) {
			open.classList.remove('hover');
			setExpanded(open, false);
		});
	}

	/**
	 * Opens a submenu and closes the open submenus beside it.
	 *
	 * @param {HTMLElement} item The menu item (li) whose submenu opens.
	 */
	function openSubmenu(item) {
		Array.from(item.parentElement.children).forEach(function (sibling) {
			if (sibling !== item && sibling.classList.contains('hover')) {
				closeSubmenu(sibling);
			}
		});
		item.classList.add('hover');
		setExpanded(item, true);
	}

	/**
	 * Tells assistive technology whether the submenu of an item is open.
	 *
	 * @param {HTMLElement} item The menu item (li).
	 * @param {boolean}     open Whether its submenu is open.
	 */
	function setExpanded(item, open) {
		const link = item.querySelector(':scope > .ab-item');
		if (link) {
			link.setAttribute('aria-expanded', String(open));
		}
	}

	/**
	 * Opens or closes the submenu of a tapped menu builder item, instead of following its link.
	 *
	 * @param {MouseEvent} event The click (also fired by a tap).
	 */
	function toggleOnTap(event) {
		const link = event.target.closest('#wpadminbar .ab-sub-wrapper li.menupop[id^="wp-admin-bar-dwp-"] > .ab-item');
		if (!link) {
			return;
		}

		event.preventDefault();

		const item = link.parentElement;
		if (item.classList.contains('hover')) {
			closeSubmenu(item);
		} else {
			openSubmenu(item);
		}
	}

	document.addEventListener('click', toggleOnTap);
}());
