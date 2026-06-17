<?php
/**
 * OpenAI translation provider (stub for Sprint 1).
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Providers
 */

namespace hollisho\translatepress\translate\deepseek\inc\Providers;

use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationRequest;
use hollisho\translatepress\translate\deepseek\inc\Translation\TranslationResponse;

/**
 * Class OpenAIProvider
 *
 * OpenAI translation provider — STUB for Sprint 1.
 * Full implementation deferred to Sprint 2.
 *
 * This class exists so that the ProviderRegistry and admin UI
 * can reference it, but it does not make any API calls.
 */
class OpenAIProvider extends AbstractProvider {

    const ENGINE_KEY = 'openai';

    const FIELD_API_KEY = '***';

    const DEFAULT_API_URL = 'https://api.openai.com/v1/responses';

    const DEFAULT_MODEL = 'gpt-5.4-mini';

    /**
     * {@inheritDoc}
     */
    public function get_id(): string {
        return self::ENGINE_KEY;
    }

    /**
     * {@inheritDoc}
     */
    public function get_label(): string {
        return __( 'OpenAI', 'hollisho-integration-deepseek-for-translatepress' );
    }

    /**
     * {@inheritDoc}
     */
    public function get_api_url(): string {
        return self::DEFAULT_API_URL;
    }

    /**
     * {@inheritDoc}
     */
    protected function get_api_key_setting_name(): string {
        return self::FIELD_API_KEY;
    }

    /**
     * {@inheritDoc}
     */
    protected function build_payload( TranslationRequest $request ): array {
        // STUB: Full implementation in Sprint 2.
        return [
            'model' => self::DEFAULT_MODEL,
            'input' => $request->get_source_text(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function build_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->get_api_key(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function parse_response( array $response ): TranslationResponse {
        // STUB: Full implementation in Sprint 2.
        return TranslationResponse::error(
            'not_implemented',
            __( 'OpenAI provider is not yet implemented. This is a Sprint 1 stub.', 'hollisho-integration-deepseek-for-translatepress' ),
            $this->get_id()
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function perform_key_validation( $api_key ) {
        // STUB: Full implementation in Sprint 2.
        $this->logger->info( 'OpenAI key validation skipped (Sprint 1 stub)' );
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function translate( TranslationRequest $request ): TranslationResponse {
        // STUB: Return error instead of making API calls.
        $this->logger->info( 'OpenAI translate called but not implemented (Sprint 1 stub)' );
        return TranslationResponse::error(
            'not_implemented',
            __( 'OpenAI provider is not yet implemented. This is a Sprint 1 stub.', 'hollisho-integration-deepseek-for-translatepress' ),
            $this->get_id()
        );
    }
}
