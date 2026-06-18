<?php
/**
 * Abstract base provider with common functionality.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Providers
 */

namespace hollisho\translatepress\translate\deepseek\inc\Providers;

use hollisho\translatepress\translate\deepseek\inc\Contracts\ProviderInterface;
use hollisho\translatepress\translate\deepseek\inc\Http\HttpClient;
use hollisho\translatepress\translate\deepseek\inc\Logging\RedactedLogger;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationResponse;

/**
 * Class AbstractProvider
 *
 * Base class for translation providers with common HTTP and logging support.
 */
abstract class AbstractProvider implements ProviderInterface {

    /** @var HttpClient */
    protected $http_client;

    /** @var RedactedLogger */
    protected $logger;

    /** @var array */
    protected $settings;

    /**
     * AbstractProvider constructor.
     *
     * @param array $settings Provider settings.
     */
    public function __construct( array $settings = [] ) {
        $this->settings    = $settings;
        $this->logger      = new RedactedLogger( $this->get_id(), $this->is_logging_enabled() );
        $this->http_client = new HttpClient(
            $this->logger,
            $this->get_timeout(),
            $this->get_max_retries()
        );
    }

    /**
     * Get the API URL for this provider.
     *
     * @return string
     */
    abstract public function get_api_url(): string;

    /**
     * Get the API key for this provider.
     *
     * @return string|false
     */
    public function get_api_key() {
        $key_name = $this->get_api_key_setting_name();

        // Settings may be nested under 'trp_machine_translation_settings' (TranslatePress convention)
        if ( isset( $this->settings['trp_machine_translation_settings'][ $key_name ] ) ) {
            return $this->settings['trp_machine_translation_settings'][ $key_name ];
        }

        // Fallback: flat settings structure.
        if ( ! empty( $key_name ) && isset( $this->settings[ $key_name ] ) ) {
            return $this->settings[ $key_name ];
        }

        return false;
    }

    /**
     * Get the settings key name for the API key.
     *
     * @return string
     */
    abstract protected function get_api_key_setting_name(): string;

    /**
     * Check if the provider is configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $api_key = $this->get_api_key();
        return ! empty( $api_key );
    }

    /**
     * Get the timeout for API requests.
     *
     * @return int Timeout in seconds.
     */
    protected function get_timeout(): int {
        return 45;
    }

    /**
     * Get the maximum number of retries.
     *
     * @return int
     */
    protected function get_max_retries(): int {
        return 3;
    }

    /**
     * Check if debug logging is enabled.
     *
     * @return bool
     */
    protected function is_logging_enabled(): bool {
        if ( ! isset( $this->settings['trp_machine_translation_settings']['machine_translation_log'] ) ) {
            return false;
        }
        return $this->settings['trp_machine_translation_settings']['machine_translation_log'] === 'yes';
    }

    /**
     * Build the request payload for the provider API.
     *
     * @param TranslationRequest $request The translation request.
     * @return array The payload array.
     */
    abstract protected function build_payload( TranslationRequest $request ): array;

    /**
     * Build the request headers.
     *
     * @return array The headers array.
     */
    abstract protected function build_headers(): array;

    /**
     * Parse the API response into a TranslationResponse.
     *
     * @param array $response The raw wp_remote_* response.
     * @return TranslationResponse
     */
    abstract protected function parse_response( array $response ): TranslationResponse;

    /**
     * {@inheritDoc}
     */
    public function translate( TranslationRequest $request ): TranslationResponse {
        if ( ! $this->is_configured() ) {
            return TranslationResponse::error( 'not_configured', __( 'Provider is not configured.', 'hollisho-integration-deepseek-for-translatepress' ), $this->get_id() );
        }

        $url     = $this->get_api_url();
        $payload = $this->build_payload( $request );
        $headers = $this->build_headers();

        $this->logger->debug( 'Translation request', [
            'source_lang' => $request->get_source_language(),
            'target_lang' => $request->get_target_language(),
        ] );

        $response = $this->http_client->post( $url, $payload, $headers );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Translation request failed', [ 'error' => $response->get_error_message() ] );
            return TranslationResponse::error( 'request_failed', $response->get_error_message(), $this->get_id() );
        }

        return $this->parse_response( $response );
    }

    /**
     * {@inheritDoc}
     */
    public function validate_api_key( $api_key ) {
        if ( empty( $api_key ) ) {
            return false;
        }
        return $this->perform_key_validation( $api_key );
    }

    /**
     * Perform the actual API key validation.
     *
     * @param string $api_key The API key.
     * @return bool|true
     */
    abstract protected function perform_key_validation( $api_key );
}
