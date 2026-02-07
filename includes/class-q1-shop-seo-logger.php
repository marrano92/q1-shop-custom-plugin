<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_SEO_Logger {

	const MAX_FILE_SIZE  = 5242880; // 5MB
	const MAX_AGE_DAYS   = 30;
	const LOG_OPTION_KEY = 'q1_seo_log_entries';
	const MAX_ENTRIES    = 50;

	/**
	 * @var string|null
	 */
	private static $log_file = null;

	/**
	 * Get log file path, creating the directory if needed.
	 *
	 * @return string
	 */
	private static function get_log_file() {
		if ( null === self::$log_file ) {
			$log_dir = WP_CONTENT_DIR . '/logs';
			if ( ! file_exists( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
			}
			self::$log_file = $log_dir . '/q1-seo-assistant.log';
		}
		return self::$log_file;
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $level   debug|info|warning|error.
	 * @param string $message Log message.
	 * @param array  $context Additional data.
	 */
	public static function log( $level, $message, $context = array() ) {
		$allowed = array( 'debug', 'info', 'warning', 'error' );
		if ( ! in_array( $level, $allowed, true ) ) {
			$level = 'info';
		}

		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => strtoupper( $level ),
			'message'   => $message,
			'context'   => $context,
		);

		self::write_to_file( $entry );
		self::save_to_option( $entry );
	}

	/**
	 * @param string $message Log message.
	 * @param array  $context Additional data.
	 */
	public static function debug( $message, $context = array() ) {
		self::log( 'debug', $message, $context );
	}

	/**
	 * @param string $message Log message.
	 * @param array  $context Additional data.
	 */
	public static function info( $message, $context = array() ) {
		self::log( 'info', $message, $context );
	}

	/**
	 * @param string $message Log message.
	 * @param array  $context Additional data.
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'warning', $message, $context );
	}

	/**
	 * @param string $message Log message.
	 * @param array  $context Additional data.
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'error', $message, $context );
	}

	/**
	 * Log an API call with timing and outcome.
	 *
	 * @param string         $endpoint    Webhook path.
	 * @param array          $payload     Request payload.
	 * @param array|WP_Error $response    Decoded response or WP_Error.
	 * @param float          $duration_ms Duration in milliseconds.
	 */
	public static function api_call( $endpoint, $payload, $response, $duration_ms ) {
		$is_error = is_wp_error( $response );
		$level    = $is_error ? 'error' : 'info';

		if ( $is_error ) {
			$summary = $response->get_error_message();
		} elseif ( is_array( $response ) && isset( $response['success'] ) ) {
			$summary = 'success';
		} else {
			$summary = 'ok';
		}

		self::log( $level, 'API Call: ' . $endpoint, array(
			'endpoint'         => $endpoint,
			'payload_size'     => strlen( wp_json_encode( $payload ) ),
			'response_ok'      => ! $is_error,
			'response_summary' => $summary,
			'duration_ms'      => $duration_ms,
		) );
	}

	/**
	 * Write entry to log file with rotation.
	 *
	 * @param array $entry Log entry.
	 */
	private static function write_to_file( $entry ) {
		$file = self::get_log_file();

		if ( file_exists( $file ) && filesize( $file ) > self::MAX_FILE_SIZE ) {
			$backup = $file . '.' . gmdate( 'Y-m-d-His' ) . '.bak';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			rename( $file, $backup );
			self::cleanup_old_logs();
		}

		$line = sprintf(
			"[%s] [%s] %s %s\n",
			$entry['timestamp'],
			$entry['level'],
			$entry['message'],
			! empty( $entry['context'] ) ? wp_json_encode( $entry['context'] ) : ''
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Save entry to WP option for settings UI display.
	 *
	 * @param array $entry Log entry.
	 */
	private static function save_to_option( $entry ) {
		$entries = get_option( self::LOG_OPTION_KEY, array() );
		array_unshift( $entries, $entry );
		$entries = array_slice( $entries, 0, self::MAX_ENTRIES );
		update_option( self::LOG_OPTION_KEY, $entries, false );
	}

	/**
	 * Get recent log entries for UI display.
	 *
	 * @param int $limit Max entries.
	 * @return array
	 */
	public static function get_recent_entries( $limit = 50 ) {
		$entries = get_option( self::LOG_OPTION_KEY, array() );
		return array_slice( $entries, 0, $limit );
	}

	/**
	 * Remove backup log files older than MAX_AGE_DAYS.
	 */
	private static function cleanup_old_logs() {
		$log_dir = WP_CONTENT_DIR . '/logs';
		$files   = glob( $log_dir . '/q1-seo-assistant.log.*.bak' );
		if ( ! is_array( $files ) ) {
			return;
		}
		$cutoff = time() - ( self::MAX_AGE_DAYS * DAY_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}
	}

	/**
	 * Clear all log data (file + option).
	 */
	public static function clear() {
		$file = self::get_log_file();
		if ( file_exists( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $file );
		}
		update_option( self::LOG_OPTION_KEY, array(), false );
	}
}
