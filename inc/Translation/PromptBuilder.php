<?php
/**
 * Builder for translation prompts.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

use hollisho\translatepress\translate\deepseek\inc\Helpers\DeepSeekApiHelper;

/**
 * Class PromptBuilder
 *
 * Builds translation prompts for different providers.
 */
class PromptBuilder {

    /**
     * Build a single-text translation prompt.
     *
     * @param string $text            The text to translate.
     * @param string $source_language Source language code.
     * @param string $target_language Target language code.
     * @return string The prompt.
     */
    public function build( string $text, string $source_language, string $target_language ): string {
        $source_name = $this->get_language_name( $source_language );
        $target_name = $this->get_language_name( $target_language );

        if ( $source_language === 'auto' || empty( $source_name ) ) {
            return sprintf(
                'Translate the following content to %s, maintaining a professional tone. Return only the translated text:%s%s',
                $target_name,
                "\n\n",
                $text
            );
        }

        return sprintf(
            'Translate the following %s content to %s, maintaining a professional tone. Return only the translated text:%s%s',
            $source_name,
            $target_name,
            "\n\n",
            $text
        );
    }

    /**
     * Build a batch translation prompt (multiple items).
     *
     * @param array  $texts           Array of texts to translate.
     * @param string $source_language Source language code.
     * @param string $target_language Target language code.
     * @return string The batch prompt.
     */
    public function build_batch( array $texts, string $source_language, string $target_language ): string {
        return DeepSeekApiHelper::convert( $texts, $source_language, $target_language );
    }

    /**
     * Get the human-readable language name.
     *
     * @param string $language_code The language code.
     * @return string The language name or the code itself.
     */
    private function get_language_name( string $language_code ): string {
        $languages = DeepSeekApiHelper::supportedLanguages;

        if ( isset( $languages[ $language_code ] ) ) {
            return $languages[ $language_code ];
        }

        // Try base language (e.g., 'en_US' -> 'en').
        $base = explode( '_', $language_code )[0];
        if ( isset( $languages[ $base ] ) ) {
            return $languages[ $base ];
        }

        return $language_code;
    }
}
