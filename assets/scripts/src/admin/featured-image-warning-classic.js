/* global document, ogFeaturedImageWarning, window */
( function () {
	'use strict';

	/**
	 * Store the per-user global opt-out.
	 */
	function dismiss() {
		var params = new window.URLSearchParams();
		params.append( 'action', ogFeaturedImageWarning.action );
		params.append( 'nonce', ogFeaturedImageWarning.nonce );
		window.fetch( ogFeaturedImageWarning.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: params
		} ).catch( function () {} );
	}

	/**
	 * Classic editor: delegated click on the notice dismiss button.
	 */
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.og-featured-image-warning .notice-dismiss' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var notice = button.closest( '.og-featured-image-warning' );
		if ( notice && notice.parentNode ) {
			notice.parentNode.removeChild( notice );
		}
		dismiss();
	} );
} )();
