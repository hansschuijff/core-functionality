/**
 * Order preview cancel button: asks for confirmation before the order is cancelled.
 *
 * The preview is rendered after the page has loaded, so the click is caught on the document.
 * The texts come from PHP (window.dwpCfOrderPreviewCancel).
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

( function () {
	'use strict';

	const BUTTON_SELECTOR = '.wc-order-preview .wc-action-button-cancel, .wc-order-preview .wc-action-button-cancel-subscription';

	/**
	 * Returns the confirmation question for the clicked button.
	 *
	 * @param {HTMLElement} button The cancel button.
	 * @return {string} The question.
	 */
	function getQuestion( button ) {
		const texts = window.dwpCfOrderPreviewCancel || {};

		if ( button.classList.contains( 'wc-action-button-cancel-subscription' ) ) {
			return texts.confirmSubscription || '';
		}

		return texts.confirmOrder || '';
	}

	/**
	 * Stops the click when the question is not confirmed.
	 *
	 * @param {MouseEvent} event The click.
	 * @return {void}
	 */
	function confirmCancel( event ) {
		const button = event.target.closest( BUTTON_SELECTOR );
		if ( ! button ) {
			return;
		}

		if ( ! window.confirm( getQuestion( button ) ) ) {
			event.preventDefault();
			event.stopPropagation();
		}
	}

	document.addEventListener( 'click', confirmCancel, true );
} )();
