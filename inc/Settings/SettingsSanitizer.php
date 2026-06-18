<?php
/**
 * Sanitization for AI Providers settings.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Settings
 */

namespace hollisho\translatepress\translate\deepseek\inc\Settings;

/**
 * Class SettingsSanitizer
 *
 * Sanitizes and validates plugin settings.
 */
class SettingsSanitizer {

    /**
     * Sanitize all settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public static function sanitize( array $input ): array {
        $sanitized = [];

        // Active provider: allowlist.
        $sanitized['active_provider'] = self::sanitize_provider( $input['active_provider'] ?? '' );

        // OpenAI API key: sanitize_text_field + trim.
        $sanitized['openai_api_key'] = self::sanitize_api_key( $input['openai_api_key'] ?? '' );

        // OpenAI model: allowlist.
        $sanitized['openai_model'] = self::sanitize_model( $input['openai_model'] ?? '' );

        // Timeout: integer with min/max.
        $sanitized['openai_timeout'] = self::sanitize_timeout( $input['openai_timeout'] ?? 60 );

        // Max output tokens: integer with min/max.
        $sanitized['openai_max_tokens'] = self::sanitize_max_tokens( $input['openai_max_tokens'] ?? 4096 );

        return $sanitized;
    }

    /**
     * Sanitize provider ID against allowlist.
     *
     * @param string $provider The provider ID.
     * @return string
     */
    public static function sanitize_provider( string $provider ): string {
        if ( in_array( $provider, SettingsRepository::ALLOWED_PROVIDERS, true ) ) {
            return $provider;
        }
        return 'deepseek'; // Safe default.
    }

    /**
     * Sanitize API key.
     *
     * @param string $api_key The API key.
     * @return string
     */
    public static function sanitize_api_key( string $api_key ): string {
        $api_key = trim( $api_key );
        if ( function_exists( 'sanitize_text_field' ) ) {
            $api_key = sanitize_text_field( $api_key );
        }
        return $api_key;
    }

    /**
     * Sanitize model against allowlist.
     *
     * @param string $model The model ID.
     * @return string
     */
    public static function sanitize_model( string $model ): string {
        if ( in_array( $model, SettingsRepository::ALLOWED_MODELS, true ) ) {
            return $model;
        }
        return 'gpt-5.4-mini'; // Safe default.
    }

    /**
     * Sanitize timeout value.
     *
     * @param mixed $timeout The timeout value.
     * @return int
     */
    public static function sanitize_timeout( $timeout ): int {
        $timeout = abs( intval( $timeout ) );
        return max( SettingsRepository::MIN_TIMEOUT, min( SettingsRepository::MAX_TIMEOUT, $timeout ) );
    }

    /**
     * Sanitize max output tokens.
     *
     * @param mixed $max_tokens The max tokens value.
     * @return int
     */
    public static function sanitize_max_tokens( $max_tokens ): int {
        $max_tokens = abs( intval( $max_tokens ) );
        return max( SettingsRepository::MIN_MAX_TOKENS, min( SettingsRepository::MAX_MAX_TOKENS, $max_tokens ) );
    }

    /**
     * Check if a value looks like an API key (for UI display purposes only).
     *
     * @param string $value The value to check.
     * @return bool
     */
    public static function looks_like_api_key( string $value ): bool {
        return (bool) preg_match( '/^sk-[a-zA-Z0-9.-]{20,}$/', $value );
    }

    /**
     * Mask an API key for display.
     *
     * @param string $api_key The API key.
     * @return string Masked key or empty string.
     */
    public static function mask_api_key( string $api_key ): string {
        if ( empty( $api_key ) ) {
            return '';
        }
        $len = strlen( $api_key );
        if ( $len <= 8 ) {
            return '****';
        }
        return substr( $api_key, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $api_key, -4 );
    }
}
