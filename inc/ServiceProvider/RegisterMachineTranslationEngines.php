<?php
namespace hollisho\translatepress\translate\deepseek\inc\ServiceProvider;

use hollisho\translatepress\translate\deepseek\inc\Base\ServiceProviderInterface;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\DeepSeekTranslationEngine;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\OpenAITranslationEngine;
use hollisho\translatepress\translate\deepseek\inc\TranslationEngines\OpenRouterTranslationEngine;
use TRP_Translate_Press;

/**
 * @author Hollis
 * @desc yodao machine translation engine service provider
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
        $classes[OpenAITranslationEngine::ENGINE_KEY] = OpenAITranslationEngine::class;
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

        // Check for API errors.
        if ( DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
            $trp = TRP_Translate_Press::get_trp_instance();
            $machine_translator = $trp->get_component( 'machine_translator' );
            $api_check = $machine_translator->check_api_key_validity();
        } elseif ( OpenAITranslationEngine::ENGINE_KEY === $translation_engine ) {
            $trp = TRP_Translate_Press::get_trp_instance();
            $machine_translator = $trp->get_component( 'machine_translator' );
            $api_check = $machine_translator->check_api_key_validity();
        } elseif ( OpenRouterTranslationEngine::ENGINE_KEY === $translation_engine ) {
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
        if ( $show_errors && DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
            $text_input_classes[] = 'trp-text-input-error';
        } elseif ( $show_errors && OpenAITranslationEngine::ENGINE_KEY === $translation_engine ) {
            $text_input_classes[] = 'trp-text-input-error';
        } elseif ( $show_errors && OpenRouterTranslationEngine::ENGINE_KEY === $translation_engine ) {
            $text_input_classes[] = 'trp-text-input-error';
        }

        ?>

        <tr>
            <th scope="row">
                <?php 
                    // translators: input api key
                    echo esc_html(__('deepseek api key', 'hollisho-integration-deepseek-for-translatepress'));
                ?>
            </th>
            <td>
                <?php
                // Display an error message above the input.
                if ( $show_errors && DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
                    ?>
                    <p class="trp-error-inline">
                        <?php echo wp_kses_post( $error_message ); ?>
                    </p>
                    <?php
                }
                ?>
                <input type="text" id="trp-deepseek-api-key" class="<?php echo esc_html( implode( ' ', $text_input_classes ) ); ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr(DeepSeekTranslationEngine::FIELD_API_KEY) ?>]"
                       value="<?php if( !empty( $settings[DeepSeekTranslationEngine::FIELD_API_KEY] ) ) echo esc_attr( $settings[DeepSeekTranslationEngine::FIELD_API_KEY]); ?>"/>
                <?php
                // Show error or success SVG.
                if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) && DeepSeekTranslationEngine::ENGINE_KEY === $translation_engine ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors );
                }
                ?>
                <p class="description">
                    <?php 
                        // translators: visit deepseek api url.
                        $text = __( 'Visit <a href="%s" target="_blank">this link</a> to see how you can set up an API key and control API costs.', 'hollisho-integration-deepseek-for-translatepress' );
                        echo wp_kses( sprintf( $text, 'https://api-docs.deepseek.com/' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] )
                    ?>
                </p>
            </td>

        </tr>

        <!-- OpenAI settings -->
        <?php
        $show_errors_oai   = false;
        $error_message_oai = '';
        if ( OpenAITranslationEngine::ENGINE_KEY === $translation_engine ) {
            $api_check_oai = $machine_translator->check_api_key_validity();
            if ( isset($api_check_oai) && true === $api_check_oai['error'] ) {
                $error_message_oai = $api_check_oai['message'];
                $show_errors_oai    = true;
            }
        }
        $text_input_classes_oai = array('trp-text-input');
        if ( $show_errors_oai ) {
            $text_input_classes_oai[] = 'trp-text-input-error';
        }
        ?>
        <tr class="trp-engine" data-trp-openai-engine="<?php echo esc_attr(OpenAITranslationEngine::ENGINE_KEY); ?>">
            <th scope="row">
                <?php echo esc_html(__('OpenAI API key', 'hollisho-integration-deepseek-for-translatepress')); ?>
            </th>
            <td>
                <?php if ( $show_errors_oai ) { ?>
                    <p class="trp-error-inline">
                        <?php echo wp_kses_post( $error_message_oai ); ?>
                    </p>
                <?php } ?>
                <input type="text" id="trp-openai-api-key"
                       class="<?php echo esc_html( implode( ' ', $text_input_classes_oai ) ); ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr(OpenAITranslationEngine::FIELD_API_KEY); ?>]"
                       value="<?php if( !empty( $settings[OpenAITranslationEngine::FIELD_API_KEY] ) ) echo esc_attr( $settings[OpenAITranslationEngine::FIELD_API_KEY] ); ?>"/>
                <?php if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) && OpenAITranslationEngine::ENGINE_KEY === $translation_engine ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors_oai );
                } ?>
                <p class="description">
                    <?php
                    $text_oai = __( 'Visit <a href="%s" target="_blank">this link</a> to get your OpenAI API key.', 'hollisho-integration-deepseek-for-translatepress' );
                    echo wp_kses( sprintf( $text_oai, 'https://platform.openai.com/api-keys' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] );
                    ?>
                </p>
            </td>
        </tr>
        <tr class="trp-engine" data-trp-openai-engine="<?php echo esc_attr(OpenAITranslationEngine::ENGINE_KEY); ?>">
            <th scope="row">
                <?php echo esc_html(__('OpenAI model', 'hollisho-integration-deepseek-for-translatepress')); ?>
            </th>
            <td>
                <input type="text" id="trp-openai-model"
                       class="trp-text-input"
                       name="trp_machine_translation_settings[<?php echo esc_attr(OpenAITranslationEngine::FIELD_MODEL); ?>]"
                       value="<?php if( !empty( $settings[OpenAITranslationEngine::FIELD_MODEL] ) ) echo esc_attr( $settings[OpenAITranslationEngine::FIELD_MODEL] ); ?>"
                       placeholder="gpt-5.4-mini"/>
                <p class="description">
                    <?php echo esc_html__('Default: gpt-5.4-mini', 'hollisho-integration-deepseek-for-translatepress'); ?>
                </p>
            </td>
        </tr>

        <!-- OpenRouter settings -->
        <?php
        $show_errors_or   = false;
        $error_message_or = '';
        if ( OpenRouterTranslationEngine::ENGINE_KEY === $translation_engine ) {
            $api_check_or = $machine_translator->check_api_key_validity();
            if ( isset($api_check_or) && true === $api_check_or['error'] ) {
                $error_message_or = $api_check_or['message'];
                $show_errors_or    = true;
            }
        }
        $text_input_classes_or = array('trp-text-input');
        if ( $show_errors_or ) {
            $text_input_classes_or[] = 'trp-text-input-error';
        }
        ?>
        <tr class="trp-engine" data-trp-openrouter-engine="<?php echo esc_attr(OpenRouterTranslationEngine::ENGINE_KEY); ?>">
            <th scope="row">
                <?php echo esc_html(__('OpenRouter API key', 'hollisho-integration-deepseek-for-translatepress')); ?>
            </th>
            <td>
                <?php if ( $show_errors_or ) { ?>
                    <p class="trp-error-inline">
                        <?php echo wp_kses_post( $error_message_or ); ?>
                    </p>
                <?php } ?>
                <input type="text" id="trp-openrouter-api-key"
                       class="<?php echo esc_html( implode( ' ', $text_input_classes_or ) ); ?>"
                       name="trp_machine_translation_settings[<?php echo esc_attr(OpenRouterTranslationEngine::FIELD_API_KEY); ?>]"
                       value="<?php if( !empty( $settings[OpenRouterTranslationEngine::FIELD_API_KEY] ) ) echo esc_attr( $settings[OpenRouterTranslationEngine::FIELD_API_KEY] ); ?>"/>
                <?php if ( method_exists( $machine_translator, 'automatic_translation_svg_output' ) && OpenRouterTranslationEngine::ENGINE_KEY === $translation_engine ) {
                    $machine_translator->automatic_translation_svg_output( $show_errors_or );
                } ?>
                <p class="description">
                    <?php
                    $text_or = __( 'Visit <a href="%s" target="_blank">this link</a> to get your OpenRouter API key.', 'hollisho-integration-deepseek-for-translatepress' );
                    echo wp_kses( sprintf( $text_or, 'https://openrouter.ai/keys' ), [ 'a' => [ 'href' => [], 'target'=> [] ] ] );
                    ?>
                </p>
            </td>
        </tr>
        <tr class="trp-engine" data-trp-openrouter-engine="<?php echo esc_attr(OpenRouterTranslationEngine::ENGINE_KEY); ?>">
            <th scope="row">
                <?php echo esc_html(__('OpenRouter model', 'hollisho-integration-deepseek-for-translatepress')); ?>
            </th>
            <td>
                <input type="text" id="trp-openrouter-model"
                       class="trp-text-input"
                       name="trp_machine_translation_settings[<?php echo esc_attr(OpenRouterTranslationEngine::FIELD_MODEL); ?>]"
                       value="<?php if( !empty( $settings[OpenRouterTranslationEngine::FIELD_MODEL] ) ) echo esc_attr( $settings[OpenRouterTranslationEngine::FIELD_MODEL] ); ?>"
                       placeholder="openai/gpt-4o-mini"/>
                <p class="description">
                    <?php echo esc_html__('Default: openai/gpt-4o-mini', 'hollisho-integration-deepseek-for-translatepress'); ?>
                </p>
            </td>
        </tr>

        <?php
    }

    public function sanitize_settings( $settings, $mt_settings ){
        if( !empty( $mt_settings[DeepSeekTranslationEngine::FIELD_API_KEY] ) )
            $settings[DeepSeekTranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[DeepSeekTranslationEngine::FIELD_API_KEY] );
        elseif( isset( $settings[DeepSeekTranslationEngine::FIELD_API_KEY] ) ) {
            // Preserve existing key when field is empty in POST
        } else {
            // No existing key, nothing to preserve
        }

        if( !empty( $mt_settings[OpenAITranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenAITranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[OpenAITranslationEngine::FIELD_API_KEY] );
        elseif( isset( $settings[OpenAITranslationEngine::FIELD_API_KEY] ) ) {
            // Preserve existing key when field is empty in POST
        }

        if( !empty( $mt_settings[OpenAITranslationEngine::FIELD_MODEL] ) )
            $settings[OpenAITranslationEngine::FIELD_MODEL] = sanitize_text_field( $mt_settings[OpenAITranslationEngine::FIELD_MODEL] );

        if( !empty( $mt_settings[OpenRouterTranslationEngine::FIELD_API_KEY] ) )
            $settings[OpenRouterTranslationEngine::FIELD_API_KEY] = sanitize_text_field( $mt_settings[OpenRouterTranslationEngine::FIELD_API_KEY] );
        elseif( isset( $settings[OpenRouterTranslationEngine::FIELD_API_KEY] ) ) {
            // Preserve existing key when field is empty in POST
        }

        if( !empty( $mt_settings[OpenRouterTranslationEngine::FIELD_MODEL] ) )
            $settings[OpenRouterTranslationEngine::FIELD_MODEL] = sanitize_text_field( $mt_settings[OpenRouterTranslationEngine::FIELD_MODEL] );

        return $settings;
    }

    /**
     * Particularities for source language in API.
     *
     * PT_BR is not treated in the same way as for the target language
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