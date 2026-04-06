<?php
/**
 * Hooks registration for CF7 Validate Pro.
 *
 * Registers all WordPress and CF7 action/filter hooks. Callbacks are
 * fully implemented here or delegated to specialist classes.
 *
 * @package CF7_Validate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFV_Hooks {

    /**
     * Stores server-side validation errors between validate_submission and override_error_response.
     *
     * @var array<string, string>
     */
    private static array $validation_errors = [];

    /**
     * Register all WP/CF7 hooks.
     */
    public static function init(): void {
        // Assets.
        add_action( 'wp_enqueue_scripts',   [ __CLASS__, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // CF7 editor tab.
        add_filter( 'wpcf7_editor_panels', [ __CLASS__, 'register_validation_panel' ] );

        // AJAX: save/load validation config from the Validation tab.
        add_action( 'wp_ajax_cfv_save_config', [ __CLASS__, 'ajax_save_config' ] );
        add_action( 'wp_ajax_cfv_get_config',  [ __CLASS__, 'ajax_get_config' ] );

        // Form HTML decoration (asterisks, optional labels, error spans, counters).
        add_filter( 'wpcf7_form_elements', [ __CLASS__, 'decorate_form_elements' ] );

        // Server-side validation — intercept before CF7 sends email.
        add_filter( 'wpcf7_before_send_mail', [ __CLASS__, 'validate_submission' ], 10, 3 );

        // Override CF7 AJAX error response with our field-specific messages.
        add_filter( 'wpcf7_ajax_json_echo', [ __CLASS__, 'override_error_response' ], 10, 2 );

        // Form duplication — copy validation meta to the new form.
        add_action( 'wp_insert_post', [ __CLASS__, 'maybe_copy_config_on_duplicate' ], 10, 3 );
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * Enqueue frontend validation assets on pages containing a CF7 shortcode.
     */
    public static function enqueue_frontend_assets(): void {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $content = $post->post_content;
        $has_cf7 = has_shortcode( $content, 'contact-form-7' )
                || strpos( $content, 'wp:contact-form-7' ) !== false;

        if ( ! $has_cf7 ) {
            return;
        }

        wp_enqueue_style(
            'cfv-styles',
            CFV_PLUGIN_URL . 'assets/css/cfv-styles.css',
            [],
            CFV_VERSION
        );

        wp_enqueue_script(
            'cfv-counter',
            CFV_PLUGIN_URL . 'assets/js/cfv-counter.js',
            [],
            CFV_VERSION,
            true
        );

        wp_enqueue_script(
            'cfv-validation',
            CFV_PLUGIN_URL . 'assets/js/cfv-validation.js',
            [ 'cfv-counter' ],
            CFV_VERSION,
            true
        );

        // Pass per-form config and helpers to JS.
        $forms_config = self::collect_page_forms_config( $post->post_content );

        // Enqueue intl-tel-input if any form on the page has a phone field with enable_intl.
        $needs_intl = false;
        foreach ( $forms_config as $fid => $fc ) {
            foreach ( $fc['fields'] ?? [] as $field ) {
                if ( ! empty( $field['enable_intl'] ) ) {
                    $needs_intl = true;
                    break 2;
                }
            }
        }

        if ( $needs_intl ) {
            wp_enqueue_style(
                'intl-tel-input',
                CFV_PLUGIN_URL . 'assets/vendor/intl-tel-input/intlTelInput.min.css',
                [],
                '18.0.0'
            );
            wp_enqueue_script(
                'intl-tel-input',
                CFV_PLUGIN_URL . 'assets/vendor/intl-tel-input/intlTelInput.min.js',
                [],
                '18.0.0',
                true
            );
            wp_enqueue_script(
                'cfv-intl-phone',
                CFV_PLUGIN_URL . 'assets/js/cfv-intl-phone.js',
                [ 'intl-tel-input', 'cfv-validation' ],
                CFV_VERSION,
                true
            );
        }

        wp_localize_script( 'cfv-validation', 'cfvConfig', [
            'forms'   => $forms_config,
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    /**
     * Collect validation config for every CF7 form shortcode on the page.
     *
     * @param string $content Post content to scan.
     * @return array<int, array> Form ID → config array.
     */
    private static function collect_page_forms_config( string $content ): array {
        $config  = [];
        $all_ids = [];

        // Classic editor / shortcode: [contact-form-7 id="123" ...]
        preg_match_all( '/\[contact-form-7[^\]]*id=["\']?(\d+)["\']?/i', $content, $sc_matches );
        foreach ( $sc_matches[1] ?? [] as $id ) {
            $all_ids[] = (int) $id;
        }

        // Block editor: <!-- wp:contact-form-7/... {"id":123,...} /-->
        preg_match_all( '/wp:contact-form-7[^{]*\{"id":(\d+)/i', $content, $block_matches );
        foreach ( $block_matches[1] ?? [] as $id ) {
            $all_ids[] = (int) $id;
        }

        foreach ( array_unique( $all_ids ) as $form_id ) {
            if ( $form_id > 0 ) {
                $config[ $form_id ] = CFV_Config::get( $form_id );
            }
        }

        return $config;
    }

    /**
     * Enqueue admin assets on the CF7 form edit screen.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( string $hook ): void {
        // Only enqueue on the CF7 form edit screen.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['page'] ) || 'wpcf7' !== $_GET['page'] ) {
            return;
        }

        $asset_file = CFV_PLUGIN_DIR . 'build/validation-tab/index.asset.php';
        if ( ! file_exists( $asset_file ) ) {
            return; // Build has not been run yet.
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'cfv-validation-tab',
            CFV_PLUGIN_URL . 'build/validation-tab/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // CSS may not exist if the entry has no styles yet — guard it.
        $css_file = CFV_PLUGIN_DIR . 'build/validation-tab/index.css';
        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'cfv-validation-tab-style',
                CFV_PLUGIN_URL . 'build/validation-tab/index.css',
                [ 'wp-components' ],
                $asset['version']
            );
        }

        wp_localize_script( 'cfv-validation-tab', 'cfvAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cfv_admin' ),
        ] );
    }

    // =========================================================================
    // CF7 Editor Panel
    // =========================================================================

    /**
     * Register the Validation tab in the CF7 form editor.
     *
     * @param array $panels Existing editor panels.
     * @return array Panels with Validation tab added.
     */
    public static function register_validation_panel( array $panels ): array {
        $panels['validation'] = [
            'title'    => __( 'Validation', 'cf7-validate-pro' ),
            'callback' => [ __CLASS__, 'render_validation_panel' ],
        ];
        return $panels;
    }

    /**
     * Render the Validation tab panel HTML (React mounts into this div).
     *
     * @param WPCF7_ContactForm $contact_form The current CF7 form.
     */
    public static function render_validation_panel( WPCF7_ContactForm $contact_form ): void {
        echo '<div id="cfv-validation-tab" data-form-id="' . esc_attr( $contact_form->id() ) . '"></div>';
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    /**
     * AJAX handler: save validation config for a form.
     */
    public static function ajax_save_config(): void {
        check_ajax_referer( 'cfv_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $form_id     = absint( $_POST['form_id'] ?? 0 );
        $config_json = wp_unslash( $_POST['config'] ?? '' );

        if ( ! $form_id || ! $config_json ) {
            wp_send_json_error( 'Invalid data.' );
        }

        $config = json_decode( $config_json, true );
        if ( ! is_array( $config ) ) {
            wp_send_json_error( 'Invalid JSON.' );
        }

        CFV_Config::save( $form_id, $config );
        wp_send_json_success( 'Saved.' );
    }

    /**
     * AJAX handler: load validation config for a form.
     */
    public static function ajax_get_config(): void {
        check_ajax_referer( 'cfv_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $form_id = absint( $_POST['form_id'] ?? 0 );
        if ( ! $form_id ) {
            wp_send_json_error( 'Invalid form ID.' );
        }

        wp_send_json_success( CFV_Config::get( $form_id ) );
    }

    // =========================================================================
    // Form HTML Decoration
    // =========================================================================

    /**
     * Post-process rendered CF7 form HTML to inject validation UX elements.
     *
     * @param string $form_html Rendered form HTML.
     * @return string Modified form HTML.
     */
    public static function decorate_form_elements( string $form_html ): string {
        $form = WPCF7_ContactForm::get_current();
        if ( ! $form ) {
            return $form_html;
        }
        return CFV_Field_Decorator::decorate( $form_html, $form->id() );
    }

    // =========================================================================
    // Server-side Validation
    // =========================================================================

    /**
     * Validate the CF7 submission before the email is sent.
     *
     * Sets $abort = true if validation fails, storing errors for the response override.
     *
     * @param WPCF7_ContactForm $contact_form The contact form object.
     * @param bool              $abort        By-reference flag; set to true to abort send.
     * @param WPCF7_Submission  $submission   The submission object.
     * @return WPCF7_ContactForm Unchanged contact form.
     */
    public static function validate_submission( WPCF7_ContactForm $contact_form, &$abort, WPCF7_Submission $submission ): WPCF7_ContactForm {
        $form_id = $contact_form->id();
        $posted  = $submission->get_posted_data();
        $files   = $_FILES; // phpcs:ignore WordPress.Security.NonceVerification

        $errors = CFV_Validator::validate( $form_id, $posted, $files );

        if ( ! empty( $errors ) ) {
            $abort = true;
            self::$validation_errors = $errors;
        }

        return $contact_form;
    }

    // =========================================================================
    // Error Response Override
    // =========================================================================

    /**
     * Inject field-specific error messages into CF7's AJAX JSON response.
     *
     * @param array $response The outgoing JSON response array.
     * @param array $result   CF7 result data (unused here).
     * @return array Modified response.
     */
    public static function override_error_response( array $response, array $result ): array {
        if ( empty( self::$validation_errors ) ) {
            return $response;
        }

        $response['status']  = 'validation_failed';
        $response['message'] = __( 'Please correct the errors below.', 'cf7-validate-pro' );

        // Build invalid-fields array in the format CF7 expects.
        $invalid_fields = [];
        foreach ( self::$validation_errors as $field_name => $message ) {
            $invalid_fields[] = [
                'field'   => $field_name,
                'message' => $message,
                'idref'   => null,
            ];
        }
        $response['invalid_fields'] = $invalid_fields;

        // Reset for next submission.
        self::$validation_errors = [];

        return $response;
    }

    // =========================================================================
    // Form Duplication
    // =========================================================================

    /**
     * Copy validation config to a newly duplicated CF7 form.
     *
     * @param int     $post_id The inserted post ID.
     * @param WP_Post $post    The inserted post object.
     * @param bool    $update  Whether this is an update (true) or insert (false).
     */
    public static function maybe_copy_config_on_duplicate( int $post_id, WP_Post $post, bool $update ): void {
        // Only act on CF7 form posts being inserted (not updated).
        if ( $update || $post->post_type !== 'wpcf7_contact_form' ) {
            return;
        }

        // Check for Duplicate Post plugin's original post reference.
        $original_id = (int) get_post_meta( $post_id, '_dp_original', true );

        // Also check WPCF7's own copy mechanism (it stores _copy_from in some versions).
        if ( ! $original_id ) {
            $original_id = (int) get_post_meta( $post_id, '_wpcf7_copy_from', true );
        }

        if ( ! $original_id ) {
            return;
        }

        CFV_Config::copy( $original_id, $post_id );
    }
}
