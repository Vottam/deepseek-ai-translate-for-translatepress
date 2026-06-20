<?php
/**
 * OpenRouter translation provider using Chat Completions API.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Providers
 */

namespace hollisho\translatepress\translate\deepseek\inc\Providers;

use hollisho\translatepress\translate\deepseek\inc\Translation\PlaceholderPreserver;
use hollisho\translatepress\translate\deepseek\inc\Translation\PromptBuilder;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationResponse;

/**
 * Class OpenRouterProvider
 *
 * OpenRouter translation provider using the Chat Completions API
 * (POST https://openrouter.ai/api/v1/chat/completions).
 *
 * @see https://openrouter.ai/docs/api-reference/overview
 */
class OpenRouterProvider extends AbstractProvider {

    const ENGINE_KEY = 'openrouter';

    const FIELD_API_KEY = 'openrouter-api-key';

    const FIELD_MODEL = 'openrouter-model';

    const DEFAULT_API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    const DEFAULT_MODEL = 'openai/gpt-4o-mini';

    const DEFAULT_MAX_OUTPUT_TOKENS = 4096;

    const DEFAULT_TIMEOUT = 60;

    /** @var PromptBuilder */
    private $prompt_builder;

    /** @var PlaceholderPreserver */
    private $placeholder_preserver;

    /**
     * {@inheritDoc}
     */
    public function __construct( array $settings = [] ) {
        parent::__construct( $settings );
        $this->prompt_builder       = new PromptBuilder();
        $this->placeholder_preserver = new PlaceholderPreserver();
    }

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
        return __( 'OpenRouter', 'hollisho-integration-deepseek-for-translatepress' );
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
     * {@inheritDoc}
     */
    protected function get_timeout(): int {
        return self::DEFAULT_TIMEOUT;
    }

    /**
     * Get the model to use.
     *
     * First checks TranslationRequest for a model override, then falls back
     * to the stored openrouter-model setting, then to the default.
     *
     * @param TranslationRequest|null $request Optional request for model override.
     * @return string The model slug.
     */
    protected function get_model( TranslationRequest $request = null ): string {
        if ( $request && $request->get_model() ) {
            return $request->get_model();
        }

        $stored_model = $this->get_stored_model();
        if ( ! empty( $stored_model ) ) {
            return $stored_model;
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * Get the model from stored settings.
     *
     * @return string|false
     */
    protected function get_stored_model() {
        $settings = $this->settings['trp_machine_translation_settings'] ?? [];
        return $settings[ self::FIELD_MODEL ] ?? false;
    }

    /**
     * Get max output tokens.
     *
     * @return int
     */
    protected function get_max_output_tokens(): int {
        return self::DEFAULT_MAX_OUTPUT_TOKENS;
    }

    /**
     * {@inheritDoc}
     */
    protected function build_payload( TranslationRequest $request ): array {
        $source_text = $request->get_source_text();
        $model       = $this->get_model( $request );

        // Preserve placeholders before building prompt.
        $preserved_text = $this->placeholder_preserver->preserve( $source_text );

        // Build the translation instructions.
        $instructions = $this->build_instructions( $request );

        $payload = [
            'model'       => $model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => $instructions,
                ],
                [
                    'role'    => 'user',
                    'content' => $preserved_text,
                ],
            ],
            'max_tokens'  => $this->get_max_output_tokens(),
            'temperature' => 0.3,
        ];

        $this->logger->debug( 'OpenRouter payload built', [
            'model'            => $model,
            'input_length'     => strlen( $preserved_text ),
            'has_placeholders' => ! empty( $this->placeholder_preserver->get_placeholders() ),
        ] );

        return $payload;
    }

    /**
     * Build the instructions for the Chat Completions API.
     *
     * @param TranslationRequest $request The translation request.
     * @return string The instructions string.
     */
    protected function build_instructions( TranslationRequest $request ): string {
        $source_language = $request->get_source_language();
        $target_language = $request->get_target_language();

        $target_name = $this->get_language_name( $target_language );

        $instructions = "You are a professional translator. ";
        $instructions .= "Translate the following content to {$target_name}. ";
        $instructions .= "Maintain a professional tone. ";
        $instructions .= "Return ONLY the translated text. ";
        $instructions .= "Do NOT add numbering, bullets, labels, quotes, markdown, explanations, or comments. ";
        $instructions .= "Preserve all HTML tags, shortcodes, placeholders (%s, %d, {name}, {{var}}), and special characters exactly as they are. ";
        $instructions .= "Do NOT translate placeholders or variable names.";

        if ( $source_language && $source_language !== 'auto' ) {
            $source_name = $this->get_language_name( $source_language );
            $instructions .= " The source language is {$source_name}.";
        }

        return $instructions;
    }

    /**
     * Get human-readable language name.
     *
     * @param string $language_code The language code.
     * @return string
     */
    private function get_language_name( string $language_code ): string {
        $languages = [
            'ar' => 'Arabic', 'bg' => 'Bulgarian', 'cs' => 'Czech', 'da' => 'Danish',
            'de' => 'German', 'el' => 'Greek', 'en' => 'English', 'es' => 'Spanish',
            'et' => 'Estonian', 'fi' => 'Finnish', 'fr' => 'French', 'hu' => 'Hungarian',
            'id' => 'Indonesian', 'it' => 'Italian', 'ja' => 'Japanese', 'ko' => 'Korean',
            'lt' => 'Lithuanian', 'lv' => 'Latvian', 'nb' => 'Norwegian Bokmål', 'nl' => 'Dutch',
            'pl' => 'Polish', 'pt' => 'Portuguese', 'pt_BR' => 'Brazilian Portuguese',
            'ro' => 'Romanian', 'ru' => 'Russian', 'sk' => 'Slovak', 'sl' => 'Slovenian',
            'sv' => 'Swedish', 'tr' => 'Turkish', 'uk' => 'Ukrainian',
            'zh-cn' => 'Chinese (Simplified)', 'zh-tw' => 'Chinese (Traditional)',
            'zh_CN' => 'Chinese (Simplified)', 'zh_TW' => 'Chinese (Traditional)',
        ];

        if ( isset( $languages[ $language_code ] ) ) {
            return $languages[ $language_code ];
        }

        $base = explode( '_', $language_code )[0];
        return $languages[ $base ] ?? $language_code;
    }

    /**
     * {@inheritDoc}
     */
    protected function build_headers(): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->get_api_key(),
            'HTTP-Referer'  => home_url(),
            'X-OpenRouter-Title' => get_bloginfo( 'name' ),
        ];

        return $headers;
    }

    /**
     * {@inheritDoc}
     */
    protected function parse_response( array $response ): TranslationResponse {
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 ) {
            return $this->parse_error_response( $response, $code );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), false );

        if ( empty( $body ) ) {
            $this->logger->error( 'OpenRouter empty response body', [ 'status' => $code ] );
            return TranslationResponse::error(
                'empty_response',
                __( 'Empty response from OpenRouter API.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        // Check for API-level errors.
        if ( isset( $body->error ) && ! empty( $body->error ) ) {
            $error_message = is_string( $body->error ) ? $body->error : ( $body->error->message ?? 'Unknown OpenRouter error' );
            $error_code    = is_string( $body->error ) ? 'api_error' : ( $body->error->code ?? 'api_error' );
            $this->logger->error( 'OpenRouter API error', [ 'error_code' => $error_code ] );
            return TranslationResponse::error( $error_code, $error_message, $this->get_id() );
        }

        // Extract translated text from choices[0].message.content.
        $translated_text = $this->extract_content( $body );

        if ( null === $translated_text ) {
            $this->logger->error( 'OpenRouter parse error: no content found in choices' );
            return TranslationResponse::error(
                'parse_error',
                __( 'Unable to extract translated text from OpenRouter response.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        // Restore placeholders.
        $translated_text = $this->placeholder_preserver->restore( $translated_text );

        $this->logger->debug( 'OpenRouter translation success', [
            'output_length' => strlen( $translated_text ),
        ] );

        return TranslationResponse::success(
            $translated_text,
            $this->get_id(),
            $body->model ?? null,
            [
                'usage' => isset( $body->usage ) ? $this->sanitize_usage( $body->usage ) : null,
            ]
        );
    }

    /**
     * Extract content from the Chat Completions response.
     *
     * @param object $body The decoded response body.
     * @return string|null The extracted text or null.
     */
    private function extract_content( $body ): ?string {
        if ( isset( $body->choices ) && is_array( $body->choices ) && ! empty( $body->choices ) ) {
            $choice = $body->choices[0];

            if ( isset( $choice->message ) && isset( $choice->message->content ) ) {
                $content = $choice->message->content;
                if ( is_string( $content ) && ! empty( $content ) ) {
                    return trim( $content );
                }
            }

            // Fallback: some models return content directly in choice.
            if ( isset( $choice->text ) && is_string( $choice->text ) && ! empty( $choice->text ) ) {
                return trim( $choice->text );
            }
        }

        return null;
    }

    /**
     * Parse an error response from the API.
     *
     * @param array $response The raw response.
     * @param int   $code     The HTTP status code.
     * @return TranslationResponse
     */
    private function parse_error_response( array $response, int $code ): TranslationResponse {
        $body       = json_decode( wp_remote_retrieve_body( $response ), false );
        $error_code = $this->map_http_error_code( $code );
        $message    = $this->extract_error_message( $body, $code );

        // Check for Retry-After header on rate limit.
        if ( $code === 429 ) {
            $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
            if ( $retry_after ) {
                $message .= ' ' . sprintf( __( 'Retry after %s seconds.', 'hollisho-integration-deepseek-for-translatepress' ), intval( $retry_after ) );
            }
        }

        $this->logger->error( 'OpenRouter HTTP error', [
            'http_code' => $code,
            'error_code' => $error_code,
        ] );

        return TranslationResponse::error( $error_code, $message, $this->get_id() );
    }

    /**
     * Map HTTP status code to error code.
     *
     * @param int $code HTTP status code.
     * @return string Error code.
     */
    private function map_http_error_code( int $code ): string {
        $mapping = [
            401 => 'invalid_key',
            402 => 'insufficient_credits',
            403 => 'forbidden',
            429 => 'rate_limit',
            500 => 'server_error',
            502 => 'server_error',
            503 => 'server_error',
            504 => 'server_error',
        ];

        return $mapping[ $code ] ?? 'http_error';
    }

    /**
     * Extract error message from response body.
     *
     * @param object|null $body The decoded body.
     * @param int         $code The HTTP status code.
     * @return string The error message.
     */
    private function extract_error_message( $body, int $code ): string {
        if ( isset( $body->error ) ) {
            if ( is_string( $body->error ) ) {
                return $body->error;
            }
            if ( is_object( $body->error ) && isset( $body->error->message ) ) {
                return $body->error->message;
            }
        }

        $messages = [
            401 => __( 'Invalid OpenRouter API key.', 'hollisho-integration-deepseek-for-translatepress' ),
            402 => __( 'Insufficient OpenRouter credits. Please add credits to your account.', 'hollisho-integration-deepseek-for-translatepress' ),
            403 => __( 'OpenRouter API access forbidden.', 'hollisho-integration-deepseek-for-translatepress' ),
            429 => __( 'OpenRouter API rate limit exceeded. Please try again later.', 'hollisho-integration-deepseek-for-translatepress' ),
            500 => __( 'OpenRouter API server error.', 'hollisho-integration-deepseek-for-translatepress' ),
            502 => __( 'OpenRouter API server error.', 'hollisho-integration-deepseek-for-translatepress' ),
            503 => __( 'OpenRouter API service unavailable.', 'hollisho-integration-deepseek-for-translatepress' ),
            504 => __( 'OpenRouter API gateway timeout.', 'hollisho-integration-deepseek-for-translatepress' ),
        ];

        return $messages[ $code ] ?? sprintf( __( 'OpenRouter API error (HTTP %d).', 'hollisho-integration-deepseek-for-translatepress' ), $code );
    }

    /**
     * Sanitize usage metadata.
     *
     * @param object $usage The usage object.
     * @return array Sanitized usage data.
     */
    private function sanitize_usage( $usage ): array {
        return [
            'input_tokens'  => $usage->prompt_tokens ?? null,
            'output_tokens' => $usage->completion_tokens ?? null,
            'total_tokens'  => $usage->total_tokens ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function perform_key_validation( $api_key ) {
        $this->logger->info( 'OpenRouter key validation started' );

        $model = $this->get_stored_model() ?: self::DEFAULT_MODEL;

        $validation_payload = [
            'model'    => $model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'Return exactly OK.',
                ],
                [
                    'role'    => 'user',
                    'content' => 'Return OK',
                ],
            ],
            'max_tokens' => 10,
        ];

        $validation_headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer'  => home_url(),
        ];

        $response = $this->http_client->post(
            $this->get_api_url(),
            $validation_payload,
            $validation_headers,
            15
        );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'OpenRouter key validation failed', [
                'error' => $response->get_error_message(),
            ] );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 ) {
            $this->logger->error( 'OpenRouter key validation HTTP error', [ 'status' => $code ] );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), false );

        if ( empty( $body ) ) {
            $this->logger->error( 'OpenRouter key validation empty response' );
            return false;
        }

        $content = $this->extract_content( $body );

        if ( null === $content ) {
            $this->logger->error( 'OpenRouter key validation: no content' );
            return false;
        }

        $is_valid = ( stripos( $content, 'OK' ) !== false );

        $this->logger->info( 'OpenRouter key validation result', [
            'valid' => $is_valid,
        ] );

        return $is_valid;
    }

    /**
     * Send a raw translation request (for batch compatibility).
     *
     * @param string $source_language Source language code.
     * @param string $target_language Target language code.
     * @param array  $strings_array    Array of strings to translate.
     * @return array|\WP_Error The raw wp_remote_* response.
     */
    public function send_raw_request( string $source_language, string $target_language, array $strings_array ) {
        $prompt_builder = new PromptBuilder();
        $prompt         = $prompt_builder->build_batch( $strings_array, $source_language, $target_language );

        $model = $this->get_model();

        $payload = [
            'model'       => $model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => $this->build_instructions(
                        new TranslationRequest( '', $source_language, $target_language )
                    ),
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens'  => $this->get_max_output_tokens(),
            'temperature' => 0.3,
        ];

        $headers = $this->build_headers();

        return $this->http_client->post( $this->get_api_url(), $payload, $headers );
    }
}
