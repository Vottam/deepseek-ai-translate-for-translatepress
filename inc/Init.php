<?php
/**
 * Plugin initialization.
 *
 * @package hollisho\translatepress\translate\deepseek\inc
 */

namespace hollisho\translatepress\translate\deepseek\inc;

use hollisho\translatepress\translate\deepseek\inc\ServiceProvider\RegisterMachineTranslationEngines;
use hollisho\translatepress\translate\deepseek\inc\ServiceProvider\RegisterScripts;

/**
 * Class Init
 *
 * @author Hollis
 * @desc plugin init entry
 */
class Init {

    /**
     * Get registered services.
     *
     * @return string[]
     */
    public static function getService(): array {
        return [
            RegisterScripts::class,
            RegisterMachineTranslationEngines::class,
        ];
    }

    /**
     * Load registered services.
     *
     * @return void
     */
    public static function registerService() {
        foreach ( self::getService() as $class ) {
            $service = self::instantiate( $class );
            if ( method_exists( $service, 'register' ) ) {
                $service->register();
            }
        }
    }

    /**
     * Instantiate a class.
     *
     * @param string $class The class name.
     * @return object
     */
    public static function instantiate( $class ) {
        return new $class();
    }
}
