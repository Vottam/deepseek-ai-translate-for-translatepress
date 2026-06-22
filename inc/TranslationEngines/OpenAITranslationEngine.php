<?php
/**
 * OpenAI translation engine for TranslatePress.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\TranslationEngines
 */

namespace hollisho\translatepress\translate\deepseek\inc\TranslationEngines;

use hollisho\translatepress\translate\deepseek\inc\Providers\OpenAIProvider;
use hollisho\translatepress\translate\deepseek\inc\Translation\BatchIntegrityValidator;
use hollisho\translatepress\translate\deepseek\inc\Translation\ChunkingStrategy;
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

        // Use ChunkingStrategy for safe chunk sizes (max 25 strings, 50k chars).
        $validator = new BatchIntegrityValidator();
        $chunks    = ChunkingStrategy::split( $new_strings, BatchIntegrityValidator::MAX_BATCH_STRINGS, BatchIntegrityValidator::MAX_BATCH_CHARS );

        foreach ( $chunks as $chunk ) {
            $chunk_result = $this->translate_chunk_with_retry( $chunk, $source_language, $target_language );

            if ( is_wp_error( $chunk_result ) ) {
                // Integrity check failed — log the error.
                // Do NOT return WP_Error to TranslatePress; it cannot handle it.
                // Return accumulated translations so far (may be empty).
                $this->machine_translator_logger->log( [
                    'error'       => 'batch_integrity_fail',
                    'error_code'  => $chunk_result->get_error_code(),
                    'chunk_size'  => count( $chunk ),
                    'lang_source' => $source_language,
                    'lang_target' => $target_language,
                ] );

                // Return what we have so far — never WP_Error to TranslatePress.
                return $translated_strings;
            }

            $translated_strings = array_merge( $translated_strings, $chunk_result );
        }

        return $translated_strings;
    }

    /**
     * Translate a single chunk with retry and integrity validation.
     *
     * @param array  $chunk           Strings to translate.
     * @param string $source_language Source language code.
     * @param string $target_language Target language code.
     *
     * @return array|WP_Error Translated strings or error.
     */
    private function translate_chunk_with_retry( array $chunk, string $source_language, string $target_language ) {
        $validator      = new BatchIntegrityValidator();
        $chunk_size   = count( $chunk );
        $retry_sizes  = ChunkingStrategy::get_retry_strategy( $chunk_size );

        // Try with original chunk size first.
        $response = $this->send_request( $source_language, $target_language, $chunk );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! ( is_array( $response ) && isset( $response['response'] ) &&
            isset( $response['response']['code'] ) && $response['response']['code'] == 200 ) ) {
            return new WP_Error(
                'openai_http_error',
                sprintf( 'OpenAI returned non-200 status for chunk of %d items.', $chunk_size )
            );
        }

        $translation_response = json_decode( $response['body'] );

        if ( ! empty( $translation_response->error ) ) {
            return new WP_Error(
                'openai_api_error',
                'OpenAI API error: ' . ( $translation_response->error->message ?? 'unknown' )
            );
        }

        $translated_content = $this->extract_translated_content( $translation_response );
        $parsed_items       = $this->parse_translated_items( $translated_content, count( $chunk ) );

        // Map parsed translations back to original chunk keys for integrity validation.
        $chunk_keys    = array_keys( $chunk );
        $translations  = [];
        foreach ( $chunk_keys as $index => $key ) {
            $translations[ $key ] = $parsed_items[ $index ] ?? '';
        }

        // Validate integrity.
        $integrity = $validator->validate( $chunk, $translations );
        if ( is_wp_error( $integrity ) ) {
            // Try smaller chunks.
            foreach ( $retry_sizes as $new_size ) {
                if ( $new_size >= $chunk_size ) {
                    continue;
                }

                $sub_chunks = array_chunk( $chunk, $new_size, true );
                $all_valid  = true;
                $all_translated = [];

                foreach ( $sub_chunks as $sub_chunk ) {
                    $sub_result = $this->translate_chunk_with_retry( $sub_chunk, $source_language, $target_language );
                    if ( is_wp_error( $sub_result ) ) {
                        $all_valid = false;
                        break;
                    }
                    $all_translated = array_merge( $all_translated, $sub_result );
                }

                if ( $all_valid ) {
                    return $all_translated;
                }

                $chunk_size = $new_size; // Update for next iteration.
            }

            // All retries failed — return error, do NOT save partial translations.
            return $integrity;
        }

        // Validate source leak — detect untranslated segments.
        $source_leak = $validator->validate_source_leak( $chunk, $translations, $source_language, $target_language );
        if ( is_wp_error( $source_leak ) ) {
            // Source leak detected — do NOT accept this translation.
            $this->machine_translator_logger->log( [
                'error'       => 'source_leak_detected',
                'error_code'  => $source_leak->get_error_code(),
                'chunk_size'  => count( $chunk ),
                'lang_source' => $source_language,
                'lang_target' => $target_language,
            ] );

            // Try smaller chunks.
            foreach ( $retry_sizes as $new_size ) {
                if ( $new_size >= $chunk_size ) {
                    continue;
                }

                $sub_chunks = array_chunk( $chunk, $new_size, true );
                $all_valid  = true;
                $all_translated = [];

                foreach ( $sub_chunks as $sub_chunk ) {
                    $sub_result = $this->translate_chunk_with_retry( $sub_chunk, $source_language, $target_language );
                    if ( is_wp_error( $sub_result ) ) {
                        $all_valid = false;
                        break;
                    }
                    $all_translated = array_merge( $all_translated, $sub_result );
                }

                if ( $all_valid ) {
                    return $all_translated;
                }

                $chunk_size = $new_size;
            }

            return $source_leak;
        }

        // Validate near-identical long segments.
        $near_identical = $validator->validate_no_near_identical_long( $chunk, $translations );
        if ( is_wp_error( $near_identical ) ) {
            $this->machine_translator_logger->log( [
                'error'       => 'near_identical_long',
                'error_code'  => $near_identical->get_error_code(),
                'chunk_size'  => count( $chunk ),
                'lang_source' => $source_language,
                'lang_target' => $target_language,
            ] );
            return $near_identical;
        }

        // Validate mixed-language for non-Latin targets.
        $mixed_lang = $validator->validate_no_mixed_language( $translations, $target_language );
        if ( is_wp_error( $mixed_lang ) ) {
            $this->machine_translator_logger->log( [
                'error'       => 'mixed_language',
                'error_code'  => $mixed_lang->get_error_code(),
                'chunk_size'  => count( $chunk ),
                'lang_source' => $source_language,
                'lang_target' => $target_language,
            ] );
            return $mixed_lang;
        }

        // Integrity passed — count towards quota and return.
        $this->machine_translator_logger->count_towards_quota( $chunk );

        return $translations;
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

        // Try '---' delimiter (new batch format).
        if ( strpos( $content, '---' ) !== false ) {
            $parts = preg_split( '/\n?---\n?/', $content, -1, PREG_SPLIT_NO_EMPTY );
            foreach ( $parts as $part ) {
                $item = trim( $part );
                // Defensive: remove residual numbering like "1. " or "2. " etc.
                $item = preg_replace( '/^\s*\d+\.\s+/', '', $item );
                if ( ! empty( $item ) ) {
                    $items[] = $item;
                }
            }
            return $items;
        }

        // Split by newlines if multiple items.
        if ( $expected_count > 1 ) {
            $lines = preg_split( '/\n/', $content, -1, PREG_SPLIT_NO_EMPTY );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                // Defensive: remove residual numbering like "1. " or "2. " etc.
                $line = preg_replace( '/^\s*\d+\.\s+/', '', $line );
                if ( ! empty( $line ) ) {
                    $items[] = $line;
                }
            }
        }

        if ( empty( $items ) && ! empty( $content ) ) {
            $content = trim( $content );
            // Defensive: remove residual numbering like "1. " for single items.
            $content = preg_replace( '/^\s*\d+\.\s+/', '', $content );
            if ( ! empty( $content ) ) {
                $items[] = $content;
            }
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
