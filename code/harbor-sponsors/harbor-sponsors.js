
jQuery( document ).ready( function( $ ) {

	var file_frame;

	jQuery('#harbor_sponsor_logo_button').on('click', function( event ){

		event.preventDefault();

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		file_frame = wp.media.frames.file_frame = wp.media({
			title: 'Select a Sponsor Logo',
			button: {
				text: 'Use This Logo',
			},
			multiple: false
		});

		file_frame.on( 'select', function() {
			attachment = file_frame.state().get('selection').first().toJSON();
			$( '#harbor_sponsor_logo_image' ).attr( 'src', attachment.url );
			$( '#sponsor_logo' ).val( attachment.id );
		});

		file_frame.open();

	});

	jQuery('#harbor_sponsor_logo_delete_button').on('click', function( event ){

		event.preventDefault();

		$( '#harbor_sponsor_logo_image' ).attr( 'src', '' );
		$( '#sponsor_logo' ).val( '' );

	});

});