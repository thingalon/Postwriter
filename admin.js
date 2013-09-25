var Postwriter = {

	size_slider_changed: function( event, ui ) {
		Postwriter.describe_chain_size( ui.value );
	},

	describe_chain_size: function( value ) {
		if ( value < 0 || value > 10 )
			return;
		
		jQuery( 'span#postwriter_chain_size_description' ).html( [
			'F\'tang Crazy',
			'Extremely Random',
			'Very Random',
			'Random',
			'Less Random',
			'Barely Random',
			'Boring',
			'Yawn.'
		][ value - 1 ] );
	},
	
};

jQuery( document ).ready( function() {

	Postwriter.chain_size_slider = jQuery( 'div#postwriter_chain_size' ).slider( {
		value: 4,
		min: 1,
		max: 8,
		step: 1,
		slide: Postwriter.size_slider_changed
	} );
	
	Postwriter.describe_chain_size( 4 );

	jQuery( 'span#postwriter_generate' ).click( function( event ) {
		jQuery( 'span#postwriter_loading' ).show();
		jQuery( 'span#postwriter_generate' ).hide();

		var chain_size = Postwriter.chain_size_slider.slider( 'value' );
		var chain_by = jQuery( 'select#postwriter_chain_by option:selected' ).val();

		var data = {
			action: 'postwriter_generate',
			chain_size: chain_size,
			chain_by: chain_by
		};
		
		jQuery.post( ajaxurl, data, function( data ) {
			jQuery( 'span#postwriter_loading' ).hide();
			jQuery( 'span#postwriter_generate' ).show();
			
			try {
				var response = jQuery.parseJSON( data );
			} catch ( e ) {
				var response = data;
			};
			
			if ( 'object' == typeof( response ) && response.hasOwnProperty( 'title' ) && response.hasOwnProperty( 'body' ) ) {
				jQuery( 'input#title' ).val( response.title ).trigger( 'focus' );

				if ( switchEditors && switchEditors.go )
					switchEditors.go( null, 'html' );
				
				jQuery( 'textarea#content' ).val( response.body );
			} else {
				var error = response.error ? response.error : response;
				if ( ! error )
					error = 'Empty response from the server :(';
				
				alert( error );
			}
		} );
	} );

} );
