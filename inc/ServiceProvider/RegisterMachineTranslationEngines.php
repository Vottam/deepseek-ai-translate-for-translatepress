<?php
namespace hollisho\translatepress\translate\deepseek\inc\ServiceProvider;

use hollisho\translatepress\translate\deepseek\inc\Base\ServiceProviderInterface;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\DeepSeekTranslationEngine;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\OpenAITranslationEngine;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\OpenRouterTranslationEngine;
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
        $classes[OpenRouterTranslationEngine::ENGINE_KEY] = OpenRouterTranslationEngine::class;
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

        $engines[] = [
            'value' => OpenRouterTranslationEngine::ENGINE_KEY,
            'label' => esc_html(__('OpenRouter', 'hollisho-integration-deepseek-for-translatepress')),
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

        // Check for API errors when the selected engine matches.
        if ( DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine || OpenAITranslationEngine::ENGINE_KEY === $translation_engine || OpenRouterTranslationEngine::ENGINE_KEY === $translation_engine ) {
            $api_check = $machine_translator->check_api_key_validity();
        }

        if ( isset($api_check) && true === $api_check['error'] ) {
            $error_message = $api_check['message'];
            $show_errors    = true;
        }

        $text_input_classes = array(
            'trp-text-input',
        );
        if ( $show_errors ) {
            $text_input_classes[] = 'trp-text-input-error';
        }

        // Render BOTH engine blocks. TP JS (trp-back-end-script.js) toggles visibility
        // via .trp-engine class and #engine_key id on dropdown change.
        $this->render_deepseek_api_key_field(
            $settings,
            $text_input_classes,
            $show_errors,
            $error_message,
            $machine_translator
        );

        $this->render_openai_api_key_field( $settings, $machine_translator, $show_errors, $error_message );
        $this->render_openrouter_api_key_field( $settings, $machine_translator, $show_errors, $error_message );
    }

    /**
     * Render DeepSeek API key field — simple text input, shows real value.
     * Same pattern as the original upstream plugin.
     */
    private function render_deepseek_api_key_field(
        $settings,
        $text_input_classes,
        $show_errors,
        $error_message,
        $machine_translator
    ) {
        $field_key = DeepSeekTranslationEngine::FIELD_API_KEY;
        $api_key   = $settings[ $field_key ] ?? '';
        ?>
        <div class="trp-engine trp-automatic-translation-engine__container" id="<?php echo esc_attr( DeepSeekTranslationEngine::ENGINE_KEY ); ?>">
        <tr>
            <th scope="row">
                <?php echo esc_html(__('DeepSeek API key', 'hollisho-integration-deepseek-for-translatepress')); ?>
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
                <input type="text" id="trp-deepseek-api-key"
                       class="<?php echo esc_html( implode( ' ', $text_input_classes ) ); ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr( $field_key ); ?>]"
                       value="<?php echo esc_attr( $api_key ); ?>"
                       autocomplete="off"
                />
                <?php
                if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors );
                }
                ?>
                <p class="description">
                    <?php
                        $text = __( 'Visit <a href="%s" target="_blank">this link</a> to see how you can set up an API key and control API costs.', 'hollisho-integration-deepseek-for-translatepress' );
                        echo wp_kses( sprintf( $text, 'https://api-docs.deepseek.com/' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] )
                    ?>
                </p>
            </td>
        </tr>
        </div>
        <?php
    }

    /**
     * Render OpenAI API key field — simple text input, shows real value.
     * Same pattern as DeepSeek field.
     */
    private function render_openai_api_key_field( $settings, $machine_translator, $show_errors = false, $error_message = '' ) {
        $field_key = OpenAITranslationEngine::FIELD_API_KEY;
        $api_key   = $settings[ $field_key ] ?? '';
        ?>
        <div class="trp-engine trp-automatic-translation-engine__container" id="<?php echo esc_attr( OpenAITranslationEngine::ENGINE_KEY ); ?>">
        <tr>
            <th scope="row">
                <?php esc_html_e( 'OpenAI API key', 'hollisho-integration-deepseek-for-translatepress' ); ?>
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
                <input type="text"
                       id="trp-openai-api-key"
                       class="trp-text-input<?php echo $show_errors ? ' trp-text-input-error' : ''; ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr( $field_key ); ?>]"
                       value="<?php echo esc_attr( $api_key ); ?>"
                       autocomplete="off"
                />
                <?php
                if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors );
                }
                ?>
                <p class="description">
                    <?php
                        $text = __( 'Visit <a href="%s" target="_blank">this link</a> to get your OpenAI API key.', 'hollisho-integration-deepseek-for-translatepress' );
                        echo wp_kses( sprintf( $text, 'https://platform.openai.com/api-keys' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] );
                    ?>
                </p>
            </td>
        </tr>
        </div>
        <?php
    }

    /**
     * Render OpenRouter API key and model fields.
     */
    private function render_openrouter_api_key_field( $settings, $machine_translator, $show_errors = false, $error_message = '' ) {
        $field_key    = OpenRouterTranslationEngine::FIELD_API_KEY;
        $field_model  = OpenRouterTranslationEngine::FIELD_MODEL;
        $api_key      = $settings[ $field_key ] ?? '';
        $model        = $settings[ $field_model ] ?? '';
        ?>
        <div class="trp-engine trp-automatic-translation-engine__container" id="<?php echo esc_attr( OpenRouterTranslationEngine::ENGINE_KEY ); ?>">
        <tr>
            <th scope="row">
                <?php esc_html_e( 'OpenRouter API key', 'hollisho-integration-deepseek-for-translatepress' ); ?>
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
                <input type="text"
                       id="trp-openrouter-api-key"
                       class="trp-text-input<?php echo $show_errors ? ' trp-text-input-error' : ''; ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr( $field_key ); ?>]"
                       value="<?php echo esc_attr( $api_key ); ?>"
                       autocomplete="off"
                />
                <?php
                if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors );
                }
                ?>
                <p class="description">
                    <?php
                        $text = __( 'Visit <a href="%s" target="_blank">this link</a> to get your OpenRouter API key.', 'hollisho-integration-deepseek-for-translatepress' );
                        echo wp_kses( sprintf( $text, 'https://openrouter.ai/keys' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] )
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php esc_html_e( 'OpenRouter model', 'hollisho-integration-deepseek-for-translatepress' ); ?>
            </th>
            <td>
                <input type="text"
                       id="trp-openrouter-model"
                       class="trp-text-input"
                       name="trp_machine_translation_settings[<?php echo esc_attr( $field_model ); ?>]"
                       value="<?php echo esc_attr( $model ); ?>"
                       placeholder="openai/gpt-4o-mini"
                       autocomplete="off"
                />
                <p class="description">
                    <?php esc_html_e( 'Enter the OpenRouter model slug (e.g., openai/gpt-4o-mini, anthropic/claude-3-haiku).', 'hollisho-integration-deepseek-for-translatepress' ); ?>
                </p>
            </td>
        </tr>
        </div>
        <?php
    }

    public function sanitize_settings( $settings, $mt_settings ){
        // Preserve existing API keys when fields are not present in POST
        // (e.g., when switching engines, hidden fields are not submitted).
        // Follows the same pattern as TranslatePress core (class-machine-translation-tab.php).
        $existing = get_option( 'trp_machine_translation_settings', [] );

        // DeepSeek API key: save only if non-empty in POST, otherwise preserve existing.
        if( !empty( $mt_settings[DeepSeekTranslationEngine::FIELD_API_KEY] ) )
            $settings[DeepSeekTranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[DeepSeekTranslationEngine::FIELD_API_KEY] );
        elseif( isset( $existing[DeepSeekTranslationEngine::FIELD_API_KEY] ) )
            $settings[DeepSeekTranslationEngine::FIELD_API_KEY] = $existing[DeepSeekTranslationEngine::FIELD_API_KEY];

        // OpenAI API key: save only if non-empty in POST, otherwise preserve existing.
        if( !empty( $mt_settings[OpenAITranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenAITranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[OpenAITranslationEngine::FIELD_API_KEY] );
        elseif( isset( $existing[OpenAITranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenAITranslationEngine::FIELD_API_KEY] = $existing[OpenAITranslationEngine::FIELD_API_KEY];

        // OpenRouter API key: save only if non-empty in POST, otherwise preserve existing.
        if( !empty( $mt_settings[OpenRouterTranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenRouterTranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[OpenRouterTranslationEngine::FIELD_API_KEY] );
        elseif( isset( $existing[OpenRouterTranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenRouterTranslationEngine::FIELD_API_KEY] = $existing[OpenRouterTranslationEngine::FIELD_API_KEY];

        // OpenRouter model: save only if non-empty in POST, otherwise preserve existing.
        if( !empty( $mt_settings[OpenRouterTranslationEngine::FIELD_MODEL] ) )
            $settings[OpenRouterTranslationEngine::FIELD_MODEL] = sanitize_text_field( $mt_settings[OpenRouterTranslationEngine::FIELD_MODEL] );
        elseif( isset( $existing[OpenRouterTranslationEngine::FIELD_MODEL] ) )
            $settings[OpenRouterTranslationEngine::FIELD_MODEL] = $existing[OpenRouterTranslationEngine::FIELD_MODEL];

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
