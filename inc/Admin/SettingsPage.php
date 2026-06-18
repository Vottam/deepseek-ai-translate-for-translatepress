<?php
/**
 * Admin settings page for AI Providers.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Admin
 */

namespace hollisho\translatepress\translate\deepseek\inc\Admin;

use hollisho\translatepress\translate\deepseek\inc\Settings\SettingsRepository;
use hollisho\translatepress\translate\deepseek\inc\Settings\SettingsSanitizer;

/**
 * Class SettingsPage
 *
 * Renders the AI Providers settings page in WordPress admin.
 */
class SettingsPage {

    /** @var SettingsRepository */
    private $settings;

    const NONCE_ACTION = 'ai_providers_for_translatepress_settings_nonce';
    const NONCE_NAME   = 'ai_providers_for_translatepress_nonce';
    const PAGE_SLUG    = 'ai-providers-for-translatepress';

    /**
     * SettingsPage constructor.
     */
    public function __construct() {
        $this->settings = new SettingsRepository();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Add menu page under Settings.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_options_page(
            __( 'AI Providers for TranslatePress', 'hollisho-integration-deepseek-for-translatepress' ),
            __( 'AI Providers', 'hollisho-integration-deepseek-for-translatepress' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register settings with WordPress Settings API.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'ai_providers_for_translatepress_group',
            SettingsRepository::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ SettingsSanitizer::class, 'sanitize' ],
                'default'           => SettingsRepository::DEFAULTS,
            ]
        );

        // Section: Provider Selection.
        add_settings_section(
            'ai_providers_section_provider',
            __( 'Provider Selection', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_provider_section' ],
            self::PAGE_SLUG
        );

        // Field: Active Provider.
        add_settings_field(
            'active_provider',
            __( 'Active Provider', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_provider_field' ],
            self::PAGE_SLUG,
            'ai_providers_section_provider'
        );

        // Section: OpenAI Settings.
        add_settings_section(
            'ai_providers_section_openai',
            __( 'OpenAI Settings', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_openai_section' ],
            self::PAGE_SLUG
        );

        // Field: OpenAI API Key.
        add_settings_field(
            'openai_api_key',
            __( 'OpenAI API Key', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_api_key_field' ],
            self::PAGE_SLUG,
            'ai_providers_section_openai'
        );

        // Field: OpenAI Model.
        add_settings_field(
            'openai_model',
            __( 'OpenAI Model', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_model_field' ],
            self::PAGE_SLUG,
            'ai_providers_section_openai'
        );

        // Field: OpenAI Timeout.
        add_settings_field(
            'openai_timeout',
            __( 'Timeout (seconds)', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_timeout_field' ],
            self::PAGE_SLUG,
            'ai_providers_section_openai'
        );

        // Field: Max Output Tokens.
        add_settings_field(
            'openai_max_tokens',
            __( 'Max Output Tokens', 'hollisho-integration-deepseek-for-translatepress' ),
            [ $this, 'render_max_tokens_field' ],
            self::PAGE_SLUG,
            'ai_providers_section_openai'
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'ai-providers-admin',
            plugins_url( 'assets/js/admin-settings.js', dirname( __DIR__, 2 ) . '/deepseek-ai-translate-for-translatepress.php' ),
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script( 'ai-providers-admin', 'aiProvidersAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ai_providers_validate_key' ),
            'i18n'    => [
                'validating'  => __( 'Validating...', 'hollisho-integration-deepseek-for-translatepress' ),
                'valid'       => __( 'API key is valid.', 'hollisho-integration-deepseek-for-translatepress' ),
                'invalid'     => __( 'API key is invalid.', 'hollisho-integration-deepseek-for-translatepress' ),
                'error'       => __( 'Validation error. Please try again.', 'hollisho-integration-deepseek-for-translatepress' ),
                'networkError'=> __( 'Network error. Please check your connection.', 'hollisho-integration-deepseek-for-translatepress' ),
            ],
        ] );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $all_settings = $this->settings->get_all();
        $is_openai_configured = $this->settings->is_openai_configured();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved.', 'hollisho-integration-deepseek-for-translatepress' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ai_providers_for_translatepress_group' );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Connection Test', 'hollisho-integration-deepseek-for-translatepress' ); ?></h2>
            <p>
                <button type="button" id="ai-providers-validate-key" class="button button-secondary">
                    <?php esc_html_e( 'Validate OpenAI Key', 'hollisho-integration-deepseek-for-translatepress' ); ?>
                </button>
                <span id="ai-providers-validation-result" style="margin-left: 10px;"></span>
            </p>
            <p class="description">
                <?php esc_html_e( 'This will send a minimal request to OpenAI to verify your API key.', 'hollisho-integration-deepseek-for-translatepress' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render provider section description.
     *
     * @return void
     */
    public function render_provider_section(): void {
        echo '<p>' . esc_html__( 'Select which AI provider to use for automatic translation.', 'hollisho-integration-deepseek-for-translatepress' ) . '</p>';
    }

    /**
     * Render provider selection field.
     *
     * @return void
     */
    public function render_provider_field(): void {
        $active = $this->settings->get_active_provider();
        ?>
        <fieldset>
            <label>
                <input type="radio" name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[active_provider]" value="deepseek" <?php checked( $active, 'deepseek' ); ?>>
                <?php esc_html_e( 'DeepSeek', 'hollisho-integration-deepseek-for-translatepress' ); ?>
            </label>
            <br>
            <label>
                <input type="radio" name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[active_provider]" value="openai" <?php checked( $active, 'openai' ); ?>>
                <?php esc_html_e( 'OpenAI', 'hollisho-integration-deepseek-for-translatepress' ); ?>
                <?php if ( ! $this->settings->is_openai_configured() ) : ?>
                    <span class="description"> (<?php esc_html_e( 'API key required', 'hollisho-integration-deepseek-for-translatepress' ); ?>)</span>
                <?php endif; ?>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Render OpenAI section description.
     *
     * @return void
     */
    public function render_openai_section(): void {
        echo '<p>' . esc_html__( 'Configure OpenAI API settings.', 'hollisho-integration-deepseek-for-translatepress' ) . '</p>';
    }

    /**
     * Render API key field — simple text input showing real value.
     *
     * @return void
     */
    public function render_api_key_field(): void {
        $api_key = $this->settings->get_openai_api_key();
        ?>
        <input type="text"
               id="openai_api_key"
               name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[openai_api_key]"
               value="<?php echo esc_attr( $api_key ); ?>"
               class="regular-text"
               autocomplete="off"
        >
        <p class="description">
            <?php esc_html_e( 'Enter your OpenAI API key. It will be stored securely.', 'hollisho-integration-deepseek-for-translatepress' ); ?>
        </p>
        <?php
    }

    /**
     * Render model selection field.
     *
     * @return void
     */
    public function render_model_field(): void {
        $model = $this->settings->get_openai_model();
        ?>
        <select name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[openai_model]">
            <?php foreach ( SettingsRepository::ALLOWED_MODELS as $model_id ) : ?>
                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>>
                    <?php echo esc_html( $model_id ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the OpenAI model to use for translation.', 'hollisho-integration-deepseek-for-translatepress' ); ?>
        </p>
        <?php
    }

    /**
     * Render timeout field.
     *
     * @return void
     */
    public function render_timeout_field(): void {
        $timeout = (int) $this->settings->get( 'openai_timeout', 60 );
        ?>
        <input type="number"
               name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[openai_timeout]"
               value="<?php echo esc_attr( $timeout ); ?>"
               min="<?php echo esc_attr( SettingsRepository::MIN_TIMEOUT ); ?>"
               max="<?php echo esc_attr( SettingsRepository::MAX_TIMEOUT ); ?>"
               step="1"
               class="small-text"
        >
        <p class="description">
            <?php
            printf(
                /* translators: 1: Minimum timeout, 2: Maximum timeout */
                esc_html__( 'Request timeout in seconds (%1$d-%2$d).', 'hollisho-integration-deepseek-for-translatepress' ),
                SettingsRepository::MIN_TIMEOUT,
                SettingsRepository::MAX_TIMEOUT
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render max output tokens field.
     *
     * @return void
     */
    public function render_max_tokens_field(): void {
        $max_tokens = (int) $this->settings->get( 'openai_max_tokens', 4096 );
        ?>
        <input type="number"
               name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[openai_max_tokens]"
               value="<?php echo esc_attr( $max_tokens ); ?>"
               min="<?php echo esc_attr( SettingsRepository::MIN_MAX_TOKENS ); ?>"
               max="<?php echo esc_attr( SettingsRepository::MAX_MAX_TOKENS ); ?>"
               step="1"
               class="small-text"
        >
        <p class="description">
            <?php
            printf(
                /* translators: 1: Minimum tokens, 2: Maximum tokens */
                esc_html__( 'Maximum output tokens (%1$d-%2$d).', 'hollisho-integration-deepseek-for-translatepress' ),
                SettingsRepository::MIN_MAX_TOKENS,
                SettingsRepository::MAX_MAX_TOKENS
            );
            ?>
        </p>
        <?php
    }
}
