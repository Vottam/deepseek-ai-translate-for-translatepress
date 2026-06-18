<?php
/**
 * Preserves placeholders and HTML tags during translation.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class PlaceholderPreserver
 *
 * Replaces placeholders, shortcodes, HTML tags, and other non-translatable
 * elements with numeric markers before translation, then restores them after.
 *
 * This is a conservative implementation for Sprint 1.
 * Full implementation with HTML tag preservation is deferred to Sprint 2/3.
 */
class PlaceholderPreserver {

    /** @var array Map of placeholder markers to original values. */
    private $placeholders = [];

    /** @var int Counter for generating unique markers. */
    private $counter = 0;

    /**
     * Replace non-translatable elements with numeric markers.
     *
     * Currently handles:
     * - WordPress shortcodes: [shortcode] or [shortcode]content[/shortcode]
     * - sprintf placeholders: %s, %d, %1$s, etc.
     * - Template variables: {{var}}, {name}
     *
     * Deferred to Sprint 2/3:
     * - HTML tag preservation
     * - URL preservation
     * - Complex nested shortcodes
     *
     * @param string $text The original text.
     * @return string The text with placeholders replaced by markers.
     */
    public function preserve( string $text ): string {
        $this->placeholders = [];
        $this->counter      = 0;

        // Preserve WordPress shortcodes.
        $text = $this->preserve_pattern( $text, '/\[\/?[a-zA-Z0-9_-]+(?:\s[^\]]*?)?(?:\].*?\[\/\1\]|\s*\/\]|\s*\])/s' );

        // Preserve sprintf-style placeholders.
        $text = $this->preserve_pattern( $text, '/%[0-9]*\$?[sdfeEgGcxXobB%]/' );

        // Preserve {{var}} template variables.
        $text = $this->preserve_pattern( $text, '/\{\{[a-zA-Z0-9_-]+\}\}/' );

        // Preserve {name} template variables (but not already matched {{var}}).
        $text = $this->preserve_pattern( $text, '/\{[a-zA-Z0-9_-]+\}/' );

        return $text;
    }

    /**
     * Restore original placeholders from markers.
     *
     * @param string $text The translated text with markers.
     * @return string The text with original placeholders restored.
     */
    public function restore( string $text ): string {
        foreach ( $this->placeholders as $marker => $original ) {
            $text = str_replace( $marker, $original, $text );
        }
        return $text;
    }

    /**
     * Preserve all matches of a regex pattern.
     *
     * @param string $text    The text to process.
     * @param string $pattern The regex pattern.
     * @return string The text with matches replaced by markers.
     */
    private function preserve_pattern( string $text, string $pattern ): string {
        return preg_replace_callback( $pattern, function ( $matches ) {
            $marker = $this->get_marker();
            $this->placeholders[ $marker ] = $matches[0];
            return $marker;
        }, $text );
    }

    /**
     * Generate a unique placeholder marker.
     *
     * @return string The marker string.
     */
    private function get_marker(): string {
        $this->counter++;
        return '___PH' . $this->counter . '___';
    }

    /**
     * Get the current placeholder map (for debugging).
     *
     * @return array
     */
    public function get_placeholders(): array {
        return $this->placeholders;
    }
}
