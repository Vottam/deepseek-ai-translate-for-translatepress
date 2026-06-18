<?php
/**
 * OpenAI translation engine for TranslatePress.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\TranslationEngines
 */

namespace hollisho\translatepress\translate\deepseek\inc\TranslationEngines;

use hollisho\translatepress\translate\deepseek\inc\Providers\OpenAIProvider;
use TRP_Machine_Translator;
use WP_Error;

/**
 * Class OpenAITranslationEngine
 *
 * OpenAI machine translation engine using Responses API.
 * Registered as a separate option in the TranslatePress dropdown.
 */
class OpenAITranslationEngine extends TRP_Machine_Translator {

    const ENGINE_KEY  = 'openai';
    const FIELD_API_KEY = 'openai-api-key';

    /** @var OpenAIProvider|null */
    private $provider = null;

    /**
     * Get the provider instance (lazy-loaded).
     *
     * @return OpenAIProvider|null
     */
    protected function get_provider(): ?OpenAIProvider {
        if ( null === $this->provider ) {
            $settings = $this->settings ?? [];
            if ( ! isset( $settings['trp_machine_translation_settings'] ) ) {
                $settings['trp_machine_translation_settings'] = [];
            }
            $this->provider = new OpenAIProvider( $settings );
        }
        return $this->provider;
    }

    /**
     * Send request to OpenAI API.
     *
     * @param string $source_language Source language code.
     * @param string $language_code   Target language code.
     * @param array  $strings_array   Array of strings to translate.
     *
     * @return array|WP_Error Response.
     */
    public function send_request( $source_language, $language_code, $strings_array ) {
        $provider = $this->get_provider();

        if ( ! $provider ) {
            return new WP_Error(
                'provider_error',
                __( 'OpenAI provider could not be initialized.', 'hollisho-integration-deepseek-for-translatepress' )
            );
        }

        return $provider->send_raw_request( $source_language, $language_code, $strings_array );
    }

    /**
     * Translate an array of strings.
     *
     * @param array  $new_strings         Strings to translate.
     * @param string $target_language_code Target language code.
     * @param string $source_language_code Source language code.
     *
     * @return array Translated strings.
     */
    public function translate_array( $new_strings, $target_language_code, $source_language_code = null ) {
        if ( $source_language_code == null ) {
            $source_language_code = $this->settings['default-language'] ?? '';
        }

        if ( empty( $new_strings ) || ! $this->verify_request_parameters( $target_language_code, $source_language_code ) ) {
            return [];
        }

        $translated_strings = [];

        $source_language = $this->machine_translation_codes[ $source_language_code ] ?? $source_language_code;
        $target_language = $this->machine_translation_codes[ $target_language_code ] ?? $target_language_code;

        $new_strings_chunks = array_chunk( $new_strings, 64, true );

        foreach ( $new_strings_chunks as $new_strings_chunk ) {
            $response = $this->send_request( $source_language, $target_language, $new_strings_chunk );

            // Log if enabled.
            $this->machine_translator_logger->log( [
                'strings'      => serialize( $new_strings_chunk ),
                'response'     => serialize( $response ),
                'lang_source'  => $source_language,
                'lang_target'  => $target_language,
            ] );

            if ( is_array( $response ) && ! is_wp_error( $response ) && isset( $response['response'] ) &&
                isset( $response['response']['code'] ) && $response['response']['code'] == 200 ) {

                $this->machine_translator_logger->count_towards_quota( $new_strings_chunk );

                $translation_response = json_decode( $response['body'] );

                if ( empty( $translation_response->error ) ) {
                    $translated_content = $this->extract_translated_content( $translation_response );
                    $translations       = $this->parse_translated_items( $translated_content, count( $new_strings_chunk ) );
                    $i = 0;

                    foreach ( $new_strings_chunk as $key => $old_string ) {
                        if ( isset( $translations[ $i ] ) && ! empty( $translations[ $i ] ) ) {
                            $translated_strings[ $key ] = $translations[ $i ];
                        } else {
                            $translated_strings[ $key ] = $old_string;
                        }
                        $i++;
                    }
                }

                if ( $this->machine_translator_logger->quota_exceeded() ) {
                    break;
                }
            }
        }

        return $translated_strings;
    }

    /**
     * Extract translated content from OpenAI response.
     *
     * @param object $response The decoded response body.
     * @return string The translated content.
     */
    private function extract_translated_content( $response ): string {
        // OpenAI Responses API: output_text helper.
        if ( isset( $response->output_text ) && is_string( $response->output_text ) ) {
            return $response->output_text;
        }

        // Fallback: iterate output[].
        if ( isset( $response->output ) && is_array( $response->output ) ) {
            $texts = [];
            foreach ( $response->output as $item ) {
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
                return implode( "\n", $texts );
            }
        }

        return '';
    }

    /**
     * Parse translated items from content.
     *
     * @param string $content        The translated content.
     * @param int    $expected_count Expected number of items.
     * @return array Parsed translations.
     */
    private function parse_translated_items( string $content, int $expected_count ): array {
        $items = [];

        // Try JSON array first.
        $decoded = json_decode( $content, true );
        if ( is_array( $decoded ) ) {
            return array_values( $decoded );
        }

        // Split by newlines if multiple items.
        if ( $expected_count > 1 ) {
            $lines = preg_split( '/\n/', $content, -1, PREG_SPLIT_NO_EMPTY );
            foreach ( $lines as $line ) {
                $items[] = trim( $line );
            }
        }

        if ( empty( $items ) && ! empty( $content ) ) {
            $items[] = $content;
        }

        return $items;
    }

    /**
     * Test the API connection.
     *
     * @return array|WP_Error
     */
    public function test_request() {
        return $this->send_request( 'en', 'es', [ 'Where are you from ?', 'I Love you !' ] );
    }

    /**
     * Check API key validity.
     *
     * @return array
     */
    public function check_api_key_validity() {
        $translation_engine = $this->settings['trp_machine_translation_settings']['translation-engine'] ?? '';
        $api_key            = $this->get_api_key();
        $is_error           = false;
        $return_message     = '';

        if ( self::ENGINE_KEY === $translation_engine && ( $this->settings['trp_machine_translation_settings']['machine-translation'] ?? '' ) === 'yes' ) {
            if ( isset( $this->correct_api_key ) && $this->correct_api_key != null ) {
                return $this->correct_api_key;
            }

            if ( empty( $api_key ) ) {
                $is_error       = true;
                $return_message = __( 'Please enter your OpenAI API key.', 'hollisho-integration-deepseek-for-translatepress' );
            }

            $this->correct_api_key = [
                'message' => $return_message,
                'error'   => $is_error,
            ];
        }

        return [
            'message' => $return_message,
            'error'   => $is_error,
        ];
    }

    /**
     * Get the API key from settings.
     *
     * @return string|false
     */
    public function get_api_key() {
        return isset( $this->settings['trp_machine_translation_settings'], $this->settings['trp_machine_translation_settings'][ self::FIELD_API_KEY ] )
            ? $this->settings['trp_machine_translation_settings'][ self::FIELD_API_KEY ] : false;
    }

    /**
     * Get the API URL.
     *
     * @return string
     */
    public function get_api_url(): string {
        return 'https://api.openai.com/v1/responses';
    }
}
