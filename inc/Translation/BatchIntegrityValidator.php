<?php
/**
 * Batch Integrity Validator.
 *
 * Validates that a translation batch response matches the input in count,
 * order, content integrity, and placeholder preservation.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

use hollisho\translatepress\translate\deepseek\inc\Translation\SourceLeakDetector;
use WP_Error;

/**
 * Class BatchIntegrityValidator
 *
 * Validates batch translation results to prevent partial translations
 * from being saved as complete.
 */
class BatchIntegrityValidator {

    /**
     * Minimum ratio of translated length to original length.
     * Below this threshold, the translation is considered suspicious.
     */
    const MIN_LENGTH_RATIO = 0.25;

    /**
     * Maximum input size (chars) for a single batch request.
     */
    const MAX_BATCH_CHARS = 50000;

    /**
     * Maximum number of strings per batch request.
     */
    const MAX_BATCH_STRINGS = 25;

    /**
     * Maximum estimated tokens per request.
     */
    const MAX_BATCH_TOKENS = 32000;

    /**
     * Validate a batch translation result.
     *
     * @param array $input_strings  The original strings (keyed array).
     * @param array $output_strings The translated strings (keyed array).
     *
     * @return true|WP_Error True if valid, WP_Error if integrity check fails.
     */
    public function validate( array $input_strings, array $output_strings ) {
        // 1. Count match.
        $check = $this->validate_count( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 2. Key/ID match.
        $check = $this->validate_keys( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 3. Order preservation.
        $check = $this->validate_order( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 4. No empty items.
        $check = $this->validate_not_empty( $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 5. No item identical to original (unless explicitly allowed).
        $check = $this->validate_not_identical( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 6. No residual delimiters.
        $check = $this->validate_no_residual_delimiters( $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 7. No artificial numbering.
        $check = $this->validate_no_artificial_numbering( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 8. Placeholder preservation.
        $check = $this->validate_placeholders( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // 9. Length ratio check.
        $check = $this->validate_length_ratio( $input_strings, $output_strings );
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        return true;
    }

    /**
     * Validate source leak — detect untranslated segments.
     *
     * Uses SourceLeakDetector to check if translated text retains
     * substantial portions of the source language.
     *
     * @param array  $input_strings  Original strings (keyed).
     * @param array  $output_strings Translated strings (keyed).
     * @param string $source_lang    Source language code.
     * @param string $target_lang    Target language code.
     *
     * @return true|WP_Error True if no leak, WP_Error if source leak detected.
     */
    public function validate_source_leak( array $input_strings, array $output_strings, string $source_lang, string $target_lang ) {
        $detector = new SourceLeakDetector();
        $result   = $detector->detect_batch_leak( $input_strings, $output_strings, $source_lang, $target_lang );

        if ( $result['leak_detected'] ) {
            return new WP_Error(
                'batch_source_leak',
                sprintf(
                    'Source leak detected: %s Leak ratio: %.1f%%. Leaking items: %d/%d.',
                    $result['details'],
                    $result['leak_ratio'] * 100,
                    count( $result['leaking_keys'] ),
                    count( $output_strings )
                )
            );
        }

        return true;
    }

    /**
     * Validate near-identical long segments.
     *
     * For each output string, checks if it is nearly identical to the input
     * for long strings (> 100 chars), which indicates the LLM skipped translation.
     *
     * @param array $input_strings  Original strings (keyed).
     * @param array $output_strings Translated strings (keyed).
     *
     * @return true|WP_Error
     */
    public function validate_no_near_identical_long( array $input_strings, array $output_strings ) {
        foreach ( $output_strings as $key => $translated ) {
            $original = $input_strings[ $key ] ?? '';

            // Only check strings longer than 100 chars.
            if ( strlen( $original ) < 100 ) {
                continue;
            }

            // Calculate similarity.
            similar_text( $original, $translated, $percent );

            if ( $percent > 80 ) {
                return new WP_Error(
                    'batch_near_identical_long',
                    sprintf(
                        'Batch integrity check failed: item "%s" is %.1f%% identical to original (long string).',
                        $key,
                        $percent
                    )
                );
            }
        }

        return true;
    }

    /**
     * Validate mixed-language content in non-Latin targets.
     *
     * For targets like Korean, Japanese, Thai, etc., checks that the
     * translation does not contain large blocks of Latin text.
     *
     * @param array  $output_strings Translated strings (keyed).
     * @param string $target_lang    Target language code.
     *
     * @return true|WP_Error
     */
    public function validate_no_mixed_language( array $output_strings, string $target_lang ) {
        if ( ! SourceLeakDetector::is_non_latin_target( $target_lang ) ) {
            return true;
        }

        foreach ( $output_strings as $key => $translated ) {
            // Count Latin words (3+ consecutive Latin characters).
            preg_match_all( '/[a-zA-Z]{3,}/', $translated, $latin_words );
            $latin_word_count = count( $latin_words[0] );

            // Count total words (approximate).
            $total_words = str_word_count( preg_replace( '/[^\w\s]/', '', $translated ) );

            if ( $total_words < 5 ) {
                continue;
            }

            $latin_ratio = $latin_word_count / max( $total_words, 1 );

            // For non-Latin targets, Latin words should be < 30% (allowing for brand names, etc.).
            if ( $latin_ratio > 0.30 ) {
                return new WP_Error(
                    'batch_mixed_language',
                    sprintf(
                        'Batch integrity check failed: item "%s" contains %.1f%% Latin words in a %s translation.',
                        $key,
                        $latin_ratio * 100,
                        $target_lang
                    )
                );
            }
        }

        return true;
    }

    /**
     * Validate that output count matches input count.
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_count( array $input, array $output ) {
        $input_count  = count( $input );
        $output_count = count( $output );

        if ( $output_count !== $input_count ) {
            return new WP_Error(
                'batch_count_mismatch',
                sprintf(
                    'Batch integrity check failed: input has %d items, output has %d items.',
                    $input_count,
                    $output_count
                )
            );
        }

        return true;
    }

    /**
     * Validate that output keys match input keys.
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_keys( array $input, array $output ) {
        $input_keys  = array_keys( $input );
        $output_keys = array_keys( $output );

        $missing = array_diff( $input_keys, $output_keys );
        $extra   = array_diff( $output_keys, $input_keys );

        if ( ! empty( $missing ) || ! empty( $extra ) ) {
            return new WP_Error(
                'batch_key_mismatch',
                sprintf(
                    'Batch integrity check failed: missing keys (%d), extra keys (%d).',
                    count( $missing ),
                    count( $extra )
                )
            );
        }

        return true;
    }

    /**
     * Validate that the order of keys is preserved.
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_order( array $input, array $output ) {
        $input_keys  = array_keys( $input );
        $output_keys = array_keys( $output );

        if ( $input_keys !== $output_keys ) {
            return new WP_Error(
                'batch_order_mismatch',
                'Batch integrity check failed: output order does not match input order.'
            );
        }

        return true;
    }

    /**
     * Validate that no output item is empty.
     *
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_not_empty( array $output ) {
        foreach ( $output as $key => $value ) {
            if ( empty( trim( $value ) ) ) {
                return new WP_Error(
                    'batch_empty_item',
                    sprintf(
                        'Batch integrity check failed: item "%s" is empty.',
                        $key
                    )
                );
            }
        }

        return true;
    }

    /**
     * Validate that no output is identical to its input.
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_not_identical( array $input, array $output ) {
        foreach ( $output as $key => $translated ) {
            if ( isset( $input[ $key ] ) && $input[ $key ] === $translated ) {
                return new WP_Error(
                    'batch_identical_item',
                    sprintf(
                        'Batch integrity check failed: item "%s" is identical to original (not translated).',
                        $key
                    )
                );
            }
        }

        return true;
    }

    /**
     * Validate that no output contains residual delimiters.
     *
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_no_residual_delimiters( array $output ) {
        foreach ( $output as $key => $value ) {
            // Check for '---' delimiter that should have been split.
            if ( strpos( $value, "\n---\n" ) !== false || strpos( $value, '---' ) === 0 ) {
                return new WP_Error(
                    'batch_residual_delimiter',
                    sprintf(
                        'Batch integrity check failed: item "%s" contains residual delimiter.',
                        $key
                    )
                );
            }
        }

        return true;
    }

    /**
     * Validate that no output has artificial numbering not present in input.
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_no_artificial_numbering( array $input, array $output ) {
        foreach ( $output as $key => $translated ) {
            // Check if output starts with "N. " pattern but input does not.
            if ( preg_match( '/^\s*\d+\.\s+/', $translated ) ) {
                $original = $input[ $key ] ?? '';
                if ( ! preg_match( '/^\s*\d+\.\s+/', $original ) ) {
                    return new WP_Error(
                        'batch_artificial_numbering',
                        sprintf(
                            'Batch integrity check failed: item "%s" has artificial numbering not present in original.',
                            $key
                        )
                    );
                }
            }
        }

        return true;
    }

    /**
     * Validate that placeholders are preserved in translations.
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_placeholders( array $input, array $output ) {
        foreach ( $output as $key => $translated ) {
            $original = $input[ $key ] ?? '';

            // Extract placeholders from both.
            $input_placeholders  = $this->extract_placeholders( $original );
            $output_placeholders = $this->extract_placeholders( $translated );

            // Check that all input placeholders are present in output.
            $missing = array_diff( $input_placeholders, $output_placeholders );

            if ( ! empty( $missing ) ) {
                return new WP_Error(
                    'batch_placeholder_missing',
                    sprintf(
                        'Batch integrity check failed: item "%s" is missing placeholders: %s',
                        $key,
                        implode( ', ', array_slice( $missing, 0, 3 ) )
                    )
                );
            }
        }

        return true;
    }

    /**
     * Extract placeholders from text.
     *
     * Matches: %s, %d, %1$s, {name}, {{var}}, [shortcode], [/shortcode]
     *
     * @param string $text The text.
     * @return array List of placeholders found.
     */
    private function extract_placeholders( string $text ): array {
        $placeholders = [];

        // sprintf placeholders.
        if ( preg_match_all( '/%[0-9]*\$?[sdfeEgGcxXobB%]/', $text, $matches ) ) {
            foreach ( $matches[0] as $m ) {
                if ( $m !== '%%' ) { // Skip literal %.
                    $placeholders[] = $m;
                }
            }
        }

        // {{var}}.
        if ( preg_match_all( '/\{\{[a-zA-Z0-9_-]+\}\}/', $text, $matches ) ) {
            $placeholders = array_merge( $placeholders, $matches[0] );
        }

        // {name}.
        if ( preg_match_all( '/\{[a-zA-Z0-9_-]+\}/', $text, $matches ) ) {
            // Exclude already-matched {{var}}.
            foreach ( $matches[0] as $m ) {
                if ( strpos( $text, '{{' . trim( '{}' ) . '}}' ) === false ) {
                    $placeholders[] = $m;
                }
            }
        }

        // Shortcodes.
        if ( preg_match_all( '/\[\/?[a-zA-Z0-9_-]+[^\]]*\]/', $text, $matches ) ) {
            $placeholders = array_merge( $placeholders, $matches[0] );
        }

        return array_unique( $placeholders );
    }

    /**
     * Validate length ratio (translation should not be too short).
     *
     * @param array $input  Input strings.
     * @param array $output Output strings.
     * @return true|WP_Error
     */
    public function validate_length_ratio( array $input, array $output ) {
        foreach ( $output as $key => $translated ) {
            $original = $input[ $key ] ?? '';

            $original_len   = strlen( $original );
            $translated_len = strlen( $translated );

            // Skip very short strings (< 10 chars) where ratio is unreliable.
            if ( $original_len < 10 ) {
                continue;
            }

            $ratio = $translated_len / $original_len;

            if ( $ratio < self::MIN_LENGTH_RATIO ) {
                return new WP_Error(
                    'batch_length_ratio_fail',
                    sprintf(
                        'Batch integrity check failed: item "%s" translated length (%d) is less than 25%% of original (%d).',
                        $key,
                        $translated_len,
                        $original_len
                    )
                );
            }
        }

        return true;
    }

    /**
     * Calculate the recommended chunk size based on input content.
     *
     * @param array $strings Array of strings to translate.
     * @return int Recommended chunk size (number of strings).
     */
    public function calculate_chunk_size( array $strings ): int {
        if ( empty( $strings ) ) {
            return 0;
        }

        // Calculate average string length.
        $total_chars = 0;
        $count       = count( $strings );

        foreach ( $strings as $s ) {
            $total_chars += strlen( $s );
        }

        $avg_chars = $total_chars / $count;

        // For very long strings (> 500 chars avg), use smaller chunks.
        if ( $avg_chars > 500 ) {
            return min( 5, self::MAX_BATCH_STRINGS );
        }

        if ( $avg_chars > 200 ) {
            return min( 10, self::MAX_BATCH_STRINGS );
        }

        if ( $avg_chars > 100 ) {
            return min( 15, self::MAX_BATCH_STRINGS );
        }

        // For short strings, use max batch strings.
        return self::MAX_BATCH_STRINGS;
    }

    /**
     * Check if total input size exceeds safe limits.
     *
     * @param array $strings Array of strings.
     * @return true|WP_Error True if safe, WP_Error if too large.
     */
    public function check_batch_safety( array $strings ) {
        $total_chars = 0;
        foreach ( $strings as $s ) {
            $total_chars += strlen( $s );
        }

        if ( $total_chars > self::MAX_BATCH_CHARS ) {
            return new WP_Error(
                'batch_too_large',
                sprintf(
                    'Batch integrity check failed: total input size %d chars exceeds safe limit of %d chars. Split into smaller batches.',
                    $total_chars,
                    self::MAX_BATCH_CHARS
                )
            );
        }

        if ( count( $strings ) > self::MAX_BATCH_STRINGS ) {
            return new WP_Error(
                'batch_too_many_strings',
                sprintf(
                    'Batch integrity check failed: %d strings exceeds safe limit of %d. Split into smaller batches.',
                    count( $strings ),
                    self::MAX_BATCH_STRINGS
                )
            );
        }

        return true;
    }
}
