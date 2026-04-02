/* global ajaxurl */
(function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Confirm before deleting
	// -------------------------------------------------------------------------
	$( document ).on( 'click', '.psp-delete-btn', function ( e ) {
		var name = $( this ).data( 'name' ) || '';
		var msg  = pspTerritorial.confirmDelete.replace( '%s', name );
		if ( ! window.confirm( msg ) ) {
			e.preventDefault();
		}
	} );

	// -------------------------------------------------------------------------
	// Dynamic parent dropdown (handled inline in edit-entity.php via fetch API)
	// -------------------------------------------------------------------------

} )( typeof jQuery !== 'undefined' ? jQuery : { fn: {}, extend: function(){} } );
