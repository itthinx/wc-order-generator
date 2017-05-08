/**
 * order-generator.js
 *
 * Copyright (c) 2017 "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author itthinx
 * @package wc-order-generator
 * @since 1.0.0
 */

var ixwcordergen = {
	running : false,
	generating : false,
	timeout : null,
	limit : null
};

/**
 * Order generator query.
 */
ixwcordergen.generate = function() {

	if ( typeof args === "undefined" ) {
		args = {};
	}

	var $status = jQuery( "#order-generator-status" ),
		$update = jQuery( "#order-generator-update" ),
		$blinker = jQuery( "#order-generator-blinker" );

	$blinker.addClass( 'blinker' );
	$status.html('<p>Generating</p>' );
	if ( !ixwcordergen.generating ) {
		ixwcordergen.generating = true;
		jQuery.ajax({
				type : 'POST',
				url  : ixwcordergen.url,
				data : { "action" : "order_generator", "nonce" : ixwcordergen.nonce },
				complete : function() {
					ixwcordergen.generating = false;
					$blinker.removeClass('blinker');
				},
				success : function ( data ) {
					if ( typeof data.total !== "undefined" ) {
						$update.html( '<p>Total Orders: ' + data.total + '</p>' );
						if ( ixwcordergen.limit !== null ) {
							if ( data.total >= ixwcordergen.limit ) {
								ixwcordergen.stop();
							}
						}
					}
				},
				dataType : "json"
		});
	}
};

ixwcordergen.start = function( url, nonce ) {
	if ( !ixwcordergen.running ) {
		ixwcordergen.running = true;
		ixwcordergen.url = url;
		ixwcordergen.nonce = nonce;
		ixwcordergen.exec();
		var $status = jQuery( "#order-generator-status" );
		$status.html( '<p>Running</p>' );
	}
};

ixwcordergen.exec = function() {
	ixwcordergen.timeout = setTimeout(
		function() {
			if ( ixwcordergen.running ) {
				if ( !ixwcordergen.generating ) {
					ixwcordergen.generate();
				}
				ixwcordergen.exec();
			}
		},
		1000
	);
};

ixwcordergen.stop = function() {
	if ( ixwcordergen.running ) {
		ixwcordergen.running = false;
		clearTimeout( ixwcordergen.timeout );
		var $status = jQuery( "#order-generator-status" );
		$status.html( '<p>Stopped</p>' );
	}
};
