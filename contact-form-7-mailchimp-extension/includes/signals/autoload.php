<?php
defined( 'ABSPATH' ) || exit;

spl_autoload_register( function ( $class ) {
	$prefix = 'Cmatic\\Metrics\\';
	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	}
} );
