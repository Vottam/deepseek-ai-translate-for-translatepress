<?php
/**
 * Settings repository for AI Providers.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Settings
 */

namespace hollisho\translatepress\translate\deepseek\inc\Settings;

/**
 * Class SettingsRepository
 *
 * Reads and writes plugin settings using WordPress Options API.
 */
class SettingsRepository {

    const OPTION_NAME = 'ai_providers_for_translatepress_settings';

    const DEFAULTS = [
        'active_provider'  => 'deepseek',
        'openai_api_key'   => '',
        'openai_model'     => 'gpt-5.4-mini',
        'openai_timeout'   => 60,
        'openai_max_tokens'=> 4096,
    ];

    const ALLOWED_PROVIDERS = [ 'deepseek', 'openai' ];

    const ALLOWED_MODELS = [
        'gpt-5.4-mini',
        'gpt-5.4-nano',
        'gpt-4o-mini',
    ];

    const MIN_TIMEOUT = 10;
    const MAX_TIMEOUT = 120;
    const MIN_MAX_TOKENS = 256;
    const MAX_MAX_TOKENS = 8192;

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_all(): array {
        $settings = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        return array_merge( self::DEFAULTS, $settings );
    }

    /**
     * Get a single setting value.
     *
     * @param string $key     The setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        $settings = $this->get_all();
        return $settings[ $key ] ?? $default;
    }

    /**
     * Get the active provider ID.
     *
     * @return string
     */
    public function get_active_provider(): string {
        return $this->get( 'active_provider', 'deepseek' );
    }

    /**
     * Get the OpenAI API key.
     *
     * @return string
     */
    public function get_openai_api_key(): string {
        return $this->get( 'openai_api_key', '' );
    }

    /**
     * Check if OpenAI API key is configured.
     *
     * @return bool
     */
    public function is_openai_configured(): bool {
        return ! empty( $this->get_openai_api_key() );
    }

    /**
     * Get the OpenAI model.
     *
     * @return string
     */
    public function get_openai_model(): string {
        return $this->get( 'openai_model', 'gpt-5.4-mini' );
    }

    /**
     * Save settings.
     *
     * @param array $settings The settings to save.
     * @return bool
     */
    public function save( array $settings ): bool {
        $sanitized = SettingsSanitizer::sanitize( $settings );
        return update_option( self::OPTION_NAME, $sanitized );
    }

    /**
     * Save a single setting.
     *
     * @param string $key   The setting key.
     * @param mixed  $value The value.
     * @return bool
     */
    public function save_single( string $key, $value ): bool {
        $settings = $this->get_all();
        $settings[ $key ] = $value;
        return $this->save( $settings );
    }

    /**
     * Delete all settings.
     *
     * @return bool
     */
    public function delete_all(): bool {
        return delete_option( self::OPTION_NAME );
    }

    /**
     * Get settings formatted for OpenAIProvider constructor.
     *
     * @return array
     */
    public function get_openai_provider_settings(): array {
        return [
            'trp_machine_translation_settings' => [
                'openai-api-key'    => $this->get_openai_api_key(),
                'openai-model'      => $this->get_openai_model(),
                'openai-timeout'    => $this->get( 'openai_timeout', 60 ),
                'openai-max-tokens' => $this->get( 'openai_max_tokens', 4096 ),
            ],
        ];
    }
}
