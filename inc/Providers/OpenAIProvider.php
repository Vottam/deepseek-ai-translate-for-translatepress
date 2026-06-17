<?php
/**
 * OpenAI translation provider using Responses API.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Providers
 */

namespace hollisho\translatepress\translate\deepseek\inc\Providers;

use hollisho\translatepress\translate\deepseek\inc\Translation\PlaceholderPreserver;
use hollisho\translatepress\translate\deepseek\inc\Translation\PromptBuilder;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationResponse;

/**
 * Class OpenAIProvider
 *
 * OpenAI translation provider using the Responses API (POST /v1/responses).
 *
 * @see https://developers.openai.com/api/docs/guides/migrate-to-responses
 */
class OpenAIProvider extends AbstractProvider {

    const ENGINE_KEY = 'openai';

    const FIELD_API_KEY='***';

    const DEFAULT_API_URL = 'https://api.openai.com/v1/responses';

    const DEFAULT_MODEL = 'gpt-5.4-mini';

    const VALIDATION_MODEL = 'gpt-5.4-nano';

    const DEFAULT_MAX_OUTPUT_TOKENS = 4096;

    const DEFAULT_TIMEOUT = 60;

    /**
     * Available models.
     *
     * @var array
     */
    const AVAILABLE_MODELS = [
        'gpt-5.4-mini'  => 'GPT-5.4 Mini',
        'gpt-5.4-nano'  => 'GPT-5.4 Nano',
        'gpt-4o-mini'   => 'GPT-4o Mini',
    ];

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
        return __( 'OpenAI', 'hollisho-integration-deepseek-for-translatepress' );
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
     * @return string
     */
    protected function get_model(): string {
        return self::DEFAULT_MODEL;
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
        $model       = $request->get_model() ?? $this->get_model();

        // Preserve placeholders before building prompt.
        $preserved_text = $this->placeholder_preserver->preserve( $source_text );

        // Build the translation instructions.
        $instructions = $this->build_instructions( $request );

        $payload = [
            'model'               => $model,
            'instructions'        => $instructions,
            'input'               => $preserved_text,
            'max_output_tokens'   => $this->get_max_output_tokens(),
        ];

        $this->logger->debug( 'OpenAI payload built', [
            'model'          => $model,
            'input_length'   => strlen( $preserved_text ),
            'has_placeholders' => ! empty( $this->placeholder_preserver->get_placeholders() ),
        ] );

        return $payload;
    }

    /**
     * Build the instructions for the Responses API.
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
        $instructions .= "Preserve all HTML tags, shortcodes, placeholders (%s, %d, {name}, {{var}}), and special characters exactly as they are. ";
        $instructions .= "Do NOT translate placeholders or variable names. ";
        $instructions .= "Return ONLY the translated text, without any explanations or notes.";

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
            return $this->parse_error_response( $response, $code );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), false );

        if ( empty( $body ) ) {
            $this->logger->error( 'OpenAI empty response body', [ 'status' => $code ] );
            return TranslationResponse::error(
                'empty_response',
                __( 'Empty response from OpenAI API.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        // Check for API-level errors.
        if ( isset( $body->error ) && ! empty( $body->error ) ) {
            $error_message = is_string( $body->error ) ? $body->error : ( $body->error->message ?? 'Unknown OpenAI error' );
            $error_code    = is_string( $body->error ) ? 'api_error' : ( $body->error->code ?? 'api_error' );
            $this->logger->error( 'OpenAI API error', [ 'error_code' => $error_code ] );
            return TranslationResponse::error( $error_code, $error_message, $this->get_id() );
        }

        // Extract translated text.
        $translated_text = $this->extract_output_text( $body );

        if ( null === $translated_text ) {
            $this->logger->error( 'OpenAI parse error: no output_text found' );
            return TranslationResponse::error(
                'parse_error',
                __( 'Unable to extract translated text from OpenAI response.', 'hollisho-integration-deepseek-for-translatepress' ),
                $this->get_id()
            );
        }

        // Restore placeholders.
        $translated_text = $this->placeholder_preserver->restore( $translated_text );

        $this->logger->debug( 'OpenAI translation success', [
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
     * Extract output text from the Responses API response.
     *
     * Prefers output_text, falls back to iterating output[].
     *
     * @param object $body The decoded response body.
     * @return string|null The extracted text or null.
     */
    private function extract_output_text( $body ): ?string {
        // Preferred: output_text helper.
        if ( isset( $body->output_text ) && is_string( $body->output_text ) && ! empty( $body->output_text ) ) {
            return trim( $body->output_text );
        }

        // Fallback: iterate output[] array.
        if ( isset( $body->output ) && is_array( $body->output ) ) {
            $texts = [];

            foreach ( $body->output as $item ) {
                if ( isset( $item->type ) && $item->type === 'message' && isset( $item->content ) ) {
                    if ( is_array( $item->content ) ) {
                        foreach ( $item->content as $content_item ) {
                            if ( isset( $content_item->type ) && $content_item->type === 'output_text' && isset( $content_item->text ) ) {
                                $texts[] = $content_item->text;
                            }
                        }
                    } elseif ( is_string( $item->content ) ) {
                        $texts[] = $item->content;
                    }
                }
            }

            if ( ! empty( $texts ) ) {
                return trim( implode( "\n", $texts ) );
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

        $this->logger->error( 'OpenAI HTTP error', [
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
            401 => __( 'Invalid OpenAI API key.', 'hollisho-integration-deepseek-for-translatepress' ),
            403 => __( 'OpenAI API access forbidden.', 'hollisho-integration-deepseek-for-translatepress' ),
            429 => __( 'OpenAI API rate limit exceeded. Please try again later.', 'hollisho-integration-deepseek-for-translatepress' ),
            500 => __( 'OpenAI API server error.', 'hollisho-integration-deepseek-for-translatepress' ),
            502 => __( 'OpenAI API server error.', 'hollisho-integration-deepseek-for-translatepress' ),
            503 => __( 'OpenAI API service unavailable.', 'hollisho-integration-deepseek-for-translatepress' ),
            504 => __( 'OpenAI API gateway timeout.', 'hollisho-integration-deepseek-for-translatepress' ),
        ];

        return $messages[ $code ] ?? sprintf( __( 'OpenAI API error (HTTP %d).', 'hollisho-integration-deepseek-for-translatepress' ), $code );
    }

    /**
     * Sanitize usage metadata.
     *
     * @param object $usage The usage object.
     * @return array Sanitized usage data.
     */
    private function sanitize_usage( $usage ): array {
        return [
            'input_tokens'  => $usage->input_tokens ?? null,
            'output_tokens' => $usage->output_tokens ?? null,
            'total_tokens'  => $usage->total_tokens ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function perform_key_validation( $api_key ) {
        $this->logger->info( 'OpenAI key validation started' );

        $validation_payload = [
            'model'        => self::VALIDATION_MODEL,
            'instructions' => 'Return exactly OK.',
            'input'        => 'Return OK',
        ];

        $validation_headers = [
            'Authorization' => 'Bearer ' . $api_key,
        ];

        $response = $this->http_client->post(
            $this->get_api_url(),
            $validation_payload,
            $validation_headers,
            15
        );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'OpenAI key validation failed', [
                'error' => $response->get_error_message(),
            ] );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 ) {
            $this->logger->error( 'OpenAI key validation HTTP error', [ 'status' => $code ] );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), false );

        if ( empty( $body ) ) {
            $this->logger->error( 'OpenAI key validation empty response' );
            return false;
        }

        $output_text = $this->extract_output_text( $body );

        if ( null === $output_text ) {
            $this->logger->error( 'OpenAI key validation: no output_text' );
            return false;
        }

        $is_valid = ( stripos( $output_text, 'OK' ) !== false );

        $this->logger->info( 'OpenAI key validation result', [
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

        $payload = [
            'model'             => $this->get_model(),
            'instructions'      => $this->build_instructions(
                new TranslationRequest( '', $source_language, $target_language )
            ),
            'input'             => $prompt,
            'max_output_tokens' => $this->get_max_output_tokens(),
        ];

        $headers = $this->build_headers();

        return $this->http_client->post( $this->get_api_url(), $payload, $headers );
    }
}
