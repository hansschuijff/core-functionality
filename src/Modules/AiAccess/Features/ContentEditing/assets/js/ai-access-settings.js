/**
 * AI Access settings tab: keeps the levels of a content type in steps.
 *
 * The levels are read, write (drafts) and rewrite (published content). Checking a level checks
 * the lower ones of the same row; unchecking a level unchecks the higher ones. Saving enforces the
 * same rule, so the tab still works without this script.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

( function () {
	'use strict';

	const LEVELS = [ 'read', 'write', 'rewrite' ];

	/**
	 * Returns the checkboxes of one content type, keyed by level.
	 *
	 * @param {HTMLTableElement} table    The levels table.
	 * @param {string}           postType The post type of the row.
	 * @return {Object<string, HTMLInputElement>} Level => checkbox.
	 */
	function getRowBoxes( table, postType ) {
		const boxes = {};
		table.querySelectorAll( 'input[type="checkbox"][data-level]' ).forEach( ( box ) => {
			if ( box.dataset.type === postType ) {
				boxes[ box.dataset.level ] = box;
			}
		} );

		return boxes;
	}

	/**
	 * Applies the steps after one checkbox changed.
	 *
	 * @param {HTMLTableElement} table   The levels table.
	 * @param {HTMLInputElement} changed The checkbox that changed.
	 * @return {void}
	 */
	function applySteps( table, changed ) {
		const boxes = getRowBoxes( table, changed.dataset.type );
		const index = LEVELS.indexOf( changed.dataset.level );

		LEVELS.forEach( ( level, position ) => {
			const box = boxes[ level ];
			if ( ! box ) {
				return;
			}
			if ( changed.checked && position < index ) {
				box.checked = true;
			}
			if ( ! changed.checked && position > index ) {
				box.checked = false;
			}
		} );
	}

	/**
	 * Listens to the checkboxes of the levels table.
	 *
	 * @return {void}
	 */
	function init() {
		const table = document.querySelector( '.dwp-cf-ai-levels' );
		if ( ! table ) {
			return;
		}

		table.addEventListener( 'change', ( event ) => {
			const box = event.target;
			if ( box instanceof HTMLInputElement && box.dataset.level ) {
				applySteps( table, box );
			}
		} );
	}

	// The script loads in the footer, possibly after the document is ready.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
