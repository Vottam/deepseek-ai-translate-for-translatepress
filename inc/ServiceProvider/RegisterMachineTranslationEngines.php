<?php
namespace hollisho\translatepress\translate\deepseek\inc\ServiceProvider;

use hollisho\translatepress\translate\deepseek\inc\Base\ServiceProviderInterface;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\DeepSeekTranslationEngine;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\OpenAITranslationEngine;
use TRP_Translate_Press;

/**
 * @author Hollis
 * @desc machine translation engine service provider
 * Class TranslatePressMachineTranslationEngines
 * @package hollisho\translatepress\translate\deepseek\inc\ServiceProvider
 */
class RegisterMachineTranslationEngines implements ServiceProviderInterface
{

    public function register()
    {
        add_filter( 'trp_machine_translation_engines', [$this, 'add_engine'], 20 );
        add_filter( 'trp_automatic_translation_engines_classes', [$this, 'add_engine_classes'], 20, 1 );
        add_action( 'trp_machine_translation_extra_settings_middle', [$this, 'add_settings'], 20, 1  );
        add_action( 'trp_machine_translation_sanitize_settings', [$this, 'sanitize_settings'], 20, 2 );


        add_filter( 'trp_deepseek_target_language', [$this, 'configure_api_target_language'], 20, 3 );
        add_filter( 'trp_deepseek_source_language', [$this, 'configure_api_source_language'], 20, 3 );

    }

    public function add_engine_classes( $classes ){
        $classes[DeepSeekTranslationEngine::ENGINE_KEY] = DeepSeekTranslationEngine::class;
        $classes[OpenAITranslationEngine::ENGINE_KEY]  = OpenAITranslationEngine::class;
        return $classes;
    }

    public function add_engine( $engines ){
        $engines[] = [
            'value' => DeepSeekTranslationEngine::ENGINE_KEY,
            'label' => esc_html(__('DeepSeek', 'hollisho-integration-deepseek-for-translatepress')),
        ];

        $engines[] = [
            'value' => OpenAITranslationEngine::ENGINE_KEY,
            'label' => esc_html(__('OpenAI', 'hollisho-integration-deepseek-for-translatepress')),
        ];

        return $engines;
    }

    public function add_settings( $settings ){
        $trp                = TRP_Translate_Press::get_trp_instance();
        $machine_translator = $trp->get_component( 'machine_translator' );

        // Error messages.
        $show_errors   = false;
        $error_message = '';

        $translation_engine = $settings['translation-engine'] ?? '';

        // Check for API errors when DeepSeek is selected.
        if ( DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
            $trp = TRP_Translate_Press::get_trp_instance();
            $machine_translator = $trp->get_component( 'machine_translator' );
            $api_check = $machine_translator->check_api_key_validity();
        }

        if ( isset($api_check) && true === $api_check['error'] ) {
            $error_message = $api_check['message'];
            $show_errors    = true;
        }

        $text_input_classes = array(
            'trp-text-input',
        );

        // DeepSeek API key field.
        if ( DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
            if ( $show_errors ) {
                $text_input_classes[] = 'trp-text-input-error';
            }
            $this->render_api_key_field(
                $translation_engine,
                $text_input_classes,
                $show_errors,
                $error_message,
                DeepSeekTranslationEngine::FIELD_API_KEY,
                'deepseek api key',
                'https://api-docs.deepseek.com/',
                $machine_translator
            );
        }

        // OpenAI API key field.
        if ( OpenAITranslationEngine::ENGINE_KEY === $translation_engine ) {
            $this->render_openai_api_key_field( $settings, $machine_translator );
        }
    }

    /**
     * Render API key field for DeepSeek (original behavior).
     */
    private function render_api_key_field(
        $translation_engine,
        $text_input_classes,
        $show_errors,
        $error_message,
        $field_key,
        $label,
        $docs_url,
        $machine_translator
    ) {
        ?>
        <tr>
            <th scope="row">
                <?php
                    echo esc_html(__($label, 'hollisho-integration-deepseek-for-translatepress'));
                ?>
            </th>
            <td>
                <?php
                if ( $show_errors ) {
                    ?>
                    <p class="trp-error-inline">
                        <?php echo wp_kses_post( $error_message ); ?>
                    </p>
                    <?php
                }
                ?>
                <input type="password" id="trp-<?php echo esc_attr( $field_key ); ?>"
                       class="<?php echo esc_html( implode( ' ', $text_input_classes ) ); ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr( $field_key ); ?>]"
                       value=""
                       placeholder="<?php esc_attr_e( 'Enter API key', 'hollisho-integration-deepseek-for-translatepress' ); ?>"
                       autocomplete="off"
                />
                <?php
                if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) && DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors );
                }
                ?>
                <p class="description">
                    <?php
                        $text = __( 'Visit <a href="%s" target="_blank">this link</a> to see how you can set up an API key and control API costs.', 'hollisho-integration-deepseek-for-translatepress' );
                        echo wp_kses( sprintf( $text, $docs_url ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] )
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render API key field for OpenAI.
     *
     * @param array $settings         The settings.
     * @param object $machine_translator The machine translator instance.
     * @return void
     */
    private function render_openai_api_key_field( $settings, $machine_translator ) {
        $api_key = $settings[OpenAITranslationEngine::FIELD_API_KEY] ?? '';
        $is_configured = ! empty( $api_key );
        ?>
        <tr>
            <th scope="row">
                <?php esc_html_e( 'OpenAI API key', 'hollisho-integration-deepseek-for-translatepress' ); ?>
            </th>
            <td>
                <input type="password"
                       id="trp-openai-api-key"
                       class="trp-text-input"
                       name="trp_machine_translation_settings[<?php echo esc_attr( OpenAITranslationEngine::FIELD_API_KEY ); ?>]"
                       value=""
                       placeholder="<?php echo $is_configured ? esc_attr__( '•••••••• configured (enter new key to change)', 'hollisho-integration-deepseek-for-translatepress' ) : esc_attr__( 'Enter your OpenAI API key', 'hollisho-integration-deepseek-for-translatepress' ); ?>"
                       autocomplete="off"
                />
                <?php if ( $is_configured ) : ?>
                    <p class="description">
                        <?php esc_html_e( 'A key is configured. Enter a new key above to replace it.', 'hollisho-integration-deepseek-for-translatepress' ); ?>
                    </p>
                <?php else : ?>
                    <p class="description">
                        <?php
                            $text = __( 'Visit <a href="%s" target="_blank">this link</a> to get your OpenAI API key.', 'hollisho-integration-deepseek-for-translatepress' );
                            echo wp_kses( sprintf( $text, 'https://platform.openai.com/api-keys' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] );
                        ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function sanitize_settings( $settings, $mt_settings ){
        if( !empty( $mt_settings[DeepSeekTranslationEngine::FIELD_API_KEY] ) )
            $settings[DeepSeekTranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[DeepSeekTranslationEngine::FIELD_API_KEY] );

        if( !empty( $mt_settings[OpenAITranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenAITranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[OpenAITranslationEngine::FIELD_API_KEY] );

        return $settings;
    }

    /**
     * Particularities for source language in API.
     *
     * @param $source_language
     * @param $source_language_code
     * @param $target_language_code
     * @return string
     */
    public function configure_api_source_language($source_language, $source_language_code, $target_language_code ){
        $exceptions_source_mapping_codes = array(
            'zh_HK' => 'zh-tw',
            'zh_TW' => 'zh-tw',
            'zh_CN' => 'zh-cn',
            'en_GB' => 'en',
            'en_US' => 'en',
            'en_CA' => 'en',
            'en_ZA' => 'en',
            'en_NZ' => 'en',
            'en_AU' => 'en',
        );
        if ( isset( $exceptions_source_mapping_codes[$source_language_code] ) ){
            $source_language = $exceptions_source_mapping_codes[$source_language_code];
        } else {
            $localeParts = explode('_', $source_language_code);
            $source_language = $localeParts[0];
        }

        return $source_language;
    }

    /**
     * Particularities for target language in API
     *
     * @param $target_language
     * @param $source_language_code
     * @param $target_language_code
     * @return string
     */
    public function configure_api_target_language($target_language, $source_language_code, $target_language_code ){
        $exceptions_target_mapping_codes = array(
            'zh_HK' => 'zh-tw',
            'zh_TW' => 'zh-tw',
            'zh_CN' => 'zh-cn',
            'en_GB' => 'en',
            'en_US' => 'en',
            'en_CA' => 'en',
            'en_ZA' => 'en',
            'en_NZ' => 'en',
            'en_AU' => 'en',
        );
        if ( isset( $exceptions_target_mapping_codes[$target_language_code] ) ){
            $target_language = $exceptions_target_mapping_codes[$target_language_code];
        } else {
            $localeParts = explode('_', $target_language_code);
            $target_language = $localeParts[0];
        }

        return $target_language;
    }
}
