<?php
/**
 * AJAX controller for AI Providers settings.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Admin
 */

namespace hollisho\translatepress\translate\deepseek\inc\Admin;

use hollisho\translatepress\translate\deepseek\inc\Providers\OpenAIProvider;
use hollisho\translatepress\translate\deepseek\inc\Settings\SettingsRepository;

/**
 * Class AjaxController
 *
 * Handles AJAX requests for the settings page.
 */
class AjaxController {

    const NONCE_ACTION = 'ai_providers_validate_key';
    const RATE_LIMIT_TRANSIENT = 'ai_providers_validate_key_rate_limit';
    const RATE_LIMIT_SECONDS = 30;

    /** @var SettingsRepository */
    private $settings;

    /**
     * AjaxController constructor.
     */
    public function __construct() {
        $this->settings = new SettingsRepository();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'wp_ajax_ai_providers_for_translatepress_validate_openai_key', [ $this, 'validate_openai_key' ] );
    }

    /**
     * Handle AJAX request to validate OpenAI API key.
     *
     * @return void
     */
    public function validate_openai_key(): void {
        // 1. Verify nonce.
        if ( ! check_ajax_referer( self::NONCE_ACTION, 'security', false ) ) {
            wp_send_json_error( [
                'message' => __( 'Security check failed. Please refresh the page and try again.', 'hollisho-integration-deepseek-for-translatepress' ),
            ], 403 );
        }

        // 2. Verify capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => __( 'You do not have permission to perform this action.', 'hollisho-integration-deepseek-for-translatepress' ),
            ], 403 );
        }

        // 3. Rate limiting.
        $user_id = get_current_user_id();
        $transient_key = self::RATE_LIMIT_TRANSIENT . '_' . $user_id;
        if ( false !== get_transient( $transient_key ) ) {
            wp_send_json_error( [
                'message' => __( 'Please wait before trying again.', 'hollisho-integration-deepseek-for-translatepress' ),
            ], 429 );
        }
        set_transient( $transient_key, true, self::RATE_LIMIT_SECONDS );

        // 4. Get API key from request or saved settings.
        $api_key = '';
        if ( isset( $_POST['api_key'] ) && ! empty( $_POST['api_key'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
        } else {
            $api_key = $this->settings->get_openai_api_key();
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( [
                'message' => __( 'No API key provided. Please enter an API key first.', 'hollisho-integration-deepseek-for-translatepress' ),
            ], 400 );
        }

        // 5. Validate via OpenAIProvider.
        $provider = new OpenAIProvider( [
            'trp_machine_translation_settings' => [
                'openai-api-key' => $api_key,
            ],
        ] );

        $result = $provider->validate_api_key( $api_key );

        if ( $result ) {
            wp_send_json_success( [
                'message' => __( 'API key is valid!', 'hollisho-integration-deepseek-for-translatepress' ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => __( 'API key is invalid. Please check and try again.', 'hollisho-integration-deepseek-for-translatepress' ),
            ], 400 );
        }
    }
}
