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

        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'contact-form-7' ) ) {
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
        $config = [];

        preg_match_all( '/\[contact-form-7[^\]]*id=["\']?(\d+)["\']?/i', $content, $matches );

        foreach ( array_unique( $matches[1] ) as $form_id ) {
            $form_id            = (int) $form_id;
            $config[ $form_id ] = CFV_Config::get( $form_id );
        }

        return $config;
    }

    /**
     * Enqueue admin assets on the CF7 form edit screen.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( string $hook ): void {
        // Implemented in Task 5.
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        unset( $hook );
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
        // Implemented in Task 5.
        return $panels;
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    /**
     * AJAX handler: save validation config for a form.
     */
    public static function ajax_save_config(): void {
        // Implemented in Task 10.
    }

    /**
     * AJAX handler: load validation config for a form.
     */
    public static function ajax_get_config(): void {
        // Implemented in Task 10.
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
        // Implemented in Task 11.
        return $form_html;
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
        // Implemented in Task 17.
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        unset( $submission );
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
        // Implemented in Task 17.
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        unset( $result );
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
        // Implemented in Task 18.
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        unset( $post_id, $post, $update );
    }
}
