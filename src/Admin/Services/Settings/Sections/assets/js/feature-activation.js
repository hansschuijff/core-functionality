/**
 * Core Framework - Feature Activation Panel Interaction Pipeline.
 *
 * Handles bulk toggling, individual module state switches, and accordion animations.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

jQuery( document ).ready( function( $ ) {

	var $toggleAllBtn = $( '.js-dwp-toggle-all' );
	var $moduleGrid   = $( '.dwp-modules-grid' );

	// ==========================================================================
	// 1. MASTER BULK TOGGLE (ALLES IN- / UITSCHAKELEN)
	// ==========================================================================
	$toggleAllBtn.on( 'click', function( e ) {
		e.preventDefault();

		var $button  = $( this );
		var currentState = $button.attr( 'data-state' );
		var $cards   = $moduleGrid.find( '.dwp-module-card' );
		// Checkboxes the release decides about (disabled) keep their state.
		var $inputs  = $moduleGrid.find( 'input[type="checkbox"]:not(:disabled)' );
		var $containers = $moduleGrid.find( '.js-dwp-features-container' );

		if ( 'select' === currentState ) {
			// Activate all modules and features globally.
			$inputs.prop( 'checked', true );
			$cards.removeClass( 'is-disabled' );
			$containers.slideDown( 150 );

			// Toggle button metadata and text strings.
			$button.attr( 'data-state', 'unselect' ).text( 'Alles uitschakelen' );
		} else {
			// Deactivate all modules and features globally.
			$inputs.prop( 'checked', false );
			$cards.addClass( 'is-disabled' );
			$containers.slideUp( 150 );

			// Toggle button metadata and text strings.
			$button.attr( 'data-state', 'select' ).text( 'Alles inschakelen' );
		}
	});

	// ==========================================================================
	// 2. DYNAMISCHE IN- / UITKLAP LOGICA PER INDIVIDUELE MODULEKAART
	// ==========================================================================
	$moduleGrid.on( 'change', '.js-dwp-module-toggle', function() {
		var $checkbox = $( this );
		var $card     = $checkbox.closest( '.dwp-module-card' );
		var $features = $card.find( '.js-dwp-features-container' );

		if ( $checkbox.is( ':checked' ) ) {
			$card.removeClass( 'is-disabled' );
			$features.slideDown( 150 );

			// UX Service: Automatically activate underlying children features on parent boot.
			$features.find( 'input[type="checkbox"]:not(:disabled)' ).prop( 'checked', true );
		} else {
			$card.addClass( 'is-disabled' );
			$features.slideUp( 150 );

			// UX Service: Deactivate children configurations safely.
			$features.find( 'input[type="checkbox"]:not(:disabled)' ).prop( 'checked', false );
		}

		// Verify state of the master button after single interactions.
		updateMasterButtonState();
	});

	// ==========================================================================
	// 3. PRE-FLIGHT INITIALIZATION STATUS CHECK
	// ==========================================================================
	function initFeatureActivationState() {
		$moduleGrid.find( '.js-dwp-module-toggle' ).each( function() {
			var $checkbox = $( this );
			var $card     = $checkbox.closest( '.dwp-module-card' );
			var $features = $card.find( '.js-dwp-features-container' );

			if ( ! $checkbox.is( ':checked' ) ) {
				$card.addClass( 'is-disabled' );
				$features.hide();
			} else {
				$card.removeClass( 'is-disabled' );
				$features.show();
			}
		});

		updateMasterButtonState();
	}

	/**
	 * Evaluates checked elements to dynamically correct the master button label.
	 */
	function updateMasterButtonState() {
		var totalCheckboxes = $moduleGrid.find( 'input[type="checkbox"]:not(:disabled)' ).length;
		var checkedCount    = $moduleGrid.find( 'input[type="checkbox"]:not(:disabled):checked' ).length;

		if ( checkedCount === 0 ) {
			$toggleAllBtn.attr( 'data-state', 'select' ).text( 'Alles inschakelen' );
		} else if ( checkedCount === totalCheckboxes ) {
			$toggleAllBtn.attr( 'data-state', 'unselect' ).text( 'Alles uitschakelen' );
		}
	}

	// ==========================================================================
	// 4. FILTER: SHOW ONLY DISABLED MODULES AND FEATURES
	// ==========================================================================
	// Works on the saved state (data-saved-running), so a row stays visible while it is ticked.
	// Hidden checkboxes are still submitted, so filtering never changes what is saved.
	var $filterBtn     = $( '.js-dwp-filter-disabled' );
	var $nothingNotice = $( '.js-dwp-nothing-disabled' );

	$filterBtn.on( 'click', function( e ) {
		e.preventDefault();

		var isFiltering = 'true' !== $filterBtn.attr( 'aria-pressed' );
		var visibleCards = 0;

		$moduleGrid.toggleClass( 'is-showing-disabled-only', isFiltering );

		$moduleGrid.find( '.dwp-module-card' ).each( function() {
			var $card          = $( this );
			var moduleDisabled = '0' === $card.find( '.dwp-module-row' ).attr( 'data-saved-running' );
			var hasDisabled    = $card.find( '.dwp-feature-row[data-saved-running="0"]' ).length > 0;
			var filteredOut    = isFiltering && ! moduleDisabled && ! hasDisabled;

			$card.toggleClass( 'is-filtered-out', filteredOut );
			if ( ! filteredOut ) {
				visibleCards++;
			}
		});

		$nothingNotice.prop( 'hidden', ! isFiltering || visibleCards > 0 );
		$filterBtn
			.attr( 'aria-pressed', isFiltering ? 'true' : 'false' )
			.text( $filterBtn.attr( isFiltering ? 'data-label-all' : 'data-label-filter' ) );
	});

	// Launch structural evaluations.
	initFeatureActivationState();
});
