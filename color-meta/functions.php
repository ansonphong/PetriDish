<?php

use League\ColorExtractor\Client as ColorExtractor;


class PW_Colors{

	/**
	 * Construct the class when invoked.
	 */
	function __construct(){

	}

	/**
	 * Uses PHP League's ColorExtractor class to
	 * Select the most used colors from an image.
	 * @todo Make input array.
	 */
	public function get_image_colors( $image_path, $num = 6, $image_format = null ){

		/**
		 * @todo Auto-detect image format if not provided
		 */

		$client = new ColorExtractor;
		switch( $image_format ){
			case 'jpeg':
				$load_image = $client->loadJpeg( $image_path );
				$colors = $load_image->extract( $num );
				break;
			case 'png':
				$load_image = $client->loadPng( $image_path );
				$colors = $load_image->extract( $num );
				break;
			case 'gif':
				$load_image = $client->loadGif( $image_path );
				$colors = $load_image->extract( $num );
				break;
		}

		return $colors;
	}


	/**
	 * Gets a more sophisticated array of metadata
	 * For color values, and perform color processing.
	 *
	 * This is a wrapper for getting the image colors
	 * as well as processing the colors.
	 */
	public function get_image_color_meta( $vars ){

		$default_vars = array(
			
			// Provide the path and format of the request image
			'image_path' 	=> null, 		// [string] Absolute system path to image
			'image_format' 	=> null, 		// [string] jpeg|png|gif
			'number'		=> 3,			// [int] Number of colors to extract
			
			// Optionally, provide an array of hex values
			'hex_values'	=> null,		// [array] If array of hex values already provided, use those instead
			
			// Ordering
			'order_by'		=> 'default',	// [string] Ordering methods, default|lightness
			'order'			=> 'DESC',		// [string] DESC|ASC

			/**
			 * Fields generated at the per color level.
			 * @todo Impliment.
			 */
			'color_fields'	=> array( 'hex', 'rgb', 'hsl', 'tags' ),
			
			/**
			 * Fields generated for the entire color set.
			 * @todo Impliment.
			 */
			'image_fields'	=> array( 'colors', 'averages', 'tags' ),

			// Color Processing
			'processing' => array(
				'hue_snap' 			=> false,	// [array|false]
				'saturation_range' 	=> false, 	// [array|false]
				'lightness_range' 	=> false,	// [array|false]
				),
			);
		$vars = array_replace( $default_vars, $vars );

		if( empty( $vars['image_path'] ) || empty( $vars['image_format'] ) )
			return false;

		/**
		 * HEX VALUES
		 * Get the hexadecimal values for the image.
		 */
		// Get Hex values from the image directly, if image_path is provided
		if( !empty( $vars['image_path'] ) ){
			//$pw_colors = new PW_Colors();
			$hex_values = $this->get_image_colors( $vars['image_path'], $vars['number'], $vars['image_format'] );
		}
		// Otherwise use provided hex values
		elseif( !empty( $vars['hex_values'] ) && is_array( $vars['hex_values'] ) ){
			$hex_values = $vars['hex_values'];
			$vars['number'] = count( $vars['hex_values'] );
		}
		// No colors to go by
		else
			return false;

		/**
		 * Add HSL color to color_fields if it's not present,
		 * and lightness ordering is also requested.
		 */
		if( $vars['order_by'] == 'lightness' && !in_array( 'hsl', $vars['color_fields'] ) ){
			$no_hsl = true;
			$vars['color_fields'][] = 'hsl';
		}

		/**
		 * PROCESS INDIVIDUAL COLORS
		 */
		// Generate array of color metadata
		$colors = array();
		foreach( $hex_values as $hex ){
			$color = array();

			// HEX
			if( in_array( 'hex', $vars['color_fields'] ) ){
				$color['hex'] = $hex;
			}

			// RGB
			$rgb = $this->hex_to_rgb( $hex );
			if( in_array( 'rgb', $vars['color_fields'] ) ){
				$color['rgb'] = $rgb;
				// Add decimal values as color keys
				//$color['red'] = $color['rgb'][0]/255;
				//$color['green'] = $color['rgb'][1]/255;
				//$color['blue'] = $color['rgb'][2]/255;
			}

			// HSL
			$hsl = $this->rgb_to_hsl( $rgb );
			if( in_array( 'hsl', $vars['color_fields'] ) ){
				$color['hsl'] = $hsl;
				// Add values as keys
				//$color['hue'] = $color['hsl'][0];
				//$color['saturation'] = $color['hsl'][1];
				//$color['lightness'] = $color['hsl'][2];
			}

			$colors[] = $color;
		}

		/**
		 * ORDER BY : Volume
		 * ORDER : ASC (ascending)
		 */
		if( $vars['order'] == 'ASC' && $vars['order_by'] == 'default' ){
			$colors = array_reverse($colors);
		}

		/**
		 * ORDER BY : Lightness
		 */
		if( $vars['order_by'] == 'lightness' ){
			//$colors = array_reverse($colors);
			$colors = pw_array_order_by( $colors, 'lightness', SORT_DESC );
			if( $vars['order'] == 'DESC' )
				$colors = array_reverse($colors);
		}

		/**
		 * @todo Impliment additional options here, as extra functions:
		 * @todo Add field to add 'tags', like 'dark', 'green', etc.
		 * 
		 */

		/**
		 * IMAGE TAGS
		 * @see pw_image_color_meta_generate_
		 */
		$image_tags = array();

		/**
		 * IMAGE AVERAGES
		 */
		$image_averages = array();


		/**
		 * Perform color processing based on the provided colors.
		 */
		$colors = $this->process_color_meta( $colors, $vars['processing'] );


		return array(
			'colors' 	=> $colors,
			'tags'		=> $image_tags,
			'averages'	=> $image_averages,
			);

	}


	/**
	 * Processes an array of color meta
	 *
	 */
	public function process_color_meta( $colors, $processing ){

		/**
		 * Check if any processing keys are defined.
		 * If not, return colors right away.
		 */
		$has_processing = false;
		foreach( $processing as $key => $value ){
			if( !empty( $value ) )
				$has_processing = true;
		}
		if( !$has_processing )
			return $colors;

		/**
		 * HUE SNAP
		 * Process Hue Snapping first.
		 */
		if( isset( $processing['hue_snap'] ) && !empty( $processing['hue_snap'] ) )
			$colors = $this->process_hue_snap( $colors, $processing['hue_snap'] );

		/**
		 * SATURATION RANGE
		 * Process Saturation Range second.
		 */
		if( isset( $processing['saturation_range'] ) && !empty( $processing['saturation_range'] ) )
			$colors = $this->process_saturation_range( $colors, $processing['saturation_range'] );

		/**
		 * LIGHTNESS RANGE
		 * Process Lightness Range third.
		 */
		if( isset( $processing['lightness_range'] ) && !empty( $processing['lightness_range'] ) )
			$colors = $this->process_lightness_range( $colors, $processing['lightness_range'] );

		return $colors;

	}

	/**
	 * All colors snap to their nearest hue.
	 *
	 * @param array $colors An array of color meta values.
	 * @param array $hue_snap
	 *	An array of hues (base 360 degrees) to snap colors to.
	 * 	@example array(12,48,128,140,260,320)
	 *
	 * @todo Impliment.
	 */
	public function process_hue_snap( $colors, $hue_snap ){

	}

	/**
	 * Range of saturation values to limit colors to.
	 *
	 * @param array $colors An array of color meta values.
	 * @param array $saturation_range
	 *	First value is low, second is high.
	 *	@example array(0.2,0.8)
	 *
	 * @todo Impliment.
	 */
	public function process_saturation_range( $colors, $saturation_range ){


		


	}

	/**
	 * Range of lightness values to limit colors to.
	 *
	 * @param array $colors An array of color meta values.
	 *	@example array(
	 *				array(
	 *					'hex' => '#000000',
	 *					'rgb' => array(0,0,0),
	 *					'hsl' => array(0,0,0)
	 *					),
	 *				array(
	 *					'hex' => #FFFFFF'
	 *					'rgb' => array(255,255,255),
	 *					'hsl' => array(0,0,1)
	 *					)
	 *				)
	 * @param array $lightness_range
	 * 	@example array(
	 *				'low' => 0.2,			// [decimal] 0-1
	 *				'high' => 0.8,			// [decimal] 0-1
	 *				'distribute' => true,	// [bool]
	 *				'order' => 'ASC',		// [string] If distribute is true. 'ASC' (dark first) | 'DESC' (light first)
	 *				'operator' => 'hsl',	// [string] Which key to operate on
	 *				'fields' => array('hex','rgb','hsl')	// [array] Which fields to return
	 *				)
	 */
	public function process_lightness_range( $colors, $vars ){
		$defult_vars = array(
			'low' => 0,
			'high' => 1,
			'distribute' => false,
			'order' => 'DESC',
			'fields' => array('hex','rgb','hsl')
			);
		$vars = array_replace($defult_vars, $vars);
		// Generate color count
		$color_count = count( $colors );
		if( $color_count <= 1 )
			return $colors;
		/**
		 * Basic lightness range limiting, with no distribution.
		 */
		if( !$vars['distribute'] ){
			for( $i=0; $i<$color_count; $i++ ){
				$color = $colors[$i];
				// Get HSL value
				$hsl = $this->get_hsl( $color );
				// Limit high range
				if( $hsl[2] > $vars['high'] )
					$hsl[2] = $vars['high'];
				// Limit low range
				if( $hsl[2] < $vars['low'] )
					$hsl[2] = $vars['low'];
				// Generte Color Fields
				$color = $this->hsl_to_color_fields( $hsl, $vars['fields'] );
				// Set it into the colors array
				$colors[$i] = $color;
			}
			return $colors;
		}
		/**
		 * Advanced color distribution processing.
		 */
		elseif( $vars['distribute'] ){
			/**
			 * Distribute colors' lightness
			 * From light to dark.
			 */
			// The difference between high and low
			$diff = $vars['high'] - $vars['low'];
			// The step of lightness between color values
			$step = $diff/($color_count-1);
			// Iterate through each color
			for( $i=0; $i<$color_count; $i++ ){
				$color = $colors[$i];
				// Get HSL value
				$hsl = $this->get_hsl( $color );
				/**
				 * Generate stepped HSL value
				 */
				// Ascending values
				if( $vars['order'] == 'DESC' )
					$hsl[2] = $vars['high'] - ($step * $i);
				// Descending values
				if( $vars['order'] == 'ASC' )
					// Stepped HSL value between low and high
					$hsl[2] = $vars['low'] + ($step * $i);
				// Generte Color Fields
				$color = $this->hsl_to_color_fields( $hsl, $vars['fields'] );
				// Set it into the colors array
				$colors[$i] = $color;
			}
			return $colors;
		} else
			return false;
	}

	public function hsl_to_color_fields( $hsl, $fields ){
		$color = array();
		if( in_array( 'hsl', $fields ) )
			$color['hsl'] = $hsl;
		if( in_array( 'rgb', $fields ) )
			$color['rgb'] = $this->hsl_to_rgb( $hsl );
		if( in_array( 'hex', $fields ) )
			$color['hex'] = $this->hsl_to_hex( $hsl );
		return $color;
	}


	/**
	 * Get the HSL value from a color array.
	 * If the HSL isn't specified, generate it
	 * from other specified values.
	 *
	 * @param A_ARRAY $color 
	 *	@example array( 'hex' => '#000000', 'rgb' => array( 0,0,0 ) )
	 * @return array HSL values.
	 */
	public function get_hsl( $color ){
		// Return the HSL value from HSL
		if( isset( $color['hsl'] ) && is_array( $color['hsl'] ) && count( $color['hsl'] == 3 ) ){
			return $color['hsl'];
		// Return the HSL value from RGB
		} elseif( isset( $color['rgb'] ) && is_array( $color['rgb'] ) && count( $color['rgb'] == 3 ) ){
			return $this->rgb_to_hsl( $color['rgb'] );
		// Return the HSL value from HEX
		} elseif( isset( $color['hex'] ) && is_string( $color['hex'] ) ){
			return $this->hex_to_hsl( $color['hex'] );
		} else
			return false;
	}

	public function get_image_tags(){

	}

	public function get_color_tags(){

	}

	/**
	 * Convert hex color string to RGB values.
	 * This function works with both shorthand hex codes such as #f00
	 * and longhand hex codes such as #ff0000. It also accepts the
	 * number sign (#) just in case.
	 *
	 * @param string $hex The color hex code.
	 * @return array An array of RGB integer values, listed in that order.
	 *
	 * @link http://bavotasan.com/2011/convert-hex-color-to-rgb-using-php/
	 */
	public function hex_to_rgb($hex) {
		$hex = str_replace("#", "", $hex);

		if(strlen($hex) == 3) {
			$r = hexdec(substr($hex,0,1).substr($hex,0,1));
			$g = hexdec(substr($hex,1,1).substr($hex,1,1));
			$b = hexdec(substr($hex,2,1).substr($hex,2,1));
		} else {
			$r = hexdec(substr($hex,0,2));
			$g = hexdec(substr($hex,2,2));
			$b = hexdec(substr($hex,4,2));
		}
		$rgb = array($r, $g, $b);
		//return implode(",", $rgb); // returns the rgb values separated by commas
		return $rgb; // returns an array with the rgb values
	}

	/**
	 * Convert RGB to a hex color.
	 *
	 * @param string $hex The color hex code.
	 * @return array An array of RGB integer values, listed in that order.
	 *
	 * @link http://bavotasan.com/2011/convert-hex-color-to-rgb-using-php/
	 */
	public function rgb_to_hex($rgb) {
	   $hex = "#";
	   $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
	   $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
	   $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);

	   return $hex; // returns the hex value including the number sign (#)
	}

	/**
	 * Converts an array of RGB values to an
	 * Array of HSL values.
	 *
	 * @param array $rgb The RGB values, listed as integers in an array.
	 * @return array An array of HSL integer values, listed in that order.
	 *
	 * @link http://www.brandonheyer.com/2013/03/27/convert-hsl-to-rgb-and-rgb-to-hsl-via-php/
	 */
	public function rgb_to_hsl( $rgb ) {
		if( !is_array( $rgb ) || count($rgb) !== 3  )
			return false;

		$oldR = $r = $rgb[0];
		$oldG = $g = $rgb[1];
		$oldB = $b = $rgb[2];

		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );

		$h;
		$s;
		$l = ( $max + $min ) / 2;
		$d = $max - $min;

			if( $d == 0 ){
				$h = $s = 0; // achromatic
			} else {
				$s = $d / ( 1 - abs( 2 * $l - 1 ) );

			switch( $max ){
					case $r:
						$h = 60 * fmod( ( ( $g - $b ) / $d ), 6 ); 
							if ($b > $g) {
							$h += 360;
						}
						break;

					case $g: 
						$h = 60 * ( ( $b - $r ) / $d + 2 ); 
						break;

					case $b: 
						$h = 60 * ( ( $r - $g ) / $d + 4 ); 
						break;
				}			        	        
		}

		return array( round( $h, 2 ), round( $s, 2 ), round( $l, 2 ) );
	}

	/**
	 * Converts an array of RGB values to an
	 * Array of HSL values.
	 *
	 * @param array $hsl The HSL values, listed as integers in an array.
	 * @return array An array of RGB integer values, listed in that order.
	 *
	 * @link http://www.brandonheyer.com/2013/03/27/convert-hsl-to-rgb-and-rgb-to-hsl-via-php/
	 */
	public function hsl_to_rgb( $hsl ){
		if( !is_array( $hsl ) || count($hsl) !== 3  )
			return false;

		$h = $hsl[0];
		$s = $hsl[1];
		$l = $hsl[2];

		$r; 
		$g; 
		$b;

		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( ( $h / 60 ), 2 ) - 1 ) );
		$m = $l - ( $c / 2 );

		if ( $h < 60 ) {
			$r = $c;
			$g = $x;
			$b = 0;
		} else if ( $h < 120 ) {
			$r = $x;
			$g = $c;
			$b = 0;			
		} else if ( $h < 180 ) {
			$r = 0;
			$g = $c;
			$b = $x;					
		} else if ( $h < 240 ) {
			$r = 0;
			$g = $x;
			$b = $c;
		} else if ( $h < 300 ) {
			$r = $x;
			$g = 0;
			$b = $c;
		} else {
			$r = $c;
			$g = 0;
			$b = $x;
		}

		$r = ( $r + $m ) * 255;
		$g = ( $g + $m ) * 255;
		$b = ( $b + $m  ) * 255;

		return array( floor( $r ), floor( $g ), floor( $b ) );
	}

	/**
	 * Converts color values from hexadecimal to HSL
	 *
	 * @param string $hex Hexidecimal value
	 * @return array An array of HSL values
	 */
	public function hex_to_hsl( $hex ){
		return $this->rgb_to_hsl( $this->hex_to_rgb( $hex ) );
	}

	/**
	 * Converts color values from HSL to hexadecimal
	 *
	 * @param array $hsl An array of HSL values
	 * @return string The hexidecimal value.
	 */
	public function hsl_to_hex( $hsl ){
		return $this->rgb_to_hex( $this->hsl_to_rgb( $hsl ) );
	}


}



/**
 * Sorts an associative array by the value of a specified key.
 *
 * @param A_ARRAY $array
 * @param string $key Key to order by.
 * @param string $order How to order, SORT_DESC|SORT_ASC
 *
 * @example pw_array_order_by( $items, $score_key, SORT_DESC );
 */
if( !function_exists( 'pw_array_order_by' ) ){
	function pw_array_order_by(){
		$args = func_get_args();
		$data = array_shift($args);
		foreach ($args as $n => $field) {
			if (is_string($field)) {
				$tmp = array();
				foreach ($data as $key => $row)
					$tmp[$key] = $row[$field];
				$args[$n] = $tmp;
				}
		}
		$args[] = &$data;
		call_user_func_array('array_multisort', $args);
		return array_pop($args);
	}
}
