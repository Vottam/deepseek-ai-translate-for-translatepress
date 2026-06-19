<?php
/**
 * DeepSeek translation provider.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Providers
 */

namespace hollisho\translatepress\translate\deepseek\inc\Providers;

use hollisho\translatepress\translate\deepseek\inc\Helpers\DeepSeekApiHelper;
use hollisho\translatepress\translate\deepseek\inc\Translation\PromptBuilder;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationResponse;

/**
 * Class DeepSeekProvider
 *
 * DeepSeek translation provider using the new infrastructure.
 * Maintains backward compatibility with existing DeepSeek behavior.
 */
class DeepSeekProvider extends AbstractProvider {

    const ENGINE_KEY = 'deepseek_translate';

    const FIELD_API_KEY = 'deepseek-api-key';

    const DEFAULT_API_URL = 'https://api.deepseek.com/chat/completions';

    const DEFAULT_MODEL = 'deepseek-chat';

    /**
     * {@inheritDoc}
     */
    public function get_id(): string {
        return self::ENGINE_KEY;
    }

    /**
     * {@inheritDoc}
     */
    public function get_label(): string {
        return __( 'DeepSeek', 'hollisho-integration-deepseek-for-translatepress' );
    }

    /**
     * {@inheritDoc}
     */
    public function get_api_url(): string {
        return self::DEFAULT_API_URL;
    }

    /**
     * {@inheritDoc}
     */
    protected function get_api_key_setting_name(): string {
        return self::FIELD_API_KEY;
    }

    /**
     * Get the model to use for translation.
     *
     * @return string
     */
    protected function get_model(): string {
        return self::DEFAULT_MODEL;
    }

    /**
     * {@inheritDoc}
     */
    protected function build_payload( TranslationRequest $request ): array {
        $prompt_builder = new PromptBuilder();
        $prompt         = $prompt_builder->build(
            $request->get_source_text(),
            $request->get_source_language(),
            $request->get_target_language()
        );

        return [
            'model'       => $request->get_model() ?? $this->get_model(),
            'temperature' => 0.3,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'max_tokens'  => 4000,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function build_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->get_api_key(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function parse_response( array $response ): TranslationResponse {
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 ) {
            return TranslationResponse::error(
                'http_' . $code,
                sprintf( 'DeepSeek API returned HTTP %d', $code ),
                $this->get_id()
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), false );

        if ( empty( $body ) ) {
            return TranslationResponse::error(
                'empty_response',
                __( 'Empty response from DeepSeek API.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        if ( ! empty( $body->error ) ) {
            return TranslationResponse::error(
                'api_error',
                isset( $body->error->message ) ? $body->error->message : __( 'Unknown DeepSeek API error.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        $translated_content = $this->extract_content( $body );

        if ( null === $translated_content ) {
            return TranslationResponse::error(
                'parse_error',
                __( 'Unable to parse DeepSeek response.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        return TranslationResponse::success(
            $translated_content,
            $this->get_id(),
            isset( $body->model ) ? $body->model : null,
            [
                'usage' => isset( $body->usage ) ? $this->redact_usage( $body->usage ) : null,
            ]
        );
    }

    /**
     * Extract translated content from the DeepSeek response.
     *
     * @param object $body The decoded response body.
     * @return string|null The translated text or null on failure.
     */
    private function extract_content( $body ): ?string {
        $content = $body->choices[0]->message->content ?? null;

        if ( null === $content ) {
            return null;
        }

        // Parse numbered items back to plain text.
        $parsed = DeepSeekApiHelper::parseTranslatedItems( $content, 1 );
        return ! empty( $parsed ) ? implode( "\n", $parsed ) : $content;
    }

    /**
     * Redact usage metadata, keeping only token counts.
     *
     * @param object $usage The usage object.
     * @return array Redacted usage data.
     */
    private function redact_usage( $usage ): array {
        return [
            'prompt_tokens'     => $usage->prompt_tokens ?? null,
            'completion_tokens' => $usage->completion_tokens ?? null,
            'total_tokens'      => $usage->total_tokens ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function perform_key_validation( $api_key ) {
        $request = new TranslationRequest(
            'Hello',
            'en',
            'es'
        );

        $payload = [
            'model'       => $this->get_model(),
            'temperature' => 0.3,
            'messages'    => [
                [ 'role' => 'user', 'content' => 'Translate "Hello" to Spanish.' ],
            ],
            'max_tokens'  => 50,
        ];

        $response = $this->http_client->post(
            $this->get_api_url(),
            $payload,
            [ 'Authorization' => 'Bearer ' . $api_key ],
            15
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        return $code >= 200 && $code < 300;
    }

    /**
     * Send a raw translation request (for backward compatibility with DeepSeekTranslationEngine).
     *
     * @param string $source_language Source language code.
     * @param string $target_language Target language code.
     * @param array  $strings_array    Array of strings to translate.
     * @return array|\WP_Error The raw wp_remote_* response.
     */
    public function send_raw_request( string $source_language, string $target_language, array $strings_array ) {
        $prompt_builder = new PromptBuilder();
        $prompt         = $prompt_builder->build_batch( $strings_array, $source_language, $target_language );

        $payload = $this->build_payload(
            new TranslationRequest( $prompt, $source_language, $target_language )
        );

        // Override the message content with the batch prompt.
        $payload['messages'][0]['content'] = $prompt;

        $headers = $this->build_headers();

        return $this->http_client->post( $this->get_api_url(), $payload, $headers );
    }
}
