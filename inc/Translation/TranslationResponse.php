<?php
/**
 * Value object representing a translation response.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class TranslationResponse
 *
 * Immutable value object containing translation response data.
 */
class TranslationResponse {

    /** @var string|null */
    private $translated_text;

    /** @var string */
    private $provider_id;

    /** @var string|null */
    private $model;

    /** @var array */
    private $metadata;

    /** @var string|null */
    private $error_code;

    /** @var string|null */
    private $error_message;

    /** @var bool */
    private $success;

    /**
     * TranslationResponse constructor.
     *
     * @param string|null $translated_text The translated text.
     * @param string      $provider_id     Provider identifier.
     * @param string|null $model           Model used.
     * @param array       $metadata       Redacted metadata.
     * @param string|null $error_code      Error code if failed.
     * @param string|null $error_message   Error message if failed.
     */
    public function __construct(
        string $translated_text = null,
        string $provider_id = '',
        string $model = null,
        array $metadata = [],
        string $error_code = null,
        string $error_message = null
    ) {
        $this->translated_text = $translated_text;
        $this->provider_id     = $provider_id;
        $this->model           = $model;
        $this->metadata        = $metadata;
        $this->error_code      = $error_code;
        $this->error_message   = $error_message;
        $this->success         = ( null === $error_code && null !== $translated_text );
    }

    public function get_translated_text(): ?string {
        return $this->translated_text;
    }

    public function get_provider_id(): string {
        return $this->provider_id;
    }

    public function get_model(): ?string {
        return $this->model;
    }

    public function get_metadata(): array {
        return $this->metadata;
    }

    public function get_error_code(): ?string {
        return $this->error_code;
    }

    public function get_error_message(): ?string {
        return $this->error_message;
    }

    public function is_success(): bool {
        return $this->success;
    }

    /**
     * Check if the response has an error.
     *
     * @return bool
     */
    public function has_error(): bool {
        return ! $this->success;
    }

    /**
     * Create a success response.
     *
     * @param string      $translated_text The translated text.
     * @param string      $provider_id     Provider identifier.
     * @param string|null $model           Model used.
     * @param array       $metadata       Redacted metadata.
     * @return self
     */
    public static function success(
        string $translated_text,
        string $provider_id,
        string $model = null,
        array $metadata = []
    ): self {
        return new self( $translated_text, $provider_id, $model, $metadata );
    }

    /**
     * Create an error response.
     *
     * @param string $error_code    Error code.
     * @param string $error_message Error message.
     * @param string $provider_id   Provider identifier.
     * @return self
     */
    public static function error(
        string $error_code,
        string $error_message,
        string $provider_id = ''
    ): self {
        return new self( null, $provider_id, null, [], $error_code, $error_message );
    }
}
