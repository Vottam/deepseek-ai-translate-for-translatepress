<?php
/**
 * Interface for translation providers.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Contracts
 */

namespace hollisho\translatepress\translate\deepseek\inc\Contracts;

use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationResponse;

/**
 * Interface ProviderInterface
 *
 * Defines the contract that all translation providers must implement.
 */
interface ProviderInterface {

    /**
     * Get the unique identifier for this provider.
     *
     * @return string Provider identifier (e.g., 'deepseek', 'openai', 'openrouter').
     */
    public function get_id(): string;

    /**
     * Get the human-readable label for this provider.
     *
     * @return string Provider label.
     */
    public function get_label(): string;

    /**
     * Check if the provider is properly configured and ready to translate.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured(): bool;

    /**
     * Validate the API key without exposing it.
     *
     * @param string $api_key The API key to validate.
     * @return bool|true True if valid, false otherwise.
     */
    public function validate_api_key( $api_key );

    /**
     * Translate text from source to target language.
     *
     * @param TranslationRequest $request The translation request.
     * @return TranslationResponse The translation response.
     */
    public function translate( TranslationRequest $request ): TranslationResponse;

    /**
     * Get the API key for this provider.
     *
     * @return string|false The API key or false if not set.
     */
    public function get_api_key();

    /**
     * Get the API URL for this provider.
     *
     * @return string The API URL.
     */
    public function get_api_url(): string;
}
