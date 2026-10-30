/* global ogFeaturedImageWarning, window, wp */
( function () {
	'use strict';

	/**
	 * Fill a "%1$s"-style template from an array of arguments.
	 */
	function format( template, args ) {
		return String( template ).replace( /%(\d+)\$s/g, function ( match, index ) {
			var value = args[ index - 1 ];
			return typeof value === 'undefined' ? match : String( value );
		} );
	}

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

	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;

	/**
	 * Block editor: wrap the featured image component and prepend the warning.
	 */
	wp.hooks.addFilter(
		'editor.PostFeaturedImage',
		'og/featured-image-width-warning',
		wp.compose.createHigherOrderComponent( function ( FeaturedImage ) {
			return function ( props ) {
				var state = wp.element.useState( false );
				var isDismissed = state[ 0 ];
				var setDismissed = state[ 1 ];
				var media = useSelect( function ( select ) {
					var id = select( 'core/editor' ).getEditedPostAttribute( 'featured_media' );
					return id ? select( 'core' ).getMedia( id ) : null;
				}, [] );
				if ( isDismissed || ! media || ! media.media_details || ! media.media_details.width ) {
					return el( FeaturedImage, props );
				}
				var width = media.media_details.width;
				var height = media.media_details.height || 0;
				var minWidth = ogFeaturedImageWarning.minWidth;
				if ( width >= minWidth ) {
					return el( FeaturedImage, props );
				}
				return el(
					'div',
					{},
					el(
						'div',
						{
							className: 'og-featured-image-warning',
							role: 'alert',
							style: {
								position: 'relative',
								margin: '0 0 12px',
								padding: '12px',
								border: '1px solid #dba617',
								borderLeftWidth: '4px',
								background: '#fff',
								boxShadow: '0 1px 1px rgba(0, 0, 0, 0.04)',
								fontSize: '13px'
							}
						},
						el(
							'button',
							{
								type: 'button',
								className: 'og-featured-image-warning__dismiss',
								'aria-label': ogFeaturedImageWarning.dismiss,
								style: {
									position: 'absolute',
									top: '6px',
									right: '8px',
									width: '24px',
									height: '24px',
									minHeight: '24px',
									padding: '0',
									margin: '0',
									border: 'none',
									background: 'transparent',
									color: '#757575',
									fontSize: '16px',
									lineHeight: '24px',
									cursor: 'pointer'
								},
								onClick: function () {
									setDismissed( true );
									dismiss();
								}
							},
							el(
								'span',
								{ 'aria-hidden': 'true' },
								'×'
							)
						),
						el(
							'p',
							{ style: { margin: '0', paddingRight: '28px' } },
							format( ogFeaturedImageWarning.message, [ width, height, minWidth ] )
						)
					),
					el( FeaturedImage, props )
				);
			};
		} )
	);
} )();
