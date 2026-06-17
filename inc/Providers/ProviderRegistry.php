<?php
/**
 * Registry for translation providers.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Providers
 */

namespace hollisho\translatepress\translate\deepseek\inc\Providers;

use hollisho\translatepress\translate\deepseek\inc\Contracts\ProviderInterface;

/**
 * Class ProviderRegistry
 *
 * Central registry for managing translation provider instances.
 */
class ProviderRegistry {

    /** @var ProviderInterface[] */
    private $providers = [];

    /** @var self|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (for testing).
     *
     * @return void
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * Register a provider.
     *
     * @param ProviderInterface $provider The provider to register.
     * @return void
     */
    public function register( ProviderInterface $provider ): void {
        $this->providers[ $provider->get_id() ] = $provider;
    }

    /**
     * Get a provider by ID.
     *
     * @param string $provider_id The provider identifier.
     * @return ProviderInterface|null The provider or null if not found.
     */
    public function get( string $provider_id ): ?ProviderInterface {
        return $this->providers[ $provider_id ] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @return ProviderInterface[]
     */
    public function get_all(): array {
        return $this->providers;
    }

    /**
     * Check if a provider is registered.
     *
     * @param string $provider_id The provider identifier.
     * @return bool
     */
    public function has( string $provider_id ): bool {
        return isset( $this->providers[ $provider_id ] );
    }

    /**
     * Get all provider IDs.
     *
     * @return string[]
     */
    public function get_ids(): array {
        return array_keys( $this->providers );
    }
}
