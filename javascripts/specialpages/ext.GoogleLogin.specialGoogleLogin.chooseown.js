$( function ( $ ) {
	// make sure, the form is infused before adding event listeners
	$( '#googlelogin-createform [data-ooui]' ).each( function () {
		OO.ui.infuse( this );
	} );

	$( '.oo-ui-radioSelectInputWidget input:radio' ).click( function () {
		$( '.mw-googlelogin-wpOwninput' ).toggleClass( 'hidden', this.value !== 'wpOwn' );
	} );
}( jQuery ) );