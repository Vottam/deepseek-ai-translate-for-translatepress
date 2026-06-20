<?php
/**
 * Source Leak Detector for translation validation.
 *
 * Detects when translated text retains substantial portions of the source
 * language, indicating a partial or failed translation.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class SourceLeakDetector
 *
 * Detects source language leakage in translated text by comparing
 * character sets, stopword overlap, and n-gram similarity.
 */
class SourceLeakDetector {

    /**
     * Minimum ratio of non-source characters required for non-Latin targets.
     * Below this threshold, the translation is considered to have source leak.
     *
     * @var float
     */
    const MIN_TARGET_SCRIPT_RATIO = 0.20;

    /**
     * Maximum ratio of Latin characters allowed in non-Latin target translations.
     * Above this threshold indicates source leak.
     *
     * @var float
     */
    const MAX_LATIN_RATIO_NON_LATIN_TARGET = 0.50;

    /**
     * Maximum similarity ratio between source and translation for Latin-script
     * language pairs. Above this threshold indicates the text was not translated.
     *
     * @var float
     */
    const MAX_SIMILARITY_RATIO = 0.60;

    /**
     * Minimum number of stopwords from source language found in translation
     * to trigger a source leak warning.
     *
     * @var int
     */
    const MAX_SOURCE_STOPWORDS = 3;

    /**
     * Known non-latin script language codes and their Unicode ranges.
     *
     * @var array
     */
    const NON_LATIN_SCRIPTS = [
        'ko' => ['name' => 'Korean', 'pattern' => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u'],
        'ja' => ['name' => 'Japanese', 'pattern' => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u'],
        'zh' => ['name' => 'Chinese', 'pattern' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u'],
        'zh_CN' => ['name' => 'Chinese (Simplified)', 'pattern' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u'],
        'zh_TW' => ['name' => 'Chinese (Traditional)', 'pattern' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u'],
        'th' => ['name' => 'Thai', 'pattern' => '/[\x{0E00}-\x{0E7F}]/u'],
        'hi' => ['name' => 'Hindi', 'pattern' => '/[\x{0900}-\x{097F}]/u'],
        'ar' => ['name' => 'Arabic', 'pattern' => '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u'],
        'ru' => ['name' => 'Russian', 'pattern' => '/[\x{0400}-\x{04FF}]/u'],
        'uk' => ['name' => 'Ukrainian', 'pattern' => '/[\x{0400}-\x{04FF}]/u'],
        'bg' => ['name' => 'Bulgarian', 'pattern' => '/[\x{0400}-\x{04FF}]/u'],
        'el' => ['name' => 'Greek', 'pattern' => '/[\x{0370}-\x{03FF}]/u'],
        'he' => ['name' => 'Hebrew', 'pattern' => '/[\x{0590}-\x{05FF}]/u'],
    ];

    /**
     * Source language stopwords for leak detection.
     * Keyed by language code.
     *
     * @var array
     */
    const SOURCE_STOPWORDS = [
        'es' => ['el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'de', 'del', 'en', 'con', 'por', 'para', 'que', 'es', 'son', 'como', 'más', 'pero', 'sus', 'se', 'al', 'lo', 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas', 'yo', 'tú', 'él', 'ella', 'nosotros', 'ellos', 'ellas', 'mi', 'tu', 'su', 'nuestro', 'tu', 'fue', 'ser', 'estar', 'hay', 'tiene', 'puede', 'hacer', 'sobre', 'entre', 'después', 'también', 'cuando', 'donde', 'porque', 'sin', 'hasta', 'desde', 'cada', 'otro', 'otra', 'otros', 'otras', 'muy', 'ya', 'sino', 'durante', 'antes', 'todo', 'toda', 'todos', 'todas'],
        'en' => ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'shall', 'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'out', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'because', 'but', 'and', 'or', 'if', 'while', 'that', 'this', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'],
        'pt' => ['o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas', 'com', 'por', 'para', 'que', 'é', 'são', 'como', 'mais', 'mas', 'seus', 'se', 'ao', 'lo', 'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas', 'eu', 'tu', 'ele', 'ela', 'nós', 'eles', 'elas', 'meu', 'teu', 'seu', 'nosso', 'foi', 'ser', 'estar', 'há', 'tem', 'pode', 'fazer', 'sobre', 'entre', 'depois', 'também', 'quando', 'onde', 'porque', 'sem', 'até', 'desde', 'cada', 'outro', 'outra', 'outros', 'outras', 'muito', 'já', 'senão', 'durante', 'antes', 'todo', 'toda', 'todos', 'todas'],
        'pt_BR' => ['o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas', 'com', 'por', 'para', 'que', 'é', 'são', 'como', 'mais', 'mas', 'seus', 'se', 'ao', 'lo', 'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas', 'eu', 'tu', 'ele', 'ela', 'nós', 'eles', 'elas', 'meu', 'teu', 'seu', 'nosso', 'foi', 'ser', 'estar', 'há', 'tem', 'pode', 'fazer', 'sobre', 'entre', 'depois', 'também', 'quando', 'onde', 'porque', 'sem', 'até', 'desde', 'cada', 'outro', 'outra', 'outros', 'outras', 'muito', 'já', 'senão', 'durante', 'antes', 'todo', 'toda', 'todos', 'todas'],
    ];

    /**
     * Brand names and technical terms to ignore during leak detection.
     *
     * @var array
     */
    const IGNORED_TERMS = [
        'MasterTrend', 'Windows', 'BitLocker', 'EFS', 'Microsoft', 'OpenAI', 'DeepSeek',
        'TranslatePress', 'WordPress', 'PHP', 'API', 'URL', 'HTTP', 'HTTPS', 'HTML',
        'CSS', 'JSON', 'XML', 'SQL', 'USB', 'PC', 'CD', 'DVD', 'RAM', 'CPU', 'GPU',
        'SSD', 'HDD', 'BIOS', 'UEFI', 'GPT', 'MBR', 'NTFS', 'FAT32',
    ];

    /**
     * Check if a target language uses a non-Latin script.
     *
     * @param string $target_language The target language code.
     * @return bool
     */
    public static function is_non_latin_target( string $target_language ): bool {
        return isset( self::NON_LATIN_SCRIPTS[ $target_language ] );
    }

    /**
     * Detect source leak for a single translated string.
     *
     * @param string $source_text   The original source text.
     * @param string $translated    The translated text.
     * @param string $source_lang   Source language code.
     * @param string $target_lang   Target language code.
     *
     * @return array ['leak_detected' => bool, 'leak_ratio' => float, 'details' => string]
     */
    public function detect_leak( string $source_text, string $translated, string $source_lang, string $target_lang ): array {
        // Skip if translation is empty.
        if ( empty( trim( $translated ) ) ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => 1.0,
                'details'       => 'Translation is empty.',
            ];
        }

        // Skip if identical (already caught by validate_not_identical, but defensive).
        if ( $source_text === $translated ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => 1.0,
                'details'       => 'Translation is identical to source.',
            ];
        }

        // Remove ignored terms before analysis.
        $clean_source    = $this->remove_ignored_terms( $source_text );
        $clean_translated = $this->remove_ignored_terms( $translated );

        // Strategy 1: Non-Latin target script detection.
        if ( self::is_non_latin_target( $target_lang ) ) {
            return $this->detect_non_latin_leak( $clean_source, $clean_translated, $source_lang, $target_lang );
        }

        // Strategy 2: Latin-script similarity detection.
        return $this->detect_latin_leak( $clean_source, $clean_translated, $source_lang );
    }

    /**
     * Detect source leak for non-Latin target languages.
     *
     * For Korean, Japanese, Chinese, Thai, Hindi, Arabic, Russian, etc.,
     * the translation should contain a minimum ratio of target script characters.
     * If it's mostly Latin characters, the translation likely contains source leak.
     *
     * @param string $source    Cleaned source text.
     * @param string $translated Cleaned translated text.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     *
     * @return array
     */
    private function detect_non_latin_leak( string $source, string $translated, string $source_lang, string $target_lang ): array {
        $script_info = self::NON_LATIN_SCRIPTS[ $target_lang ] ?? null;

        if ( ! $script_info ) {
            return [
                'leak_detected' => false,
                'leak_ratio'    => 0.0,
                'details'       => 'Unknown target script, skipping non-Latin check.',
            ];
        }

        // Count target script characters.
        preg_match_all( $script_info['pattern'], $translated, $target_matches );
        $target_char_count = count( $target_matches[0] );

        // Count Latin characters (a-z, A-Z).
        preg_match_all( '/[a-zA-Z]/', $translated, $latin_matches );
        $latin_char_count = count( $latin_matches[0] );

        // Count total alphabetic characters (target + Latin only, ignore digits/punctuation).
        $total_alpha = $target_char_count + $latin_char_count;

        if ( $total_alpha < 10 ) {
            return [
                'leak_detected' => false,
                'leak_ratio'    => 0.0,
                'details'       => 'Too few significant characters to analyze.',
            ];
        }

        $target_ratio = $target_char_count / $total_alpha;
        $latin_ratio  = $latin_char_count / $total_alpha;

        // Strategy 1: If Latin characters dominate (> 50%), it's likely source leak.
        if ( $latin_ratio > self::MAX_LATIN_RATIO_NON_LATIN_TARGET ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => $latin_ratio,
                'details'       => sprintf(
                    'Target %s: Latin ratio %.1f%% exceeds maximum %.1f%%. Target script ratio: %.1f%%.',
                    $script_info['name'],
                    $latin_ratio * 100,
                    self::MAX_LATIN_RATIO_NON_LATIN_TARGET * 100,
                    $target_ratio * 100
                ),
            ];
        }

        // Strategy 2: If target script ratio is below minimum AND there are significant Latin chars.
        if ( $target_ratio < self::MIN_TARGET_SCRIPT_RATIO && $latin_ratio > 0.20 ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => $latin_ratio,
                'details'       => sprintf(
                    'Target %s: script ratio %.1f%% below minimum %.1f%% with significant Latin %.1f%%.',
                    $script_info['name'],
                    $target_ratio * 100,
                    self::MIN_TARGET_SCRIPT_RATIO * 100,
                    $latin_ratio * 100
                ),
            ];
        }

        // Strategy 3: Check for long Latin word sequences (4+ consecutive Latin words).
        // This catches embedded Spanish/English sentences in non-Latin text.
        preg_match_all( '/[a-zA-Z]{3,}(?:\s+[a-zA-Z]{3,}){3,}/', $translated, $latin_sequences );
        if ( ! empty( $latin_sequences[0] ) ) {
            $longest_seq = max( array_map( 'strlen', $latin_sequences[0] ) );
            if ( $longest_seq > 20 ) {
                return [
                    'leak_detected' => true,
                    'leak_ratio'    => $latin_ratio,
                    'details'       => sprintf(
                        'Target %s: found Latin word sequence of %d chars (likely untranslated text).',
                        $script_info['name'],
                        $longest_seq
                    ),
                ];
            }
        }

        return [
            'leak_detected' => false,
            'leak_ratio'    => $latin_ratio,
            'details'       => sprintf(
                'Target %s: script ratio %.1f%%, Latin ratio %.1f%% — acceptable.',
                $script_info['name'],
                $target_ratio * 100,
                $latin_ratio * 100
            ),
        ];
    }

    /**
     * Detect source leak for Latin-script target languages.
     *
     * Uses stopword overlap and n-gram similarity to detect when a
     * Latin-script translation retains too much of the source language.
     *
     * @param string $source     Cleaned source text.
     * @param string $translated Cleaned translated text.
     * @param string $source_lang Source language code.
     *
     * @return array
     */
    private function detect_latin_leak( string $source, string $translated, string $source_lang ): array {
        // Check source stopword presence in translation.
        $stopword_result = $this->check_stopword_leak( $translated, $source_lang );

        // Check n-gram similarity.
        $ngram_similarity = $this->calculate_ngram_similarity( $source, $translated );

        // Check for long identical substrings.
        $longest_match = $this->longest_common_substring_ratio( $source, $translated );

        $leak_detected = false;
        $details       = [];

        if ( $stopword_result['count'] >= self::MAX_SOURCE_STOPWORDS ) {
            $leak_detected = true;
            $details[] = sprintf(
                'Found %d source stopwords in translation.',
                $stopword_result['count']
            );
        }

        if ( $ngram_similarity > self::MAX_SIMILARITY_RATIO ) {
            $leak_detected = true;
            $details[] = sprintf(
                'N-gram similarity %.1f%% exceeds maximum %.1f%%.',
                $ngram_similarity * 100,
                self::MAX_SIMILARITY_RATIO * 100
            );
        }

        if ( $longest_match > 0.50 ) {
            $leak_detected = true;
            $details[] = sprintf(
                'Longest common substring ratio %.1f%% exceeds 50%%.',
                $longest_match * 100
            );
        }

        $max_ratio = max( $stopword_result['ratio'], $ngram_similarity, $longest_match );

        return [
            'leak_detected' => $leak_detected,
            'leak_ratio'    => $max_ratio,
            'details'       => $leak_detected ? implode( ' ', $details ) : 'No significant source leak detected.',
        ];
    }

    /**
     * Check how many source language stopwords appear in the translation.
     *
     * @param string $translated   The translated text.
     * @param string $source_lang  Source language code.
     *
     * @return array ['count' => int, 'ratio' => float]
     */
    private function check_stopword_leak( string $translated, string $source_lang ): array {
        $stopwords = self::SOURCE_STOPWORDS[ $source_lang ] ?? self::SOURCE_STOPWORDS['es'] ?? [];

        if ( empty( $stopwords ) ) {
            return ['count' => 0, 'ratio' => 0.0];
        }

        $translated_lower = mb_strtolower( $translated, 'UTF-8' );
        $found            = 0;

        foreach ( $stopwords as $word ) {
            // Use word boundary matching.
            if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/ui', $translated_lower ) ) {
                $found++;
            }
        }

        return [
            'count' => $found,
            'ratio' => $found / count( $stopwords ),
        ];
    }

    /**
     * Calculate character-level bigram similarity between two strings.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return float Similarity ratio (0.0 to 1.0).
     */
    private function calculate_ngram_similarity( string $str1, string $str2 ): float {
        $str1 = mb_strtolower( trim( $str1 ), 'UTF-8' );
        $str2 = mb_strtolower( trim( $str2 ), 'UTF-8' );

        if ( empty( $str1 ) || empty( $str2 ) ) {
            return 0.0;
        }

        // For very long strings, sample to avoid excessive computation.
        if ( mb_strlen( $str1 ) > 2000 ) {
            $str1 = mb_substr( $str1, 0, 2000, 'UTF-8' );
        }
        if ( mb_strlen( $str2 ) > 2000 ) {
            $str2 = mb_substr( $str2, 0, 2000, 'UTF-8' );
        }

        $ngrams1 = $this->get_character_ngrams( $str1, 2 );
        $ngrams2 = $this->get_character_ngrams( $str2, 2 );

        if ( empty( $ngrams1 ) || empty( $ngrams2 ) ) {
            return 0.0;
        }

        $intersection = count( array_intersect( $ngrams1, $ngrams2 ) );
        $union        = count( array_unique( array_merge( $ngrams1, $ngrams2 ) ) );

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Generate character-level n-grams from a string.
     *
     * @param string $str The input string.
     * @param int    $n   The n-gram size.
     *
     * @return array
     */
    private function get_character_ngrams( string $str, int $n ): array {
        $len     = mb_strlen( $str, 'UTF-8' );
        $ngrams  = [];

        for ( $i = 0; $i <= $len - $n; $i++ ) {
            $ngrams[] = mb_substr( $str, $i, $n, 'UTF-8' );
        }

        return $ngrams;
    }

    /**
     * Calculate the ratio of the longest common substring to the shorter string.
     *
     * Uses a sampling approach for long strings to avoid O(n^2) complexity.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return float Ratio of longest common substring to shorter string length.
     */
    private function longest_common_substring_ratio( string $str1, string $str2 ): float {
        $str1 = mb_strtolower( trim( $str1 ), 'UTF-8' );
        $str2 = mb_strtolower( trim( $str2 ), 'UTF-8' );

        $len1 = mb_strlen( $str1, 'UTF-8' );
        $len2 = mb_strlen( $str2, 'UTF-8' );

        if ( $len1 === 0 || $len2 === 0 ) {
            return 0.0;
        }

        $shorter_len = min( $len1, $len2 );

        // For long strings, use sliding window approach.
        if ( $shorter_len > 500 ) {
            return $this->sampled_lcs_ratio( $str1, $str2 );
        }

        // For shorter strings, use direct comparison.
        $longest = 0;
        for ( $i = 0; $i < $len1; $i++ ) {
            for ( $j = 0; $j < $len2; $j++ ) {
                $k = 0;
                while (
                    $i + $k < $len1 &&
                    $j + $k < $len2 &&
                    mb_substr( $str1, $i + $k, 1, 'UTF-8' ) === mb_substr( $str2, $j + $k, 1, 'UTF-8' )
                ) {
                    $k++;
                }
                $longest = max( $longest, $k );
            }
        }

        return $longest / $shorter_len;
    }

    /**
     * Sampled LCS ratio for long strings.
     *
     * Checks fixed-length windows for exact matches.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return float
     */
    private function sampled_lcs_ratio( string $str1, string $str2 ): float {
        $window_size = 20;
        $len1        = mb_strlen( $str1, 'UTF-8' );
        $len2        = mb_strlen( $str2, 'UTF-8' );
        $shorter     = min( $len1, $len2 );

        // Build set of windows from str2.
        $windows = [];
        for ( $j = 0; $j <= $len2 - $window_size; $j += $window_size ) {
            $windows[] = mb_substr( $str2, $j, $window_size, 'UTF-8' );
        }

        if ( empty( $windows ) ) {
            return 0.0;
        }

        $matched_windows = 0;
        $total_checked   = 0;

        for ( $i = 0; $i <= $len1 - $window_size; $i += $window_size ) {
            $window = mb_substr( $str1, $i, $window_size, 'UTF-8' );
            $total_checked++;
            if ( in_array( $window, $windows, true ) ) {
                $matched_windows++;
            }
        }

        return $total_checked > 0 ? ( $matched_windows * $window_size ) / $shorter : 0.0;
    }

    /**
     * Remove ignored terms (brands, technical terms) from text before analysis.
     *
     * @param string $text The text to clean.
     * @return string Cleaned text.
     */
    private function remove_ignored_terms( string $text ): string {
        foreach ( self::IGNORED_TERMS as $term ) {
            $text = str_ireplace( $term, '', $text );
        }

        // Remove URLs.
        $text = preg_replace( '/https?:\/\/[^\s]+/', '', $text );

        // Remove file paths like C:\Windows\System32.
        $text = preg_replace( '/[A-Z]:\\\\[^\s]+/', '', $text );

        // Remove commands like net user, cd, dir.
        $text = preg_replace( '/\b(net\s+user|cd\s|dir\s)\b/i', '', $text );

        // Remove placeholders.
        $text = preg_replace( '/\{[a-zA-Z0-9_-]+\}/', '', $text );
        $text = preg_replace( '/\{\{[a-zA-Z0-9_-]+\}\}/', '', $text );
        $text = preg_replace( '/%[0-9]*\$?[sdfeEgGcxXobB%]/', '', $text );

        return $text;
    }

    /**
     * Batch-level leak detection.
     *
     * Analyzes an entire batch of translations for source leak patterns.
     *
     * @param array $input_strings  Original strings (keyed).
     * @param array $output_strings Translated strings (keyed).
     * @param string $source_lang   Source language code.
     * @param string $target_lang   Target language code.
     *
     * @return array ['leak_detected' => bool, 'leak_ratio' => float, 'leaking_keys' => array, 'details' => string]
     */
    public function detect_batch_leak( array $input_strings, array $output_strings, string $source_lang, string $target_lang ): array {
        $leaking_keys   = [];
        $total_leak_ratio = 0.0;
        $count          = 0;

        foreach ( $output_strings as $key => $translated ) {
            $source = $input_strings[ $key ] ?? '';
            $result = $this->detect_leak( $source, $translated, $source_lang, $target_lang );

            if ( $result['leak_detected'] ) {
                $leaking_keys[ $key ] = $result;
            }

            $total_leak_ratio += $result['leak_ratio'];
            $count++;
        }

        $avg_leak_ratio = $count > 0 ? $total_leak_ratio / $count : 0.0;
        $leak_percentage = count( $leaking_keys ) / max( $count, 1 );

        // Batch fails if more than 10% of items have source leak.
        $leak_detected = $leak_percentage > 0.10;

        return [
            'leak_detected' => $leak_detected,
            'leak_ratio'    => $avg_leak_ratio,
            'leaking_keys'  => $leaking_keys,
            'leak_percentage' => $leak_percentage,
            'details'       => $leak_detected
                ? sprintf( 'Source leak detected in %d/%d items (%.1f%%).', count( $leaking_keys ), $count, $leak_percentage * 100 )
                : 'No significant source leak detected in batch.',
        ];
    }
}
