<?php
/**
 * Provider Throttle — rate limiting and concurrency control.
 *
 * Prevents API saturation by enforcing per-provider rate limits,
 * max concurrent requests, and circuit breaker pattern.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class ProviderThrottle
 *
 * Manages request throttling, concurrency, and circuit breaker state
 * for translation providers.
 */
class ProviderThrottle {

    /**
     * Option name prefix for storing throttle state.
     *
     * @var string
     */
    const OPTION_PREFIX = 'trp_provider_throttle_';

    /**
     * Default max concurrent requests per provider.
     *
     * @var int
     */
    const DEFAULT_MAX_CONCURRENT = 2;

    /**
     * Default requests per minute per provider.
     *
     * @var int
     */
    const DEFAULT_RATE_LIMIT = 20;

    /**
     * Circuit breaker threshold (consecutive failures before opening).
     *
     * @var int
     */
    const CIRCUIT_BREAKER_THRESHOLD = 5;

    /**
     * Circuit breaker cooldown in seconds.
     *
     * @var int
     */
    const CIRCUIT_BREAKER_COOLDOWN = 60;

    /**
     * @var string Provider ID.
     */
    private $provider_id;

    /**
     * @var int Max concurrent requests.
     */
    private $max_concurrent;

    /**
     * @var int Requests per minute.
     */
    private $rate_limit;

    /**
     * Constructor.
     *
     * @param string $provider_id   Provider identifier.
     * @param int    $max_concurrent Max concurrent requests.
     * @param int    $rate_limit     Requests per minute.
     */
    public function __construct(
        string $provider_id,
        int $max_concurrent = self::DEFAULT_MAX_CONCURRENT,
        int $rate_limit = self::DEFAULT_RATE_LIMIT
    ) {
        $this->provider_id   = $provider_id;
        $this->max_concurrent = $max_concurrent;
        $this->rate_limit     = $rate_limit;
    }

    /**
     * Check if a new request is allowed.
     *
     * @return true|\WP_Error True if allowed, WP_Error if throttled.
     */
    public function allow_request() {
        // Check circuit breaker first.
        if ( $this->is_circuit_open() ) {
            return new \WP_Error(
                'circuit_open',
                sprintf( 'Circuit breaker is open for provider %s. Try again later.', $this->provider_id )
            );
        }

        // Check concurrency.
        $active = $this->get_active_count();
        if ( $active >= $this->max_concurrent ) {
            return new \WP_Error(
                'concurrency_limit',
                sprintf(
                    'Provider %s has %d/%d active requests. Try again later.',
                    $this->provider_id,
                    $active,
                    $this->max_concurrent
                )
            );
        }

        // Check rate limit.
        $recent = $this->get_recent_count();
        if ( $recent >= $this->rate_limit ) {
            return new \WP_Error(
                'rate_limit',
                sprintf(
                    'Provider %s rate limit reached: %d/%d requests in last minute.',
                    $this->provider_id,
                    $recent,
                    $this->rate_limit
                )
            );
        }

        return true;
    }

    /**
     * Record that a request has started.
     *
     * @return void
     */
    public function start_request() {
        $this->increment_active_count();
        $this->record_request_timestamp();
    }

    /**
     * Record that a request has completed.
     *
     * @param bool $success Whether the request succeeded.
     *
     * @return void
     */
    public function end_request( bool $success = true ) {
        $this->decrement_active_count();

        if ( $success ) {
            $this->reset_failure_count();
        } else {
            $this->increment_failure_count();
        }
    }

    /**
     * Check if the circuit breaker is open.
     *
     * @return bool
     */
    public function is_circuit_open(): bool {
        $failures = $this->get_failure_count();
        if ( $failures >= self::CIRCUIT_BREAKER_THRESHOLD ) {
            $last_failure = $this->get_last_failure_time();
            if ( $last_failure && ( time() - $last_failure ) < self::CIRCUIT_BREAKER_COOLDOWN ) {
                return true;
            }
            // Cooldown expired, reset.
            $this->reset_failure_count();
        }
        return false;
    }

    /**
     * Get the number of active (in-flight) requests.
     *
     * @return int
     */
    public function get_active_count(): int {
        $count = get_option( $this->get_option_name( 'active' ), 0 );
        return (int) $count;
    }

    /**
     * Get the number of requests in the last minute.
     *
     * @return int
     */
    public function get_recent_count(): int {
        $timestamps = get_option( $this->get_option_name( 'timestamps' ), [] );
        if ( ! is_array( $timestamps ) ) {
            return 0;
        }

        $cutoff = time() - 60;
        $recent = array_filter( $timestamps, function( $ts ) use ( $cutoff ) {
            return $ts >= $cutoff;
        } );

        return count( $recent );
    }

    /**
     * Get consecutive failure count.
     *
     * @return int
     */
    public function get_failure_count(): int {
        return (int) get_option( $this->get_option_name( 'failures' ), 0 );
    }

    /**
     * Get the last failure timestamp.
     *
     * @return int|null
     */
    public function get_last_failure_time() {
        return get_option( $this->get_option_name( 'last_failure' ), null );
    }

    /**
     * Increment active request count.
     *
     * @return void
     */
    private function increment_active_count() {
        $count = $this->get_active_count();
        update_option( $this->get_option_name( 'active' ), $count + 1, false );
    }

    /**
     * Decrement active request count.
     *
     * @return void
     */
    private function decrement_active_count() {
        $count = $this->get_active_count();
        update_option( $this->get_option_name( 'active' ), max( 0, $count - 1 ), false );
    }

    /**
     * Record a request timestamp for rate limiting.
     *
     * @return void
     */
    private function record_request_timestamp() {
        $timestamps = get_option( $this->get_option_name( 'timestamps' ), [] );
        if ( ! is_array( $timestamps ) ) {
            $timestamps = [];
        }

        $timestamps[] = time();

        // Keep only last 2 minutes of timestamps.
        $cutoff = time() - 120;
        $timestamps = array_filter( $timestamps, function( $ts ) use ( $cutoff ) {
            return $ts >= $cutoff;
        } );

        update_option( $this->get_option_name( 'timestamps' ), array_values( $timestamps ), false );
    }

    /**
     * Increment failure count.
     *
     * @return void
     */
    private function increment_failure_count() {
        $count = $this->get_failure_count();
        update_option( $this->get_option_name( 'failures' ), $count + 1, false );
        update_option( $this->get_option_name( 'last_failure' ), time(), false );
    }

    /**
     * Reset failure count.
     *
     * @return void
     */
    private function reset_failure_count() {
        update_option( $this->get_option_name( 'failures' ), 0, false );
        delete_option( $this->get_option_name( 'last_failure' ) );
    }

    /**
     * Build the option name for a given key.
     *
     * @param string $key The state key.
     * @return string
     */
    private function get_option_name( string $key ): string {
        return self::OPTION_PREFIX . $this->provider_id . '_' . $key;
    }

    /**
     * Reset all throttle state for this provider.
     *
     * @return void
     */
    public function reset() {
        delete_option( $this->get_option_name( 'active' ) );
        delete_option( $this->get_option_name( 'timestamps' ) );
        delete_option( $this->get_option_name( 'failures' ) );
        delete_option( $this->get_option_name( 'last_failure' ) );
    }
}
