<?php
/**
 * Chunking Strategy for safe batch translation.
 *
 * Determines optimal chunk size based on content characteristics
 * and provides fallback strategies for failed chunks.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class ChunkingStrategy
 *
 * Handles splitting large translation batches into safe-sized chunks
 * and provides retry/fallback strategies for failed chunks.
 */
class ChunkingStrategy {

    /**
     * Default max strings per request.
     */
    const DEFAULT_MAX_STRINGS = 25;

    /**
     * Default max chars per request.
     */
    const DEFAULT_MAX_CHARS = 50000;

    /**
     * Default max estimated tokens per request.
     */
    const DEFAULT_MAX_TOKENS = 32000;

    /**
     * Minimum chunk size for retry.
     */
    const MIN_CHUNK_SIZE = 1;

    /**
     * Split an array of strings into safe-sized chunks.
     *
     * @param array $strings     Array of strings to translate.
     * @param int   $max_strings Maximum strings per chunk.
     * @param int   $max_chars   Maximum total chars per chunk.
     *
     * @return array Array of chunks, each being an array of strings.
     */
    public static function split(
        array $strings,
        int $max_strings = self::DEFAULT_MAX_STRINGS,
        int $max_chars = self::DEFAULT_MAX_CHARS
    ): array {
        if ( empty( $strings ) ) {
            return [];
        }

        $chunks     = [];
        $current    = [];
        $current_chars = 0;

        foreach ( $strings as $key => $string ) {
            $string_chars = strlen( $string );

            // If adding this string would exceed limits, start a new chunk.
            if ( ! empty( $current ) && (
                count( $current ) >= $max_strings ||
                $current_chars + $string_chars > $max_chars
            ) ) {
                $chunks[] = $current;
                $current    = [];
                $current_chars = 0;
            }

            $current[ $key ] = $string;
            $current_chars += $string_chars;
        }

        // Add the last chunk.
        if ( ! empty( $current ) ) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Calculate optimal chunk size based on content analysis.
     *
     * @param array $strings Array of strings to translate.
     * @return array ['strings_per_chunk' => int, 'estimated_chunks' => int, 'total_chars' => int]
     */
    public static function analyze( array $strings ): array {
        $total_strings = count( $strings );
        $total_chars   = 0;
        $max_single    = 0;

        foreach ( $strings as $s ) {
            $len = strlen( $s );
            $total_chars += $len;
            $max_single = max( $max_single, $len );
        }

        $avg_chars = $total_strings > 0 ? $total_chars / $total_strings : 0;

        // Determine chunk size based on average length.
        if ( $avg_chars > 500 || $max_single > 2000 ) {
            $strings_per_chunk = 5;
        } elseif ( $avg_chars > 200 || $max_single > 500 ) {
            $strings_per_chunk = 10;
        } elseif ( $avg_chars > 100 ) {
            $strings_per_chunk = 15;
        } else {
            $strings_per_chunk = self::DEFAULT_MAX_STRINGS;
        }

        // Also consider total chars per chunk.
        $chars_per_chunk = $strings_per_chunk * $avg_chars;
        if ( $chars_per_chunk > self::DEFAULT_MAX_CHARS ) {
            $strings_per_chunk = max( 1, (int) ( self::DEFAULT_MAX_CHARS / $avg_chars ) );
        }

        $estimated_chunks = max( 1, (int) ceil( $total_strings / $strings_per_chunk ) );

        return [
            'strings_per_chunk' => $strings_per_chunk,
            'estimated_chunks'  => $estimated_chunks,
            'total_chars'       => $total_chars,
            'total_strings'     => $total_strings,
            'avg_chars'         => (int) $avg_chars,
            'max_single'        => $max_single,
        ];
    }

    /**
     * Get retry strategy for a failed chunk.
     *
     * Returns progressively smaller chunk sizes to try.
     *
     * @param int $current_size The current chunk size that failed.
     * @return array Array of chunk sizes to try in order.
     */
    public static function get_retry_strategy( int $current_size ): array {
        $strategies = [];

        // First retry: halve the chunk.
        $half = max( 1, (int) ( $current_size / 2 ) );
        if ( $half < $current_size ) {
            $strategies[] = $half;
        }

        // Second retry: quarter the chunk.
        $quarter = max( 1, (int) ( $current_size / 4 ) );
        if ( $quarter < $half ) {
            $strategies[] = $quarter;
        }

        // Final fallback: item-by-item.
        $strategies[] = 1;

        return $strategies;
    }

    /**
     * Estimate token count for a set of strings.
     *
     * Rough approximation: 1 token ≈ 4 chars for Latin scripts,
     * 1 token ≈ 2 chars for CJK/Thai/Hindi.
     *
     * @param array $strings Array of strings.
     * @return int Estimated token count.
     */
    public static function estimate_tokens( array $strings ): int {
        $total = 0;
        foreach ( $strings as $s ) {
            $len = strlen( $s );
            // Check if string contains mostly CJK/Thai/Hindi characters.
            if ( preg_match( '/[\x{4e00}-\x{9fff}\x{0E00}-\x{0E7F}\x{0900}-\x{097F}]/u', $s ) ) {
                $total += (int) ceil( $len / 2 );
            } else {
                $total += (int) ceil( $len / 4 );
            }
        }
        return $total;
    }

    /**
     * Check if a chunk is within safe limits.
     *
     * @param array $chunk      Array of strings.
     * @param int   $max_strings Maximum strings allowed.
     * @param int   $max_chars   Maximum chars allowed.
     * @param int   $max_tokens  Maximum estimated tokens allowed.
     * @return bool
     */
    public static function is_safe(
        array $chunk,
        int $max_strings = self::DEFAULT_MAX_STRINGS,
        int $max_chars = self::DEFAULT_MAX_CHARS,
        int $max_tokens = self::DEFAULT_MAX_TOKENS
    ): bool {
        if ( count( $chunk ) > $max_strings ) {
            return false;
        }

        $total_chars = 0;
        foreach ( $chunk as $s ) {
            $total_chars += strlen( $s );
        }

        if ( $total_chars > $max_chars ) {
            return false;
        }

        if ( self::estimate_tokens( $chunk ) > $max_tokens ) {
            return false;
        }

        return true;
    }
}
