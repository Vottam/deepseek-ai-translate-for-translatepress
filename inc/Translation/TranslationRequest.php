<?php
/**
 * Value object representing a translation request.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class TranslationRequest
 *
 * Immutable value object containing translation request data.
 */
class TranslationRequest {

    /** @var string */
    private $source_text;

    /** @var string */
    private $source_language;

    /** @var string */
    private $target_language;

    /** @var string|null */
    private $model;

    /** @var array */
    private $extra;

    /**
     * TranslationRequest constructor.
     *
     * @param string      $source_text     The text to translate.
     * @param string      $source_language Source language code.
     * @param string      $target_language Target language code.
     * @param string|null $model           Optional model override.
     * @param array       $extra           Extra provider-specific settings.
     */
    public function __construct(
        string $source_text,
        string $source_language,
        string $target_language,
        string $model = null,
        array $extra = []
    ) {
        $this->source_text     = $source_text;
        $this->source_language = $source_language;
        $this->target_language = $target_language;
        $this->model           = $model;
        $this->extra           = $extra;
    }

    public function get_source_text(): string {
        return $this->source_text;
    }

    public function get_source_language(): string {
        return $this->source_language;
    }

    public function get_target_language(): string {
        return $this->target_language;
    }

    public function get_model(): ?string {
        return $this->model;
    }

    public function get_extra(): array {
        return $this->extra;
    }

    /**
     * Create a new instance with modified source text.
     *
     * @param string $source_text New source text.
     * @return self
     */
    public function with_source_text( string $source_text ): self {
        return new self(
            $source_text,
            $this->source_language,
            $this->target_language,
            $this->model,
            $this->extra
        );
    }
}
