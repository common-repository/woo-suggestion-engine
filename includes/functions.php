<?php
/**
 * Function include all files in folder
 *
 * @param $path   Directory address
 * @param $ext    array file extension what will include
 * @param $prefix string Class prefix
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! function_exists( 'villatheme_include_folder' ) ) {
	function villatheme_include_folder( $path, $prefix = '', $ext = array( 'php' ) ) {

		/*Include all files in payment folder*/
		if ( ! is_array( $ext ) ) {
			$ext = explode( ',', $ext );
			$ext = array_map( 'trim', $ext );
		}
		$sfiles = scandir( $path );
		foreach ( $sfiles as $sfile ) {
			if ( $sfile != '.' && $sfile != '..' ) {
				if ( is_file( $path . "/" . $sfile ) ) {
					$ext_file  = pathinfo( $path . "/" . $sfile );
					$file_name = $ext_file['filename'];
					if ( $ext_file['extension'] ) {
						if ( in_array( $ext_file['extension'], $ext ) ) {
							if ( $prefix === 'just_require' ) {
								require_once $path . $sfile;
								continue;
							}
							$class = preg_replace( '/\W/i', '_', $prefix . ucfirst( $file_name ) );

							if ( ! class_exists( $class ) ) {
								require_once $path . $sfile;
								if ( class_exists( $class ) ) {
									new $class;
								}
							}
						}
					}
				}
			}
		}
	}
}
if ( ! function_exists( 'villatheme_sanitize_kses' ) ) {
	function villatheme_sanitize_kses( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'villatheme_sanitize_kses', $data );
		} else {
			return is_scalar( $data ) ? wp_kses_post( wp_unslash( $data ) ) : $data;
		}
	}
}
if ( ! function_exists( 'villatheme_json_encode' ) ) {
	function villatheme_json_encode( $value, $options = 256, $depth = 512 ) {
		return wp_json_encode( $value, $options, $depth );
	}
}
if ( ! function_exists( 'viwse_init_set' ) ) {
	function viwse_init_set() {
		ini_set( 'memory_limit', '3000M' );
		ini_set( 'max_execution_time', '3000' );
		ini_set( 'max_input_time', '3000' );
		ini_set( 'default_socket_timeout', '3000' );
		viwse_set_time_limit();
	}
}
if ( ! function_exists( 'viwse_set_time_limit' ) ) {
	function viwse_set_time_limit( $limit = 0 ) {
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( $limit ); // @codingStandardsIgnoreLine
		}
	}
}
