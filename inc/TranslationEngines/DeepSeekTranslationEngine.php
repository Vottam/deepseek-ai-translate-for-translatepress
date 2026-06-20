<?php
/**
 * DeepSeek translation engine — adapted to use new provider infrastructure.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\TranslationEngines
 */

namespace hollisho\translatepress\translate\deepseek\inc\TranslationEngines;

use hollisho\translatepress\translate\deepseek\inc\Providers\DeepSeekProvider;
use hollisho\translatepress\translate\deepseek\inc\Translation\BatchIntegrityValidator;
use hollisho\translatepress\translate\deepseek\inc\Translation\ChunkingStrategy;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use TRP_Machine_Translator;
use WP_Error;

/**
 * Class DeepSeekTranslationEngine
 *
 * DeepSeek machine translation engine.
 * Now uses DeepSeekProvider internally while maintaining full backward
 * compatibility with the TRP_Machine_Translator interface.
 */
class DeepSeekTranslationEngine extends TRP_Machine_Translator {

    const ENGINE_KEY = 'deepseek_translate';

    const FIELD_API_KEY='***';

    /** @var DeepSeekProvider|null */
    private $provider = null;

    /**
     * Get the provider instance (lazy-loaded).
     *
     * @return DeepSeekProvider|null
     */
    protected function get_provider(): ?DeepSeekProvider {
        if ( null === $this->provider ) {
            $settings = $this->settings ?? [];
            if ( ! isset( $settings['trp_machine_translation_settings'] ) ) {
                $settings['trp_machine_translation_settings'] = [];
            }
            $this->provider = new DeepSeekProvider( $settings );
        }
        return $this->provider;
    }

    /**
     * Send request to DeepSeek API.
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
            return new WP_Error( 'provider_error', __( 'DeepSeek provider could not be initialized.', 'hollisho-integration-deepseek-for-translatepress' ) );
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

        $source_language = apply_filters( 'trp_deepseek_source_language', $this->machine_translation_codes[ $source_language_code ] ?? $source_language_code, $source_language_code, $target_language_code );
        $target_language = apply_filters( 'trp_deepseek_target_language', $this->machine_translation_codes[ $target_language_code ] ?? $target_language_code, $source_language_code, $target_language_code );

        // Use ChunkingStrategy for safe chunk sizes (max 25 strings, 50k chars).
        $validator = new BatchIntegrityValidator();
        $chunks    = ChunkingStrategy::split( $new_strings, BatchIntegrityValidator::MAX_BATCH_STRINGS, BatchIntegrityValidator::MAX_BATCH_CHARS );

        foreach ( $chunks as $chunk ) {
            $chunk_result = $this->translate_chunk_with_retry( $chunk, $source_language, $target_language );

            if ( is_wp_error( $chunk_result ) ) {
                // Integrity check failed — do NOT save partial translations.
                $this->machine_translator_logger->log( [
                    'error'       => 'batch_integrity_fail',
                    'error_code'  => $chunk_result->get_error_code(),
                    'chunk_size'  => count( $chunk ),
                    'lang_source' => $source_language,
                    'lang_target' => $target_language,
                ] );

                // Return WP_Error to signal failure to TranslatePress.
                return $chunk_result;
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
        $validator     = new BatchIntegrityValidator();
        $chunk_size  = count( $chunk );
        $retry_sizes = ChunkingStrategy::get_retry_strategy( $chunk_size );

        // Try with original chunk size first.
        $response = $this->send_request( $source_language, $target_language, $chunk );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! ( is_array( $response ) && isset( $response['response'] ) &&
            isset( $response['response']['code'] ) && $response['response']['code'] == 200 ) ) {
            return new WP_Error(
                'deepseek_http_error',
                sprintf( 'DeepSeek returned non-200 status for chunk of %d items.', $chunk_size )
            );
        }

        $translation_response = json_decode( $response['body'] );

        if ( empty( $translation_response->error ) ) {
            $translated_content = $translation_response->choices[0]->message->content ?? '';
            $parsed_items       = \hollisho\translatepress\translate\deepseek\inc\Helpers\DeepSeekApiHelper::parseTranslatedItems( $translated_content, count( $chunk ) );

            // Map parsed translations back to original chunk keys for integrity validation.
            $chunk_keys   = array_keys( $chunk );
            $translations = [];
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

                    $sub_chunks     = array_chunk( $chunk, $new_size, true );
                    $all_valid      = true;
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

                // All retries failed — return error, do NOT save partial translations.
                return $integrity;
            }

            // Validate source leak — detect untranslated segments.
            $source_leak = $validator->validate_source_leak( $chunk, $translations, $source_language, $target_language );
            if ( is_wp_error( $source_leak ) ) {
                $this->machine_translator_logger->log( [
                    'error'       => 'source_leak_detected',
                    'error_code'  => $source_leak->get_error_code(),
                    'chunk_size'  => count( $chunk ),
                    'lang_source' => $source_language,
                    'lang_target' => $target_language,
                ] );

                foreach ( $retry_sizes as $new_size ) {
                    if ( $new_size >= $chunk_size ) {
                        continue;
                    }

                    $sub_chunks     = array_chunk( $chunk, $new_size, true );
                    $all_valid      = true;
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

        // API error.
        return new WP_Error(
            'deepseek_api_error',
            'DeepSeek API error: ' . ( $translation_response->error->message ?? 'unknown' )
        );
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
                $return_message = '请输入您的 API 密钥。格式请参考下面说明';
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
        return 'https://api.deepseek.com/chat/completions';
    }
}
