/* global jQuery, PSPTerritorial, wp */
( function ( $ ) {
	'use strict';

	var cfg = window.PSPTerritorial || {};
	var ajaxUrl = cfg.ajaxUrl || '';
	var nonce   = cfg.nonce   || '';
	var i18n    = cfg.i18n   || {};

	/* ─────────────────────────────────────────────────────────────────────────
	 * Tree toggling & lazy-load children
	 * ───────────────────────────────────────────────────────────────────────── */

	$( document ).on( 'click', '.psp-toggle', function () {
		var $node     = $( this ).closest( '.psp-node' );
		var $children = $node.children( '.psp-children' );
		var isOpen    = $node.hasClass( 'psp-open' );

		if ( isOpen ) {
			$node.removeClass( 'psp-open' ).addClass( 'psp-collapsed' );
			$children.slideUp( 150 );
			return;
		}

		// Already loaded?
		if ( 'true' === $children.attr( 'data-loaded' ) ) {
			$node.addClass( 'psp-open' ).removeClass( 'psp-collapsed' );
			$children.slideDown( 150 );
			return;
		}

		// Lazy-load.
		var $spinner = $( '<span class="psp-loading"></span>' );
		$( this ).after( $spinner );

		$.post( ajaxUrl, {
			action      : 'psp_territorial_get_children',
			nonce       : nonce,
			parent_id   : $node.data( 'id' ),
			parent_type : $node.data( 'type' )
		} ).done( function ( res ) {
			$spinner.remove();
			if ( res.success ) {
				$children.html( res.data.html ).attr( 'data-loaded', 'true' );
				$node.addClass( 'psp-open' ).removeClass( 'psp-collapsed' );
				$children.slideDown( 150 );
			}
		} ).fail( function () {
			$spinner.remove();
		} );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Modal helpers
	 * ───────────────────────────────────────────────────────────────────────── */

	function openModal( title, id, type, parentId, name, description ) {
		$( '#psp-modal-title' ).text( title );
		$( '#psp-item-id' ).val( id || '' );
		$( '#psp-item-type' ).val( type || '' );
		$( '#psp-item-parent-id' ).val( parentId || '' );
		$( '#psp-item-name' ).val( name || '' );
		$( '#psp-item-description' ).val( description || '' );
		$( '#psp-form-messages' ).text( '' ).removeClass( 'success error' );
		$( '#psp-modal-overlay' ).fadeIn( 150 );
		$( '#psp-item-name' ).trigger( 'focus' );
	}

	function closeModal() {
		$( '#psp-modal-overlay' ).fadeOut( 150 );
	}

	$( '#psp-modal-close, #psp-modal-cancel' ).on( 'click', closeModal );

	$( '#psp-modal-overlay' ).on( 'click', function ( e ) {
		if ( $( e.target ).is( '#psp-modal-overlay' ) ) {
			closeModal();
		}
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( 27 === e.which && $( '#psp-modal-overlay' ).is( ':visible' ) ) {
			closeModal();
		}
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Add Province button
	 * ───────────────────────────────────────────────────────────────────────── */

	$( '#psp-add-province-btn' ).on( 'click', function () {
		openModal( i18n.addProvince || 'Add Province', '', 'province', '' );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Add Child button (inside tree nodes)
	 * ───────────────────────────────────────────────────────────────────────── */

	$( document ).on( 'click', '.psp-add-child-btn', function ( e ) {
		e.stopPropagation();
		var $btn       = $( this );
		var parentId   = $btn.data( 'parent-id' );
		var parentType = $btn.data( 'parent-type' );
		var childTypes = { province: 'district', district: 'corregimiento', corregimiento: 'community' };
		var childType  = childTypes[ parentType ] || '';
		var titleMap   = {
			district      : i18n.addDistrict      || 'Add District',
			corregimiento  : i18n.addCorregimiento || 'Add Corregimiento',
			community     : i18n.addCommunity     || 'Add Community'
		};
		openModal( titleMap[ childType ] || 'Add Item', '', childType, parentId );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Edit button
	 * ───────────────────────────────────────────────────────────────────────── */

	$( document ).on( 'click', '.psp-edit-btn', function ( e ) {
		e.stopPropagation();
		var $btn  = $( this );
		var id    = $btn.data( 'id' );
		var type  = $btn.data( 'type' );
		var $node = $btn.closest( '.psp-node' );
		var name  = $node.data( 'name' );

		openModal( 'Edit ' + ( type.charAt(0).toUpperCase() + type.slice(1) ), id, type, '', name, '' );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Delete button
	 * ───────────────────────────────────────────────────────────────────────── */

	$( document ).on( 'click', '.psp-delete-btn', function ( e ) {
		e.stopPropagation();
		var $btn  = $( this );
		var id    = $btn.data( 'id' );
		var name  = $btn.data( 'name' );

		if ( ! window.confirm( i18n.confirmDelete + '\n\n"' + name + '"' ) ) {
			return;
		}

		$btn.text( i18n.deleting || 'Deleting…' ).prop( 'disabled', true );

		$.post( ajaxUrl, {
			action  : 'psp_territorial_delete_item',
			nonce   : nonce,
			item_id : id
		} ).done( function ( res ) {
			if ( res.success ) {
				$btn.closest( '.psp-node' ).fadeOut( 200, function () {
					$( this ).remove();
				} );
			} else {
				alert( ( res.data && res.data.message ) || i18n.error );
				$btn.text( 'Delete' ).prop( 'disabled', false );
			}
		} ).fail( function () {
			alert( i18n.error );
			$btn.text( 'Delete' ).prop( 'disabled', false );
		} );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Save form submit
	 * ───────────────────────────────────────────────────────────────────────── */

	$( '#psp-item-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var $btn = $( '#psp-modal-save' );
		var $msg = $( '#psp-form-messages' );
		$btn.text( i18n.saving || 'Saving…' ).prop( 'disabled', true );
		$msg.text( '' ).removeClass( 'success error' );

		$.post( ajaxUrl, {
			action      : 'psp_territorial_save_item',
			nonce       : nonce,
			item_id     : $( '#psp-item-id' ).val(),
			item_type   : $( '#psp-item-type' ).val(),
			parent_id   : $( '#psp-item-parent-id' ).val(),
			name        : $( '#psp-item-name' ).val(),
			description : $( '#psp-item-description' ).val()
		} ).done( function ( res ) {
			$btn.text( 'Save' ).prop( 'disabled', false );

			if ( res.success ) {
				$msg.text( res.data.message ).addClass( 'success' );

				// Update or add node in tree.
				if ( 'inserted' === res.data.action ) {
					// Reload page to show new node (simple approach).
					setTimeout( function () { window.location.reload(); }, 600 );
				} else {
					// Update node name in-place.
					var $node = $( '.psp-node[data-id="' + res.data.id + '"]' );
					$node.find( '> .psp-node-name' ).text( res.data.name );
					$node.attr( 'data-name', res.data.name );
					setTimeout( closeModal, 800 );
				}
			} else {
				$msg.text( ( res.data && res.data.message ) || i18n.error ).addClass( 'error' );
			}
		} ).fail( function () {
			$btn.text( 'Save' ).prop( 'disabled', false );
			$msg.text( i18n.error ).addClass( 'error' );
		} );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Search
	 * ───────────────────────────────────────────────────────────────────────── */

	function doSearch( query ) {
		if ( query.length < 2 ) {
			$( '#psp-search-results' ).hide();
			$( '#psp-search-clear' ).hide();
			return;
		}

		$.get( cfg.restUrl || '/wp-json/psp-territorial/v1/search', { q: query }, function ( data ) {
			var $body = $( '#psp-search-results-body' ).empty();

			if ( ! data || ! data.length ) {
				$body.append( '<tr><td colspan="3">' + ( i18n.noResults || 'No results found.' ) + '</td></tr>' );
			} else {
				$.each( data, function ( i, item ) {
					$body.append(
						'<tr>' +
						'<td>' + $( '<span>' ).text( item.name ).html() + '</td>' +
						'<td>' + $( '<span>' ).text( item.type ).html() + '</td>' +
						'<td>' +
						'<button type="button" class="button button-small psp-edit-btn" data-id="' + parseInt( item.id, 10 ) + '" data-type="' + $( '<span>' ).text( item.type ).html() + '">' + ( i18n.edit || 'Edit' ) + '</button> ' +
						'<button type="button" class="button button-small button-link-delete psp-delete-btn" data-id="' + parseInt( item.id, 10 ) + '" data-name="' + $( '<span>' ).text( item.name ).html() + '" data-type="' + $( '<span>' ).text( item.type ).html() + '">' + ( i18n.deleteLabel || 'Delete' ) + '</button>' +
						'</td>' +
						'</tr>'
					);
				} );
			}

			$( '#psp-search-results' ).show();
			$( '#psp-search-clear' ).show();
		} );
	}

	$( '#psp-search-btn' ).on( 'click', function () {
		doSearch( $( '#psp-search' ).val().trim() );
	} );

	$( '#psp-search' ).on( 'keypress', function ( e ) {
		if ( 13 === e.which ) { doSearch( $( this ).val().trim() ); }
	} );

	$( '#psp-search-clear' ).on( 'click', function () {
		$( '#psp-search' ).val( '' );
		$( '#psp-search-results' ).hide();
		$( this ).hide();
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Import CSV (Import/Export page)
	 * ───────────────────────────────────────────────────────────────────────── */

	$( '#psp-import-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var $file = $( '#psp-csv-file' )[0];
		if ( ! $file.files.length ) { return; }

		var formData = new FormData();
		formData.append( 'action', 'psp_territorial_import_csv' );
		formData.append( 'nonce',  nonce );
		formData.append( 'csv_file', $file.files[0] );

		$( '#psp-import-progress' ).show();
		$( '#psp-import-results' ).hide().empty();
		$( '#psp-import-status' ).text( 'Importing…' );
		$( '.psp-progress-bar-inner' ).css( 'width', '30%' );

		$.ajax( {
			url         : ajaxUrl,
			type        : 'POST',
			data        : formData,
			processData : false,
			contentType : false
		} ).done( function ( res ) {
			$( '.psp-progress-bar-inner' ).css( 'width', '100%' );
			$( '#psp-import-status' ).text( 'Done.' );

			var cls = res.success ? 'success' : 'error';
			var msg = ( res.data && res.data.message ) || ( res.success ? 'Import successful.' : 'Import failed.' );
			$( '#psp-import-results' ).html( '<div class="psp-notice-inline ' + cls + '">' + $( '<span>' ).text( msg ).html() + '</div>' ).show();
		} ).fail( function () {
			$( '#psp-import-status' ).text( 'Error.' );
			$( '#psp-import-results' ).html( '<div class="psp-notice-inline error">' + ( i18n.error || 'An error occurred.' ) + '</div>' ).show();
		} );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Reset to default data
	 * ───────────────────────────────────────────────────────────────────────── */

	$( '#psp-reset-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( 'This will DELETE all current data and re-import the default Panama dataset. Are you sure?' ) ) {
			return;
		}

		var $btn = $( '#psp-reset-btn' ).prop( 'disabled', true ).text( 'Resetting…' );

		$.post( ajaxUrl, {
			action : 'psp_territorial_import_csv',
			nonce  : nonce,
			reset  : 1
		} ).done( function ( res ) {
			var cls = res.success ? 'success' : 'error';
			var msg = ( res.data && res.data.message ) || ( res.success ? 'Reset successful.' : 'Reset failed.' );
			$( '#psp-reset-results' ).html( '<div class="psp-notice-inline ' + cls + '">' + $( '<span>' ).text( msg ).html() + '</div>' ).show();
			$btn.prop( 'disabled', false ).text( 'Reset & Re-import Default Data' );
		} ).fail( function () {
			$( '#psp-reset-results' ).html( '<div class="psp-notice-inline error">' + ( i18n.error || 'An error occurred.' ) + '</div>' ).show();
			$btn.prop( 'disabled', false ).text( 'Reset & Re-import Default Data' );
		} );
	} );

	/* ─────────────────────────────────────────────────────────────────────────
	 * Export buttons
	 * ───────────────────────────────────────────────────────────────────────── */

	$( '#psp-export-json-btn, #psp-export-csv-btn' ).on( 'click', function () {
		var format = $( this ).data( 'format' );
		var $btn   = $( this ).prop( 'disabled', true ).text( 'Exporting…' );

		$.post( ajaxUrl, {
			action : 'psp_territorial_export',
			nonce  : nonce,
			format : format
		} ).done( function ( res ) {
			if ( res.success ) {
				var content  = typeof res.data.data === 'string' ? res.data.data : JSON.stringify( res.data.data, null, 2 );
				var mime     = 'json' === format ? 'application/json' : 'text/csv';
				var blob     = new Blob( [ content ], { type: mime } );
				var url      = URL.createObjectURL( blob );
				var a        = document.createElement( 'a' );
				a.href       = url;
				a.download   = res.data.filename;
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} else {
				alert( ( res.data && res.data.message ) || i18n.error );
			}
		} ).fail( function () {
			alert( i18n.error );
		} ).always( function () {
			$btn.prop( 'disabled', false ).text( 'json' === format ? 'Export as JSON' : 'Export as CSV' );
		} );
	} );

} )( jQuery );
