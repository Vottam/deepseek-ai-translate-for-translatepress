<?php
/**
 * HTTP client using WordPress HTTP API.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Http
 */

namespace hollisho\translatepress\translate\deepseek\inc\Http;

use hollisho\translatepress\translate\deepseek\inc\Logging\RedactedLogger;
use WP_Error;

/**
 * Class HttpClient
 *
 * Wrapper around wp_remote_post / wp_remote_get with timeout,
 * retry support, and redacted logging.
 */
class HttpClient {

    /** @var RedactedLogger */
    private $logger;

    /** @var int */
    private $default_timeout;

    /** @var int */
    private $max_retries;

    /**
     * HttpClient constructor.
     *
     * @param RedactedLogger|null $logger          Logger instance.
     * @param int                 $default_timeout Default timeout in seconds.
     * @param int                 $max_retries     Maximum retry attempts.
     */
    public function __construct(
        RedactedLogger $logger = null,
        int $default_timeout = 45,
        int $max_retries = 3
    ) {
        $this->logger          = $logger;
        $this->default_timeout = $default_timeout;
        $this->max_retries     = $max_retries;
    }

    /**
     * Send a POST request.
     *
     * @param string $url     The URL.
     * @param array  $body    The request body.
     * @param array  $headers Additional headers.
     * @param int    $timeout Timeout in seconds.
     * @return array|WP_Error The response or WP_Error.
     */
    public function post(
        string $url,
        array $body,
        array $headers = [],
        int $timeout = 0
    ) {
        $timeout = $timeout > 0 ? $timeout : $this->default_timeout;

        $default_headers = [
            'Content-Type' => 'application/json',
        ];

        $args = [
            'method'  => 'POST',
            'timeout' => $timeout,
            'headers' => array_merge( $default_headers, $headers ),
            'body'    => wp_json_encode( $body ),
        ];

        if ( $this->logger ) {
            $this->logger->debug( 'HTTP POST request', [
                'url'     => $url,
                'headers' => $this->redact_headers( $args['headers'] ),
            ] );
        }

        return $this->request_with_retry( $url, $args );
    }

    /**
     * Send a GET request.
     *
     * @param string $url     The URL.
     * @param array  $headers Additional headers.
     * @param int    $timeout Timeout in seconds.
     * @return array|WP_Error The response or WP_Error.
     */
    public function get(
        string $url,
        array $headers = [],
        int $timeout = 0
    ) {
        $timeout = $timeout > 0 ? $timeout : $this->default_timeout;

        $args = [
            'method'  => 'GET',
            'timeout' => $timeout,
            'headers' => $headers,
        ];

        if ( $this->logger ) {
            $this->logger->debug( 'HTTP GET request', [
                'url'     => $url,
                'headers' => $this->redact_headers( $args['headers'] ),
            ] );
        }

        return $this->request_with_retry( $url, $args );
    }

    /**
     * Execute a request with retry logic.
     *
     * @param string $url  The URL.
     * @param array  $args The request arguments.
     * @return array|WP_Error
     */
    private function request_with_retry( string $url, array $args ) {
        $attempt     = 0;
        $last_error  = null;
        $retry_codes = [ 429, 500, 502, 503, 504 ];

        while ( $attempt < $this->max_retries ) {
            $response = wp_remote_request( $url, $args );

            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code >= 200 && $code < 300 ) {
                    if ( $this->logger ) {
                        $this->logger->debug( 'HTTP response OK', [ 'status' => $code ] );
                    }
                    return $response;
                }
                if ( ! in_array( $code, $retry_codes, true ) ) {
                    if ( $this->logger ) {
                        $this->logger->error( 'HTTP non-retryable error', [
                            'status' => $code,
                            'url'    => $url,
                        ] );
                    }
                    return $response;
                }
                $last_error = "HTTP {$code}";
            } else {
                $last_error = $response->get_error_message();
            }

            $attempt++;
            if ( $attempt < $this->max_retries ) {
                $delay = (int) pow( 2, $attempt );
                if ( $this->logger ) {
                    $this->logger->debug( 'HTTP retry', [
                        'attempt' => $attempt,
                        'delay'   => $delay,
                        'error'   => $this->redact_string( $last_error ),
                    ] );
                }
                sleep( $delay );
            }
        }

        if ( $this->logger ) {
            $this->logger->error( 'HTTP request failed after retries', [
                'attempts' => $this->max_retries,
                'url'      => $url,
            ] );
        }

        if ( is_wp_error( $last_error ) ) {
            return $last_error;
        }

        return new WP_Error(
            'http_request_failed',
            sprintf( 'Request failed after %d attempts. Last error: %s', $this->max_retries, $this->redact_string( $last_error ) )
        );
    }

    /**
     * Redact sensitive headers.
     *
     * @param array $headers Headers array.
     * @return array Redacted headers.
     */
    private function redact_headers( array $headers ): array {
        $sensitive_keys = [ 'authorization', 'api-key', 'x-api-key' ];
        $redacted        = [];

        foreach ( $headers as $key => $value ) {
            if ( in_array( strtolower( $key ), $sensitive_keys, true ) ) {
                $redacted[ $key ] = $this->redact_string( (string) $value );
            } else {
                $redacted[ $key ] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Redact a string value, showing only first 4 and last 2 chars.
     *
     * @param string $value The value to redact.
     * @return string The redacted string.
     */
    private function redact_string( string $value ): string {
        $length = strlen( $value );
        if ( $length <= 8 ) {
            return '***';
        }
        return substr( $value, 0, 4 ) . '...' . substr( $value, -2 );
    }
}
