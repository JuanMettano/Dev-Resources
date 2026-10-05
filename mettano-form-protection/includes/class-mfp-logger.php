<?php
/**
 * Log of blocked submissions.
 * Stored in wp-content/mfp-logs/ with a random file name (protected on Apache and Nginx).
 */

defined( 'ABSPATH' ) || exit;

class MFP_Logger {

	const KEY_OPTION = 'mfp_log_key';
	const MAX_BYTES  = 1048576; // 1 MB, then it keeps only the newest 500 lines.

	public static function dir() {
		return WP_CONTENT_DIR . '/mfp-logs';
	}

	public static function file() {
		$key = get_option( self::KEY_OPTION );
		if ( ! $key ) {
			$key = wp_generate_password( 16, false, false );
			update_option( self::KEY_OPTION, $key, false );
		}
		return self::dir() . '/log-' . $key . '.txt';
	}

	private static function prepare_dir() {
		$dir = self::dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}

	public static function write( $reason, $email = '', $form = '' ) {
		self::prepare_dir();
		$file = self::file();

		if ( file_exists( $file ) && filesize( $file ) > self::MAX_BYTES ) {
			$lines = file( $file, FILE_IGNORE_NEW_LINES );
			file_put_contents( $file, implode( "\n", array_slice( $lines, -500 ) ) . "\n", LOCK_EX );
		}

		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$line = implode(
			' | ',
			array(
				current_time( 'mysql' ),
				str_replace( array( "\n", '|' ), ' ', $reason ),
				str_replace( array( "\n", '|' ), ' ', $email ),
				$ip,
				str_replace( array( "\n", '|' ), ' ', $form ),
			)
		) . "\n";

		file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Newest entries first.
	 */
	public static function read( $limit = 200 ) {
		$file = self::file();
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$lines = array_reverse( array_slice( $lines, -$limit ) );

		$rows = array();
		foreach ( $lines as $line ) {
			$parts  = array_map( 'trim', explode( ' | ', $line ) );
			$rows[] = array_pad( $parts, 5, '' );
		}
		return $rows;
	}

	public static function count() {
		$file = self::file();
		if ( ! file_exists( $file ) ) {
			return 0;
		}
		return count( file( $file, FILE_SKIP_EMPTY_LINES ) );
	}

	public static function clear() {
		$file = self::file();
		if ( file_exists( $file ) ) {
			file_put_contents( $file, '', LOCK_EX );
		}
	}
}
