<?php
/**
 * DeepSeek translation engine — adapted to use new provider infrastructure.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\TranslationEngines
 */

namespace hollisho\translatepress\translate\deepseek\inc\TranslationEngines;

use hollisho\translatepress\translate\deepseek\inc\Providers\DeepSeekProvider;
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
                    $translated_content = $translation_response->choices[0]->message->content ?? '';
                    $translations       = \hollisho\translatepress\translate\deepseek\inc\Helpers\DeepSeekApiHelper::parseTranslatedItems( $translated_content, count( $new_strings_chunk ) );
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
