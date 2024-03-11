/* global window, document, mw, OO, $ */

$( function () {
	mw.loader.using( [ 'mediawiki.api', 'oojs-ui-widgets', 'oojs-ui-windows' ], function () {
		// Add confirmation dialog to designated links.
		$( '.kolsherutlinks-require-confirmation' ).click( function ( e ) {
			e.preventDefault();
			const target = e.target;

			// Subclass OOUI's ProcessDialog for our modal.
			function ProcessDialog( config ) {
				ProcessDialog.super.call( this, config );
			}
			OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );
			ProcessDialog.static.name = 'kslConfirmation';

			// Confirm and Cancel buttons
			ProcessDialog.static.title = $(target).attr('data-confirmation-title');
			ProcessDialog.static.actions = [
				{
					action: 'confirm',
					label: mw.message( 'kolsherutlinks-confirmation-confirm' ).text(),
					flags: 'primary'
				},
				{
					label: mw.message( 'kolsherutlinks-confirmation-cancel' ).text(),
					flags: 'safe'
				}
			];

			// Confirmation message
			ProcessDialog.prototype.initialize = function () {
				ProcessDialog.super.prototype.initialize.apply( this, arguments );
				this.panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );
				this.panel.$element.append(
					'<p>' + mw.message( 'kolsherutlinks-confirmation-prompt' ).text() + '</p>'
				);
				this.$body.append( this.panel.$element );
			};

			// Action handlers
			ProcessDialog.prototype.getActionProcess = function ( action ) {
				var dialog = this;
				if ( action === 'confirm' ) {
					// Proceed to the linked url
					window.location = target.href;
				}
				return new OO.ui.Process( function () {
					dialog.close( {
						action: action
					} );
				} );
			};

			// Get modal height.
			ProcessDialog.prototype.getBodyHeight = function () {
				return this.panel.$element.outerHeight( true );
			};

			// Instantiate and append the window manager.
			var windowManager = new OO.ui.WindowManager();
			$( document.body ).append( windowManager.$element );

			// Instantiate the modal dialog and add in to the window manager.
			var processDialog = new ProcessDialog( {
				size: 'small'
			} );
			windowManager.addWindows( [ processDialog ] );

			// Open the dialog.
			windowManager.openWindow( processDialog );
		} );
	} );
} );
