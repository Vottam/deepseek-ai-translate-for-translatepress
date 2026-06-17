<?php
/**
 * Logger with automatic redaction of sensitive data.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Logging
 */

namespace hollisho\translatepress\translate\deepseek\inc\Logging;

/**
 * Class RedactedLogger
 *
 * Logs messages with automatic redaction of API keys, tokens,
 * secrets, and other sensitive information.
 */
class RedactedLogger {

    /** @var string */
    private $context;

    /** @var bool */
    private $enabled;

    /**
     * Patterns that indicate sensitive values.
     *
     * @var string[]
     */
    private static $sensitive_patterns = [
        'api_key',
        'api-key',
        'apikey',
        'authorization',
        'bearer',
        'token',
        'secret',
        'password',
        'passwd',
        'key',
    ];

    /**
     * RedactedLogger constructor.
     *
     * @param string $context Log context prefix.
     * @param bool   $enabled Whether logging is enabled.
     */
    public function __construct( string $context = 'deepseek', bool $enabled = false ) {
        $this->context = $context;
        $this->enabled = $enabled;
    }

    /**
     * Log a debug message.
     *
     * @param string $message The message.
     * @param array  $data    Additional data.
     * @return void
     */
    public function debug( string $message, array $data = [] ): void {
        $this->log( 'DEBUG', $message, $data );
    }

    /**
     * Log an info message.
     *
     * @param string $message The message.
     * @param array  $data    Additional data.
     * @return void
     */
    public function info( string $message, array $data = [] ): void {
        $this->log( 'INFO', $message, $data );
    }

    /**
     * Log an error message.
     *
     * @param string $message The message.
     * @param array  $data    Additional data.
     * @return void
     */
    public function error( string $message, array $data = [] ): void {
        $this->log( 'ERROR', $message, $data );
    }

    /**
     * Core logging method.
     *
     * @param string $level   Log level.
     * @param string $message The message.
     * @param array  $data    Additional data.
     * @return void
     */
    private function log( string $level, string $message, array $data = [] ): void {
        if ( ! $this->enabled || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $redacted_data = $this->redact_data( $data );
        $formatted     = sprintf(
            '[%s][%s] %s %s',
            $this->context,
            $level,
            $message,
            empty( $redacted_data ) ? '' : wp_json_encode( $redacted_data )
        );

        error_log( $formatted );
    }

    /**
     * Recursively redact sensitive data.
     *
     * @param array $data The data to redact.
     * @return array Redacted data.
     */
    private function redact_data( array $data ): array {
        $redacted = [];

        foreach ( $data as $key => $value ) {
            if ( $this->is_sensitive_key( $key ) ) {
                $redacted[ $key ] = $this->redact_value( $value );
            } elseif ( is_array( $value ) ) {
                $redacted[ $key ] = $this->redact_data( $value );
            } else {
                $redacted[ $key ] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Check if a key is sensitive.
     *
     * @param string $key The key to check.
     * @return bool
     */
    private function is_sensitive_key( string $key ): bool {
        $lower = strtolower( $key );
        foreach ( self::$sensitive_patterns as $pattern ) {
            if ( strpos( $lower, $pattern ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Redact a value, keeping only first 4 and last 2 chars.
     *
     * @param mixed $value The value to redact.
     * @return string Redacted value.
     */
    private function redact_value( $value ): string {
        if ( ! is_string( $value ) ) {
            return '[REDACTED_NON_STRING]';
        }
        $length = strlen( $value );
        if ( $length === 0 ) {
            return '[EMPTY]';
        }
        if ( $length <= 8 ) {
            return '***';
        }
        return substr( $value, 0, 4 ) . '...' . substr( $value, -2 );
    }
}
