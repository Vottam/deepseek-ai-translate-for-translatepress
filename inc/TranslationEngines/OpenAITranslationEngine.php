<?php
namespace hollisho\translatepress\translate\deepseek\inc\TranslationEngines;

use TRP_Machine_Translator;
use WP_Error;

/**
 * OpenAI machine translation engine (minimal, Responses API)
 * Extends TRP_Machine_Translator like DeepSeekTranslationEngine.
 * Uses /v1/responses endpoint with gpt-5.4-mini.
 */
class OpenAITranslationEngine extends TRP_Machine_Translator
{
    const ENGINE_KEY  = 'openai_translate';
    const FIELD_API_KEY = 'openai-api-key';
    const FIELD_MODEL   = 'openai-model';

    /**
     * Send translation request to OpenAI Responses API.
     *
     * @param string $source_language Source language name
     * @param string $target_language Target language name
     * @param array  $strings_array   Strings to translate (keyed)
     *
     * @return array|WP_Error HTTP response
     */
    public function send_request($source_language, $target_language, $strings_array)
    {
        $model = isset($this->settings['trp_machine_translation_settings'][self::FIELD_MODEL])
            ? $this->settings['trp_machine_translation_settings'][self::FIELD_MODEL]
            : 'gpt-5.4-mini';

        $prompt = $this->build_prompt($strings_array, $source_language, $target_language);

        $data = [
            'model'  => $model,
            'input'  => $prompt,
        ];

        return wp_remote_post('https://api.openai.com/v1/responses', [
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($data),
        ]);
    }

    /**
     * Build translation prompt.
     */
    private function build_prompt($strings_array, $source_language, $target_language)
    {
        $count = count($strings_array);

        if ($count === 1) {
            $text = array_values($strings_array)[0];
            return "Translate the following {$source_language} text to {$target_language}. Return ONLY the translation, preserving HTML tags and placeholders like {name}, %s, {{var}}. No explanations.\n\n{$text}";
        }

        // Batch: numbered format
        $counter = 0;
        $items = implode("\n", array_map(function ($text) use (&$counter) {
            return (++$counter) . ". " . $text;
        }, $strings_array));

        return "Translate each numbered line from {$source_language} to {$target_language}. " .
               "Return ONLY the translations, preserving the numbering and HTML tags. " .
               "No explanations, no extra text.\n\n{$items}";
    }

    /**
     * Parse response from OpenAI Responses API.
     *
     * @param string $response_body Raw JSON body
     * @param int    $expected_count Expected number of translations
     *
     * @return array Parsed translations (indexed)
     */
    private function parse_response($response_body, $expected_count)
    {
        $body = json_decode($response_body);

        if (empty($body)) {
            return [];
        }

        // Extract text from Responses API
        $text = '';
        if (isset($body->output_text)) {
            $text = is_array($body->output_text) ? implode('', $body->output_text) : $body->output_text;
        } elseif (isset($body->output) && is_array($body->output)) {
            foreach ($body->output as $item) {
                if (isset($item->content) && is_array($item->content)) {
                    foreach ($item->content as $c) {
                        if (isset($c->text)) {
                            $text .= $c->text;
                        }
                    }
                }
            }
        }

        if (empty(trim($text))) {
            return [];
        }

        $text = trim($text);

        if ($expected_count === 1) {
            return [0 => $text];
        }

        // Parse numbered response
        $translations = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)\.\s*(.+?)\s*$/', $line, $matches)) {
                $index = (int)$matches[1] - 1;
                $translations[$index] = $matches[2];
            }
        }
        ksort($translations);

        return array_values($translations);
    }

    /**
     * Translate array of strings.
     * Same contract as DeepSeek: return keyed array, omit failed translations.
     */
    public function translate_array($new_strings, $target_language_code, $source_language_code)
    {
        if ($source_language_code == null) {
            $source_language_code = $this->settings['default-language'];
        }

        if (empty($new_strings) || !$this->verify_request_parameters($target_language_code, $source_language_code)) {
            return [];
        }

        $translated_strings = [];

        $source_language = $this->machine_translation_codes[$source_language_code];
        $target_language = $this->machine_translation_codes[$target_language_code];

        // Chunk into batches of 64 (same as DeepSeek)
        $new_strings_chunks = array_chunk($new_strings, 64, true);

        foreach ($new_strings_chunks as $new_strings_chunk) {
            $response = $this->send_request($source_language, $target_language, $new_strings_chunk);

            $this->machine_translator_logger->log([
                'strings'     => serialize($new_strings_chunk),
                'response'    => serialize($response),
                'lang_source' => $source_language,
                'lang_target' => $target_language,
            ]);

            if (is_array($response) && !is_wp_error($response) &&
                isset($response['response']) && isset($response['response']['code']) &&
                $response['response']['code'] == 200) {

                $this->machine_translator_logger->count_towards_quota($new_strings_chunk);

                $translations = $this->parse_response($response['body'], count($new_strings_chunk));
                $i = 0;

                foreach ($new_strings_chunk as $key => $old_string) {
                    if (isset($translations[$i]) && !empty($translations[$i])) {
                        $translated_strings[$key] = $translations[$i];
                    }
                    // If translation failed: do NOT include key.
                    // TranslatePress keeps string as untranslated/pending.
                    // NEVER save the original string as a "valid translation".
                    $i++;
                }

                if ($this->machine_translator_logger->quota_exceeded()) {
                    break;
                }
            }
        }

        return $translated_strings;
    }

    public function test_request()
    {
        return $this->send_request('English', 'Spanish', ['Hello, how are you?']);
    }

    public function check_api_key_validity()
    {
        $translation_engine = $this->settings['trp_machine_translation_settings']['translation-engine'] ?? '';
        $api_key = $this->get_api_key();

        if (self::ENGINE_KEY !== $translation_engine) {
            return ['message' => '', 'error' => false];
        }

        if (isset($this->correct_api_key) && $this->correct_api_key != null) {
            return $this->correct_api_key;
        }

        $is_error = false;
        $return_message = '';

        if (empty($api_key)) {
            $is_error = true;
            $return_message = 'Please enter your OpenAI API key.';
        }

        $this->correct_api_key = [
            'message' => $return_message,
            'error'   => $is_error,
        ];

        return $this->correct_api_key;
    }

    public function get_api_key()
    {
        return isset($this->settings['trp_machine_translation_settings'][self::FIELD_API_KEY])
            ? $this->settings['trp_machine_translation_settings'][self::FIELD_API_KEY]
            : false;
    }
}
