# CF7 Validate Pro Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build CF7 Validate Pro — a standalone WordPress addon plugin by Shon that adds comprehensive client-side (JS) and server-side (PHP) validation to Contact Form 7 forms, configurable via a custom React-powered Validation tab in the CF7 form editor.

**Architecture:** Independent validation layer on top of CF7. A PHP config class reads/writes per-form rules stored as post meta (`_cfv_validation_config`). A React tab in the CF7 editor lets admins configure rules per field. A JS validation engine and a PHP validator both consume the same config — JS via `wp_localize_script`, PHP via post meta — providing real-time client-side and server-side validation without modifying CF7 core.

**Tech Stack:** PHP 7.4+, WordPress 5.9+, Contact Form 7 5.x, React 18 via `@wordpress/scripts`, intl-tel-input 18.x (vendored), vanilla JS (no jQuery dependency for validation), CSS3

---

## File Map

| File | Responsibility |
|---|---|
| `cf7-validate-pro.php` | Plugin header, constants, CF7 dependency check, class autoload, boot |
| `uninstall.php` | Delete all `_cfv_validation_config` post meta on uninstall |
| `includes/class-cfv-config.php` | Read/write/merge validation config from post meta; field label auto-gen; per-type defaults |
| `includes/class-cfv-hooks.php` | Register all WP/CF7 hooks; enqueue assets; AJAX handlers; duplication copy |
| `includes/class-cfv-field-decorator.php` | Post-process CF7 form HTML: inject asterisks, optional labels, error spans, counter elements |
| `includes/class-cfv-validator.php` | All PHP validation rules; returns field-keyed error array |
| `src/validation-tab/index.js` | React entry point; mounts `<ValidationTab>` into CF7 editor panel |
| `src/validation-tab/components/ValidationTab.js` | Root tab: loads config, renders GlobalSettings + FieldRuleRow list, saves via AJAX |
| `src/validation-tab/components/GlobalSettings.js` | Global toggle: show optional labels on/off |
| `src/validation-tab/components/FieldRuleRow.js` | Per-field row: field label input + contextual RuleInputs |
| `src/validation-tab/components/RuleInputs/TextRules.js` | Rule inputs for text fields |
| `src/validation-tab/components/RuleInputs/NameRules.js` | Rule inputs for name fields (first/last name) |
| `src/validation-tab/components/RuleInputs/EmailRules.js` | Rule inputs for email fields |
| `src/validation-tab/components/RuleInputs/PhoneRules.js` | Rule inputs for phone fields (includes intl toggle) |
| `src/validation-tab/components/RuleInputs/NumericRules.js` | Rule inputs for number fields |
| `src/validation-tab/components/RuleInputs/TextareaRules.js` | Rule inputs for textarea fields |
| `src/validation-tab/components/RuleInputs/SelectRules.js` | Rule inputs for select/dropdown fields |
| `src/validation-tab/components/RuleInputs/CheckboxRules.js` | Rule inputs for checkbox/radio fields |
| `src/validation-tab/components/RuleInputs/FileRules.js` | Rule inputs for file upload fields |
| `src/validation-tab/hooks/useFormFields.js` | Parses CF7 form body textarea to extract field names and types |
| `assets/js/cfv-validation.js` | Client-side validation engine: event listeners, rule execution, error display, submit button |
| `assets/js/cfv-counter.js` | Character counter: init, live update, colour change |
| `assets/js/cfv-intl-phone.js` | intl-tel-input init, hidden field injection, reset, iti.getNumber() integration |
| `assets/css/cfv-styles.css` | All plugin styles: errors, asterisk, optional label, counter, disabled states |
| `assets/vendor/intl-tel-input/intlTelInput.min.js` | Vendored intl-tel-input JS |
| `assets/vendor/intl-tel-input/intlTelInput.min.css` | Vendored intl-tel-input CSS |
| `assets/vendor/intl-tel-input/flags.webp` | Vendored flag sprites |
| `package.json` | @wordpress/scripts build config |

---

## Task 1: Plugin Bootstrap

**Files:**
- Create: `cf7-validate-pro.php`
- Create: `uninstall.php`

- [ ] **Step 1: Create the main plugin file**

```php
<?php
/**
 * Plugin Name:       CF7 Validate Pro
 * Plugin URI:        https://github.com/shon/cf7-validate-pro
 * Description:       Comprehensive validation addon for Contact Form 7.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Shon
 * Text Domain:       cf7-validate-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFV_VERSION', '1.0.0' );
define( 'CFV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check that CF7 is active. If not, deactivate this plugin and show a notice.
 */
function cfv_check_cf7_dependency(): void {
    if ( ! function_exists( 'wpcf7' ) ) {
        deactivate_plugins( CFV_PLUGIN_BASENAME );
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__( 'CF7 Validation Addon requires Contact Form 7 to be installed and active.', 'cf7-validate-pro' )
                . '</p></div>';
        } );
        // Prevent hooks from loading.
        return;
    }

    // CF7 is active — load the plugin.
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-config.php';
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-field-decorator.php';
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-validator.php';
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-hooks.php';

    CFV_Hooks::init();
}
add_action( 'plugins_loaded', 'cfv_check_cf7_dependency' );
```

- [ ] **Step 2: Create uninstall.php**

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete all validation config stored on CF7 form posts.
$wpdb->delete(
    $wpdb->postmeta,
    [ 'meta_key' => '_cfv_validation_config' ],
    [ '%s' ]
);
```

- [ ] **Step 3: Verify plugin appears in WP admin**

Open WP admin → Plugins. Confirm "CF7 Validation Addon" appears in the list. Activate it. With CF7 active, it should activate cleanly with no errors. Check WP debug log at `wp-content/debug.log` (enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php` first).

- [ ] **Step 4: Test CF7 missing notice**

Deactivate CF7. Reload WP admin. Confirm the plugin auto-deactivates and shows the "requires CF7" admin notice. Re-activate CF7 and the plugin.

- [ ] **Step 5: Commit**

```bash
git add cf7-validate-pro.php uninstall.php
git commit -m "feat: plugin bootstrap with CF7 dependency check"
```

---

## Task 2: Config Class

**Files:**
- Create: `includes/class-cfv-config.php`

- [ ] **Step 1: Create the config class**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFV_Config {

    const META_KEY = '_cfv_validation_config';

    /**
     * Global defaults applied to all forms unless overridden.
     */
    public static function get_global_defaults(): array {
        return [
            'show_optional_label' => false,
        ];
    }

    /**
     * Per-type field defaults. Keyed by field type string.
     */
    public static function get_field_type_defaults( string $type ): array {
        $defaults = [
            'text' => [
                'required'                   => false,
                'min_length'                 => '',
                'max_length'                 => '',
                'alpha_only'                 => false,
                'no_leading_spaces'          => false,
                'allow_special_chars'        => true,
                'allow_emoji'                => true,
                'collapse_whitespace'        => true,
                'input_mask'                 => '',
                'counter_format'             => 'off',
            ],
            'name' => [
                'required'                   => false,
                'min_length'                 => 2,
                'max_length'                 => 56,
                'alpha_only'                 => true,
                'no_leading_spaces'          => true,
                'allow_special_chars'        => false,
                'allow_emoji'                => false,
                'collapse_whitespace'        => true,
                'input_mask'                 => '',
                'counter_format'             => 'off',
            ],
            'email' => [
                'required'                   => false,
                'trim_whitespace'            => true,
            ],
            'tel' => [
                'required'                   => false,
                'min_length'                 => 7,
                'max_length'                 => 15,
                'enable_intl'                => false,
                'default_country'            => 'auto',
            ],
            'number' => [
                'required'                   => false,
                'min_value'                  => '',
                'max_value'                  => '',
                'allow_negative'             => false,
                'allow_zero'                 => true,
            ],
            'url' => [
                'required'                   => false,
            ],
            'textarea' => [
                'required'                   => false,
                'max_length'                 => 1500,
                'counter_format'             => 'count/max',
                'max_height'                 => 200,
                'security_sanitize'          => true,
            ],
            'select' => [
                'required'                   => false,
                'placeholder_value'          => '',
            ],
            'checkbox' => [
                'required'                   => false,
                'default_state'              => [],
            ],
            'radio' => [
                'required'                   => false,
            ],
            'file' => [
                'required'                   => false,
                'allowed_types'              => 'jpg,jpeg,png,pdf',
                'max_size_mb'                => 5,
                'allow_multiple'             => false,
                'show_preview'               => false,
            ],
        ];

        return $defaults[ $type ] ?? $defaults['text'];
    }

    /**
     * Auto-generate a human-readable label from a CF7 field name.
     * e.g. "your-first-name" → "First Name"
     */
    public static function generate_label( string $field_name ): string {
        $label = preg_replace( '/^(your-|the-)/', '', $field_name );
        $label = str_replace( [ '-', '_' ], ' ', $label );
        return ucwords( $label );
    }

    /**
     * Get the full config for a form, merging saved values over defaults.
     */
    public static function get( int $form_id ): array {
        $saved_json = get_post_meta( $form_id, self::META_KEY, true );
        $saved      = $saved_json ? json_decode( $saved_json, true ) : [];

        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        return [
            'global' => array_merge( self::get_global_defaults(), $saved['global'] ?? [] ),
            'fields' => $saved['fields'] ?? [],
        ];
    }

    /**
     * Save the full config for a form.
     */
    public static function save( int $form_id, array $config ): void {
        // Sanitize global settings.
        $sanitized = [
            'global' => [
                'show_optional_label' => ! empty( $config['global']['show_optional_label'] ),
            ],
            'fields' => [],
        ];

        // Sanitize per-field config.
        foreach ( $config['fields'] ?? [] as $field_name => $field_config ) {
            $clean_name                        = sanitize_key( $field_name );
            $sanitized['fields'][ $clean_name ] = self::sanitize_field_config( $field_config );
        }

        update_post_meta( $form_id, self::META_KEY, wp_json_encode( $sanitized ) );
    }

    /**
     * Sanitize an individual field's config array.
     */
    private static function sanitize_field_config( array $config ): array {
        $out = [];

        foreach ( $config as $key => $value ) {
            $key = sanitize_key( $key );

            if ( is_bool( $value ) ) {
                $out[ $key ] = (bool) $value;
            } elseif ( is_int( $value ) || ctype_digit( (string) $value ) ) {
                $out[ $key ] = absint( $value );
            } elseif ( is_float( $value ) ) {
                $out[ $key ] = floatval( $value );
            } elseif ( is_string( $value ) ) {
                $out[ $key ] = sanitize_text_field( $value );
            } elseif ( is_array( $value ) ) {
                $out[ $key ] = array_map( 'sanitize_text_field', $value );
            }
        }

        return $out;
    }

    /**
     * Copy validation config from one form to another (used on duplication).
     */
    public static function copy( int $source_form_id, int $target_form_id ): void {
        $raw = get_post_meta( $source_form_id, self::META_KEY, true );
        if ( $raw ) {
            update_post_meta( $target_form_id, self::META_KEY, $raw );
        }
    }
}
```

- [ ] **Step 2: Verify class loads without fatal errors**

In `cf7-validate-pro.php`, `CFV_Config` is required after the CF7 check. Reload WP admin with `WP_DEBUG` on. Confirm no PHP errors in `debug.log`.

- [ ] **Step 3: Manually test get/save round-trip in WP admin**

Add a temporary line to `cfv_check_cf7_dependency()` after `CFV_Config` is required:
```php
// Temp test — remove after verifying.
$form_id = 1; // use a real CF7 form post ID from your DB
CFV_Config::save( $form_id, [
    'global' => [ 'show_optional_label' => true ],
    'fields' => [
        'your-name' => [ 'required' => true, 'min_length' => 2, 'label' => 'Full Name' ],
    ],
]);
$loaded = CFV_Config::get( $form_id );
error_log( print_r( $loaded, true ) );
```
Check `debug.log` for the expected output. Remove the temp lines after verifying.

- [ ] **Step 4: Commit**

```bash
git add includes/class-cfv-config.php
git commit -m "feat: add CFV_Config class for reading and writing per-form validation settings"
```

---

## Task 3: Hooks Class Skeleton

**Files:**
- Create: `includes/class-cfv-hooks.php`

- [ ] **Step 1: Create the hooks class with all hook registrations (empty callbacks for now)**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFV_Hooks {

    public static function init(): void {
        // Assets.
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // CF7 editor tab.
        add_filter( 'wpcf7_editor_panels', [ __CLASS__, 'register_validation_panel' ] );

        // AJAX: save validation config from tab.
        add_action( 'wp_ajax_cfv_save_config', [ __CLASS__, 'ajax_save_config' ] );
        add_action( 'wp_ajax_cfv_get_config', [ __CLASS__, 'ajax_get_config' ] );

        // Form HTML decoration.
        add_filter( 'wpcf7_form_elements', [ __CLASS__, 'decorate_form_elements' ] );

        // Server-side validation.
        add_filter( 'wpcf7_before_send_mail', [ __CLASS__, 'validate_submission' ], 10, 3 );

        // Override CF7 error response.
        add_filter( 'wpcf7_ajax_json_echo', [ __CLASS__, 'override_error_response' ], 10, 2 );

        // Form duplication.
        add_action( 'wp_insert_post', [ __CLASS__, 'maybe_copy_config_on_duplicate' ], 10, 3 );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_frontend_assets(): void {
        // Only enqueue on pages that have a CF7 form shortcode.
        // Check will be implemented in Task 3 Step 2.
    }

    public static function enqueue_admin_assets( string $hook ): void {
        // Only enqueue on CF7 form edit screen.
        // Check will be implemented in Task 5.
    }

    // -------------------------------------------------------------------------
    // CF7 Editor Panel
    // -------------------------------------------------------------------------

    public static function register_validation_panel( array $panels ): array {
        // Will be implemented in Task 5.
        return $panels;
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    public static function ajax_save_config(): void {
        // Will be implemented in Task 10.
        wp_send_json_error( 'Not implemented yet.' );
    }

    public static function ajax_get_config(): void {
        // Will be implemented in Task 10.
        wp_send_json_error( 'Not implemented yet.' );
    }

    // -------------------------------------------------------------------------
    // Form HTML Decoration
    // -------------------------------------------------------------------------

    public static function decorate_form_elements( string $form_html ): string {
        // Will be implemented in Task 11.
        return $form_html;
    }

    // -------------------------------------------------------------------------
    // Server-side Validation
    // -------------------------------------------------------------------------

    public static function validate_submission( WPCF7_ContactForm $contact_form, &$abort, WPCF7_Submission $submission ): WPCF7_ContactForm {
        // Will be implemented in Task 17.
        return $contact_form;
    }

    // -------------------------------------------------------------------------
    // Error Response Override
    // -------------------------------------------------------------------------

    public static function override_error_response( array $response, array $result ): array {
        // Will be implemented in Task 17.
        return $response;
    }

    // -------------------------------------------------------------------------
    // Form Duplication
    // -------------------------------------------------------------------------

    public static function maybe_copy_config_on_duplicate( int $post_id, WP_Post $post, bool $update ): void {
        // Will be implemented in Task 18.
    }
}
```

- [ ] **Step 2: Implement frontend asset enqueue with CF7 shortcode detection**

Replace the `enqueue_frontend_assets` method:

```php
public static function enqueue_frontend_assets(): void {
    global $post;

    // Only enqueue if the current page has a CF7 shortcode.
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

    // Collect config for all CF7 forms on this page and pass to JS.
    $forms_config = self::collect_page_forms_config( $post->post_content );

    wp_localize_script( 'cfv-validation', 'cfvConfig', [
        'forms'  => $forms_config,
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ] );
}

private static function collect_page_forms_config( string $content ): array {
    $config = [];

    // Extract all CF7 shortcode IDs from the page content.
    preg_match_all( '/\[contact-form-7[^\]]*id=["\']?(\d+)["\']?/i', $content, $matches );

    foreach ( array_unique( $matches[1] ) as $form_id ) {
        $form_id          = (int) $form_id;
        $config[ $form_id ] = CFV_Config::get( $form_id );
    }

    return $config;
}
```

- [ ] **Step 3: Verify no fatal errors on WP frontend page load**

Navigate to a page with a CF7 form in your local WP site. Check browser console — no JS errors. Check `debug.log` — no PHP errors.

- [ ] **Step 4: Commit**

```bash
git add includes/class-cfv-hooks.php
git commit -m "feat: add CFV_Hooks skeleton with all hook registrations and frontend asset enqueuing"
```

---

## Task 4: Build Tooling Setup

**Files:**
- Create: `package.json`

- [ ] **Step 1: Create package.json**

```json
{
  "name": "contact-form-validation",
  "version": "1.0.0",
  "description": "CF7 Validation Addon build configuration",
  "scripts": {
    "build": "wp-scripts build src/validation-tab/index.js --output-path=build/validation-tab",
    "start": "wp-scripts start src/validation-tab/index.js --output-path=build/validation-tab"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0"
  }
}
```

- [ ] **Step 2: Install dependencies**

```bash
cd "C:/Users/Wac/Local Sites/contact-form/app/public/wp-content/plugins/contact-form-validation"
npm install
```

Expected: `node_modules/` created, no errors.

- [ ] **Step 3: Create placeholder entry file to verify build works**

Create `src/validation-tab/index.js`:
```javascript
// Placeholder — will be implemented in Task 5.
console.log( 'CFV Validation Tab loaded' );
```

- [ ] **Step 4: Run the build**

```bash
npm run build
```

Expected: `build/validation-tab/index.js` and `build/validation-tab/index.asset.php` created with no errors.

- [ ] **Step 5: Commit**

```bash
git add package.json src/validation-tab/index.js
git commit -m "chore: add @wordpress/scripts build tooling"
```

---

## Task 5: CF7 Validation Tab — Registration

**Files:**
- Modify: `includes/class-cfv-hooks.php` — `register_validation_panel` and `enqueue_admin_assets`
- Modify: `src/validation-tab/index.js`
- Create: `src/validation-tab/components/ValidationTab.js`

- [ ] **Step 1: Register the CF7 editor panel in hooks**

Replace `register_validation_panel` in `class-cfv-hooks.php`:

```php
public static function register_validation_panel( array $panels ): array {
    $panels['validation'] = [
        'title'    => __( 'Validation', 'cf7-validate-pro' ),
        'callback' => [ __CLASS__, 'render_validation_panel' ],
    ];
    return $panels;
}

public static function render_validation_panel( WPCF7_ContactForm $post ): void {
    echo '<div id="cfv-validation-tab" data-form-id="' . esc_attr( $post->id() ) . '"></div>';
}
```

- [ ] **Step 2: Enqueue admin assets on the CF7 edit screen**

Replace `enqueue_admin_assets` in `class-cfv-hooks.php`:

```php
public static function enqueue_admin_assets( string $hook ): void {
    // CF7 form edit screen hook is 'toplevel_page_wpcf7' or 'contact_page_wpcf7-new'
    // The safe check: look for the post type in $_GET.
    if ( empty( $_GET['page'] ) || $_GET['page'] !== 'wpcf7' ) {
        return;
    }

    $asset_file = CFV_PLUGIN_DIR . 'build/validation-tab/index.asset.php';
    if ( ! file_exists( $asset_file ) ) {
        return; // Build not run yet.
    }

    $asset = require $asset_file;

    wp_enqueue_script(
        'cfv-validation-tab',
        CFV_PLUGIN_URL . 'build/validation-tab/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'cfv-validation-tab-style',
        CFV_PLUGIN_URL . 'build/validation-tab/index.css',
        [ 'wp-components' ],
        $asset['version']
    );

    // Pass nonce and ajax URL to the React app.
    wp_localize_script( 'cfv-validation-tab', 'cfvAdmin', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'cfv_admin' ),
    ] );
}
```

- [ ] **Step 3: Create ValidationTab.js shell**

Create `src/validation-tab/components/ValidationTab.js`:

```javascript
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';

export default function ValidationTab( { formId } ) {
    const [ config, setConfig ] = useState( null );
    const [ saving, setSaving ] = useState( false );
    const [ saveMessage, setSaveMessage ] = useState( '' );

    useEffect( () => {
        fetch( cfvAdmin.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams( {
                action: 'cfv_get_config',
                form_id: formId,
                nonce: cfvAdmin.nonce,
            } ),
        } )
            .then( ( r ) => r.json() )
            .then( ( data ) => {
                if ( data.success ) {
                    setConfig( data.data );
                }
            } );
    }, [ formId ] );

    const save = () => {
        setSaving( true );
        fetch( cfvAdmin.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams( {
                action: 'cfv_save_config',
                form_id: formId,
                config: JSON.stringify( config ),
                nonce: cfvAdmin.nonce,
            } ),
        } )
            .then( ( r ) => r.json() )
            .then( ( data ) => {
                setSaving( false );
                setSaveMessage( data.success ? 'Saved.' : 'Error saving.' );
                setTimeout( () => setSaveMessage( '' ), 3000 );
            } );
    };

    if ( ! config ) return <Spinner />;

    return (
        <div className="cfv-validation-tab">
            <h2>Validation Settings</h2>
            { /* GlobalSettings and FieldRuleRow list will be added in Tasks 7 and 9 */ }
            <p>{ saveMessage }</p>
            <button
                className="button button-primary"
                onClick={ save }
                disabled={ saving }
            >
                { saving ? 'Saving…' : 'Save Validation Settings' }
            </button>
        </div>
    );
}
```

- [ ] **Step 4: Update index.js to mount the React app**

```javascript
import { render } from '@wordpress/element';
import ValidationTab from './components/ValidationTab';

const mount = () => {
    const el = document.getElementById( 'cfv-validation-tab' );
    if ( ! el ) return;

    const formId = parseInt( el.dataset.formId, 10 );
    render( <ValidationTab formId={ formId } />, el );
};

// CF7 editor is ready when DOMContentLoaded fires.
document.addEventListener( 'DOMContentLoaded', mount );
```

- [ ] **Step 5: Run build**

```bash
npm run build
```

Expected: no errors.

- [ ] **Step 6: Verify tab appears in CF7 form editor**

Open WP admin → Contact → any form. Confirm a "Validation" tab appears next to Form / Mail / Messages / Additional Settings. Clicking it should show the React shell (spinner, then empty tab with a Save button). Check browser console for errors.

- [ ] **Step 7: Commit**

```bash
git add includes/class-cfv-hooks.php src/validation-tab/index.js src/validation-tab/components/ValidationTab.js
git commit -m "feat: register Validation tab in CF7 form editor with React shell"
```

---

## Task 6: Form Field Parser

**Files:**
- Create: `src/validation-tab/hooks/useFormFields.js`

- [ ] **Step 1: Create the useFormFields hook**

This hook reads the CF7 form body textarea (which contains the shortcode template) and extracts all fields with their names and types.

```javascript
import { useState, useEffect } from '@wordpress/element';

/**
 * Maps CF7 shortcode tag names to our internal field types.
 */
const TAG_TYPE_MAP = {
    text:           'text',
    'text*':        'text',
    email:          'email',
    'email*':       'email',
    tel:            'tel',
    'tel*':         'tel',
    number:         'number',
    'number*':      'number',
    url:            'url',
    'url*':         'url',
    textarea:       'textarea',
    'textarea*':    'textarea',
    select:         'select',
    'select*':      'select',
    checkbox:       'checkbox',
    'checkbox*':    'checkbox',
    radio:          'radio',
    file:           'file',
    'file*':        'file',
};

/**
 * Returns the internal type for a field name, applying name-based heuristics
 * (e.g. "first-name" or "last-name" → 'name' type).
 */
function resolveType( tagType, fieldName ) {
    const base = tagType.replace( '*', '' );
    if ( base === 'text' ) {
        if ( /\b(first.?name|last.?name|full.?name|your.?name)\b/i.test( fieldName ) ) {
            return 'name';
        }
    }
    return TAG_TYPE_MAP[ tagType ] || 'text';
}

/**
 * Parse CF7 form body text and return array of { name, type, required }.
 */
function parseFormBody( body ) {
    const fields = [];
    // Match [tag-type field-name ...] shortcodes.
    const regex = /\[([a-z_*]+)\s+([\w-]+)/gi;
    let match;

    while ( ( match = regex.exec( body ) ) !== null ) {
        const tagType  = match[ 1 ];
        const name     = match[ 2 ];

        if ( ! TAG_TYPE_MAP[ tagType ] ) continue; // skip non-field tags (submit, etc.)

        fields.push( {
            name,
            type:     resolveType( tagType, name ),
            required: tagType.endsWith( '*' ),
        } );
    }

    return fields;
}

/**
 * Hook: reads the CF7 form body textarea and returns parsed fields.
 * Re-parses when the textarea content changes.
 */
export default function useFormFields() {
    const [ fields, setFields ] = useState( [] );

    useEffect( () => {
        const textarea = document.querySelector( '#wpcf7-form' );
        if ( ! textarea ) return;

        const update = () => setFields( parseFormBody( textarea.value ) );
        update();

        textarea.addEventListener( 'input', update );
        return () => textarea.removeEventListener( 'input', update );
    }, [] );

    return fields;
}
```

- [ ] **Step 2: Verify parsing in browser console**

Temporarily add to `ValidationTab.js` after the `useFormFields` import:
```javascript
import useFormFields from '../hooks/useFormFields';
// inside component:
const fields = useFormFields();
console.log( 'Parsed fields:', fields );
```
Open a CF7 form with known fields in the editor. Check console — confirm fields list matches the form's actual fields with correct names and types. Remove the `console.log` after verifying. Keep `useFormFields` import — it will be used in Task 9.

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add src/validation-tab/hooks/useFormFields.js src/validation-tab/components/ValidationTab.js
git commit -m "feat: add useFormFields hook to parse CF7 form body into typed field list"
```

---

## Task 7: Global Settings Component

**Files:**
- Create: `src/validation-tab/components/GlobalSettings.js`
- Modify: `src/validation-tab/components/ValidationTab.js`

- [ ] **Step 1: Create GlobalSettings.js**

```javascript
import { ToggleControl } from '@wordpress/components';

export default function GlobalSettings( { global, onChange } ) {
    return (
        <div className="cfv-global-settings">
            <h3>Global Settings</h3>
            <ToggleControl
                label='Show "(Optional)" label on non-required fields'
                checked={ !! global.show_optional_label }
                onChange={ ( val ) => onChange( { ...global, show_optional_label: val } ) }
            />
        </div>
    );
}
```

- [ ] **Step 2: Add GlobalSettings to ValidationTab.js**

Add import at top:
```javascript
import GlobalSettings from './GlobalSettings';
```

In the JSX, replace the comment placeholder with:
```jsx
<GlobalSettings
    global={ config.global }
    onChange={ ( newGlobal ) => setConfig( { ...config, global: newGlobal } ) }
/>
```

- [ ] **Step 3: Build and verify**

```bash
npm run build
```

Open CF7 form editor → Validation tab. Confirm the "Show Optional label" toggle appears and toggles without errors.

- [ ] **Step 4: Commit**

```bash
git add src/validation-tab/components/GlobalSettings.js src/validation-tab/components/ValidationTab.js
git commit -m "feat: add GlobalSettings component with optional label toggle"
```

---

## Task 8: Rule Input Components

**Files:**
- Create: `src/validation-tab/components/RuleInputs/TextRules.js`
- Create: `src/validation-tab/components/RuleInputs/NameRules.js`
- Create: `src/validation-tab/components/RuleInputs/EmailRules.js`
- Create: `src/validation-tab/components/RuleInputs/PhoneRules.js`
- Create: `src/validation-tab/components/RuleInputs/NumericRules.js`
- Create: `src/validation-tab/components/RuleInputs/TextareaRules.js`
- Create: `src/validation-tab/components/RuleInputs/SelectRules.js`
- Create: `src/validation-tab/components/RuleInputs/CheckboxRules.js`
- Create: `src/validation-tab/components/RuleInputs/FileRules.js`

Each component receives `( rules, onChange )` props where `rules` is the field's config object and `onChange` is called with the updated rules object.

- [ ] **Step 1: Create TextRules.js**

```javascript
import { TextControl, ToggleControl, SelectControl } from '@wordpress/components';

export default function TextRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Min characters" type="number" value={ rules.min_length ?? '' } onChange={ v => set( 'min_length', v ) } />
            <TextControl label="Max characters" type="number" value={ rules.max_length ?? '' } onChange={ v => set( 'max_length', v ) } />
            <ToggleControl label="No leading spaces" checked={ !! rules.no_leading_spaces } onChange={ v => set( 'no_leading_spaces', v ) } />
            <ToggleControl label="Allow special characters" checked={ rules.allow_special_chars !== false } onChange={ v => set( 'allow_special_chars', v ) } />
            <ToggleControl label="Allow emoji / Unicode" checked={ rules.allow_emoji !== false } onChange={ v => set( 'allow_emoji', v ) } />
            <ToggleControl label="Collapse consecutive whitespace" checked={ rules.collapse_whitespace !== false } onChange={ v => set( 'collapse_whitespace', v ) } />
            <TextControl label="Input mask (9=digit, a=letter, *=alphanumeric)" value={ rules.input_mask ?? '' } onChange={ v => set( 'input_mask', v ) } />
            <SelectControl
                label="Character counter"
                value={ rules.counter_format ?? 'off' }
                options={ [
                    { label: 'Off', value: 'off' },
                    { label: 'Count / Max (e.g. 13 / 100)', value: 'count/max' },
                    { label: 'Remaining (e.g. 87 remaining)', value: 'remaining' },
                ] }
                onChange={ v => set( 'counter_format', v ) }
            />
        </div>
    );
}
```

- [ ] **Step 2: Create NameRules.js**

```javascript
import { TextControl, ToggleControl } from '@wordpress/components';

export default function NameRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <p className="cfv-rules-note">Name field defaults: alpha only, no leading spaces, min 2 chars (first name), max 56 chars. Override below.</p>
            <TextControl label="Min characters" type="number" value={ rules.min_length ?? '' } onChange={ v => set( 'min_length', v ) } />
            <TextControl label="Max characters" type="number" value={ rules.max_length ?? 56 } onChange={ v => set( 'max_length', v ) } />
            <ToggleControl label="Alpha only" checked={ rules.alpha_only !== false } onChange={ v => set( 'alpha_only', v ) } />
            <ToggleControl label="No leading spaces" checked={ rules.no_leading_spaces !== false } onChange={ v => set( 'no_leading_spaces', v ) } />
        </div>
    );
}
```

- [ ] **Step 3: Create EmailRules.js**

```javascript
import { ToggleControl } from '@wordpress/components';

export default function EmailRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <p className="cfv-rules-note">Email format validation and whitespace trimming are always active.</p>
            <ToggleControl label="Required" checked={ !! rules.required } onChange={ v => set( 'required', v ) } />
        </div>
    );
}
```

- [ ] **Step 4: Create PhoneRules.js**

```javascript
import { TextControl, ToggleControl, SelectControl } from '@wordpress/components';

export default function PhoneRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Min length (digits)" type="number" value={ rules.min_length ?? 7 } onChange={ v => set( 'min_length', v ) } />
            <TextControl label="Max length (digits)" type="number" value={ rules.max_length ?? 15 } onChange={ v => set( 'max_length', v ) } />
            <ToggleControl
                label="Enable international phone input (intl-tel-input)"
                checked={ !! rules.enable_intl }
                onChange={ v => set( 'enable_intl', v ) }
            />
            { rules.enable_intl && (
                <TextControl
                    label="Default country code (e.g. us, gb, au — or 'auto')"
                    value={ rules.default_country ?? 'auto' }
                    onChange={ v => set( 'default_country', v ) }
                />
            ) }
        </div>
    );
}
```

- [ ] **Step 5: Create NumericRules.js**

```javascript
import { TextControl, ToggleControl } from '@wordpress/components';

export default function NumericRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Min value" type="number" value={ rules.min_value ?? '' } onChange={ v => set( 'min_value', v ) } />
            <TextControl label="Max value" type="number" value={ rules.max_value ?? '' } onChange={ v => set( 'max_value', v ) } />
            <ToggleControl label="Allow negative values" checked={ !! rules.allow_negative } onChange={ v => set( 'allow_negative', v ) } />
            <ToggleControl label="Allow zero" checked={ rules.allow_zero !== false } onChange={ v => set( 'allow_zero', v ) } />
        </div>
    );
}
```

- [ ] **Step 6: Create TextareaRules.js**

```javascript
import { TextControl, ToggleControl, SelectControl } from '@wordpress/components';

export default function TextareaRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Max characters" type="number" value={ rules.max_length ?? 1500 } onChange={ v => set( 'max_length', v ) } />
            <SelectControl
                label="Character counter"
                value={ rules.counter_format ?? 'count/max' }
                options={ [
                    { label: 'Count / Max (e.g. 13 / 1500)', value: 'count/max' },
                    { label: 'Remaining (e.g. 1487 remaining)', value: 'remaining' },
                    { label: 'Off', value: 'off' },
                ] }
                onChange={ v => set( 'counter_format', v ) }
            />
            <TextControl label="Max height (px) before scrollbar" type="number" value={ rules.max_height ?? 200 } onChange={ v => set( 'max_height', v ) } />
            <ToggleControl label="Strip code/security operators" checked={ rules.security_sanitize !== false } onChange={ v => set( 'security_sanitize', v ) } />
        </div>
    );
}
```

- [ ] **Step 7: Create SelectRules.js**

```javascript
import { TextControl, ToggleControl } from '@wordpress/components';

export default function SelectRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl
                label='Placeholder option value (the value that means "not selected")'
                value={ rules.placeholder_value ?? '' }
                onChange={ v => set( 'placeholder_value', v ) }
            />
        </div>
    );
}
```

- [ ] **Step 8: Create CheckboxRules.js**

```javascript
import { ToggleControl } from '@wordpress/components';

export default function CheckboxRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <ToggleControl
                label="Required — at least one option must be selected"
                checked={ !! rules.required }
                onChange={ v => set( 'required', v ) }
            />
        </div>
    );
}
```

- [ ] **Step 9: Create FileRules.js**

```javascript
import { TextControl, ToggleControl } from '@wordpress/components';

export default function FileRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl
                label="Allowed file types (comma-separated, e.g. jpg,png,pdf)"
                value={ rules.allowed_types ?? 'jpg,jpeg,png,pdf' }
                onChange={ v => set( 'allowed_types', v ) }
            />
            <TextControl label="Max file size (MB)" type="number" value={ rules.max_size_mb ?? 5 } onChange={ v => set( 'max_size_mb', v ) } />
            <ToggleControl label="Allow multiple files" checked={ !! rules.allow_multiple } onChange={ v => set( 'allow_multiple', v ) } />
            <ToggleControl label="Show file preview before submit" checked={ !! rules.show_preview } onChange={ v => set( 'show_preview', v ) } />
        </div>
    );
}
```

- [ ] **Step 10: Build and confirm no errors**

```bash
npm run build
```

- [ ] **Step 11: Commit**

```bash
git add src/validation-tab/components/RuleInputs/
git commit -m "feat: add all RuleInputs components for each CF7 field type"
```

---

## Task 9: FieldRuleRow Component

**Files:**
- Create: `src/validation-tab/components/FieldRuleRow.js`
- Modify: `src/validation-tab/components/ValidationTab.js`

- [ ] **Step 1: Create FieldRuleRow.js**

```javascript
import { TextControl, ToggleControl } from '@wordpress/components';
import TextRules     from './RuleInputs/TextRules';
import NameRules     from './RuleInputs/NameRules';
import EmailRules    from './RuleInputs/EmailRules';
import PhoneRules    from './RuleInputs/PhoneRules';
import NumericRules  from './RuleInputs/NumericRules';
import TextareaRules from './RuleInputs/TextareaRules';
import SelectRules   from './RuleInputs/SelectRules';
import CheckboxRules from './RuleInputs/CheckboxRules';
import FileRules     from './RuleInputs/FileRules';

const RULES_COMPONENT_MAP = {
    text:     TextRules,
    name:     NameRules,
    email:    EmailRules,
    tel:      PhoneRules,
    number:   NumericRules,
    textarea: TextareaRules,
    url:      TextRules,  // URL reuses TextRules (no special config beyond required)
    select:   SelectRules,
    checkbox: CheckboxRules,
    radio:    CheckboxRules,  // Radio reuses CheckboxRules
    file:     FileRules,
};

export default function FieldRuleRow( { field, rules, onChange } ) {
    const { name, type, required: cfRequired } = field;

    const setRule = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    const setRules = ( newRules ) => onChange( newRules );

    const RulesComponent = RULES_COMPONENT_MAP[ type ] || TextRules;

    // Auto-generate label if not set.
    const autoLabel = name
        .replace( /^(your-|the-)/, '' )
        .replace( /[-_]/g, ' ' )
        .replace( /\b\w/g, ( c ) => c.toUpperCase() );

    return (
        <div className="cfv-field-row">
            <div className="cfv-field-row__header">
                <span className="cfv-field-row__name">
                    <code>{ name }</code>
                    <span className="cfv-field-row__type">({ type })</span>
                    { cfRequired && <span className="cfv-field-row__cf-required">CF7 required</span> }
                </span>
            </div>

            <div className="cfv-field-row__body">
                <TextControl
                    label="Field label (used in error messages)"
                    placeholder={ autoLabel }
                    value={ rules.label ?? '' }
                    onChange={ v => setRule( 'label', v ) }
                />
                <ToggleControl
                    label="Required"
                    checked={ !! rules.required }
                    onChange={ v => setRule( 'required', v ) }
                />
                <RulesComponent rules={ rules } onChange={ setRules } />
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Wire FieldRuleRow into ValidationTab.js**

Add imports at top of `ValidationTab.js`:
```javascript
import useFormFields from '../hooks/useFormFields';
import FieldRuleRow  from './FieldRuleRow';
import { CFV_Config_Defaults } from '../utils/defaults';
```

Create `src/validation-tab/utils/defaults.js`:
```javascript
// Mirror of PHP CFV_Config::get_field_type_defaults per type.
export const FIELD_TYPE_DEFAULTS = {
    text:     { required: false, min_length: '', max_length: '', alpha_only: false, no_leading_spaces: false, allow_special_chars: true, allow_emoji: true, collapse_whitespace: true, input_mask: '', counter_format: 'off' },
    name:     { required: false, min_length: 2, max_length: 56, alpha_only: true, no_leading_spaces: true, allow_special_chars: false, allow_emoji: false, collapse_whitespace: true, input_mask: '', counter_format: 'off' },
    email:    { required: false, trim_whitespace: true },
    tel:      { required: false, min_length: 7, max_length: 15, enable_intl: false, default_country: 'auto' },
    number:   { required: false, min_value: '', max_value: '', allow_negative: false, allow_zero: true },
    url:      { required: false },
    textarea: { required: false, max_length: 1500, counter_format: 'count/max', max_height: 200, security_sanitize: true },
    select:   { required: false, placeholder_value: '' },
    checkbox: { required: false },
    radio:    { required: false },
    file:     { required: false, allowed_types: 'jpg,jpeg,png,pdf', max_size_mb: 5, allow_multiple: false, show_preview: false },
};

export function getFieldDefaults( type ) {
    return FIELD_TYPE_DEFAULTS[ type ] ?? FIELD_TYPE_DEFAULTS.text;
}
```

In `ValidationTab.js`, inside the component (after `config` state is loaded), add:
```javascript
const fields = useFormFields();
```

In the JSX, after `<GlobalSettings …/>`, replace the comment placeholder with:
```jsx
<div className="cfv-fields-list">
    <h3>Field Rules</h3>
    { fields.length === 0 && <p>No fields detected. Add fields to the Form tab first.</p> }
    { fields.map( ( field ) => {
        const savedRules = config.fields?.[ field.name ] ?? {};
        const defaults   = getFieldDefaults( field.type );
        const rules      = { ...defaults, ...savedRules };

        return (
            <FieldRuleRow
                key={ field.name }
                field={ field }
                rules={ rules }
                onChange={ ( newRules ) =>
                    setConfig( {
                        ...config,
                        // IMPORTANT: always store `type` alongside the rules so
                        // both the JS validator and PHP validator can read config.type.
                        fields: { ...config.fields, [ field.name ]: { ...newRules, type: field.type } },
                    } )
                }
            />
        );
    } ) }
</div>
```

- [ ] **Step 3: Build and verify full tab renders**

```bash
npm run build
```

Open CF7 form editor → Validation tab. Each field in the form should appear as a row with its label input, Required toggle, and type-specific rule inputs. Change some values and click Save — it should call the AJAX endpoint (which currently returns an error, but the request should fire).

- [ ] **Step 4: Commit**

```bash
git add src/validation-tab/components/FieldRuleRow.js src/validation-tab/utils/defaults.js src/validation-tab/components/ValidationTab.js
git commit -m "feat: add FieldRuleRow with per-type RuleInputs wired into ValidationTab"
```

---

## Task 10: AJAX Save / Load for Validation Tab

**Files:**
- Modify: `includes/class-cfv-hooks.php` — `ajax_save_config` and `ajax_get_config`

- [ ] **Step 1: Implement ajax_get_config**

Replace the `ajax_get_config` method in `class-cfv-hooks.php`:

```php
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
```

- [ ] **Step 2: Implement ajax_save_config**

Replace the `ajax_save_config` method in `class-cfv-hooks.php`:

```php
public static function ajax_save_config(): void {
    check_ajax_referer( 'cfv_admin', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $form_id     = absint( $_POST['form_id'] ?? 0 );
    $config_json = sanitize_textarea_field( wp_unslash( $_POST['config'] ?? '' ) );

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
```

- [ ] **Step 3: Verify save and load in browser**

Open CF7 form editor → Validation tab. Toggle the "Show Optional label" toggle. Click "Save Validation Settings". Confirm "Saved." message appears. Refresh the page and re-open the Validation tab. Confirm the toggle state persisted. Check WP post meta in DB: `SELECT meta_value FROM wp_postmeta WHERE meta_key = '_cfv_validation_config'` should contain the JSON config.

- [ ] **Step 4: Commit**

```bash
git add includes/class-cfv-hooks.php
git commit -m "feat: implement AJAX save/load for validation tab config"
```

---

## Task 11: Field Decorator (PHP — HTML injection)

**Files:**
- Create: `includes/class-cfv-field-decorator.php`
- Modify: `includes/class-cfv-hooks.php` — `decorate_form_elements`

- [ ] **Step 1: Create the field decorator class**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFV_Field_Decorator {

    /**
     * Post-process rendered CF7 form HTML.
     * Injects: red asterisks on required fields, optional labels on non-required fields,
     * empty error spans below each field, counter elements on length-limited fields.
     */
    public static function decorate( string $html, int $form_id ): string {
        $config = CFV_Config::get( $form_id );
        $fields = $config['fields'] ?? [];
        $global = $config['global'] ?? [];
        $show_optional = ! empty( $global['show_optional_label'] );

        foreach ( $fields as $field_name => $field_config ) {
            $required      = ! empty( $field_config['required'] );
            $label         = ! empty( $field_config['label'] )
                ? $field_config['label']
                : CFV_Config::generate_label( $field_name );
            $counter_format = $field_config['counter_format'] ?? 'off';
            $max_length     = ! empty( $field_config['max_length'] ) ? (int) $field_config['max_length'] : 0;
            $max_height     = ! empty( $field_config['max_height'] ) ? (int) $field_config['max_height'] : 0;

            // --- Inject asterisk or optional label next to <label> for this field ---
            $label_pattern = '/<label\b[^>]*\bfor=["\']?' . preg_quote( $field_name, '/' ) . '["\']?[^>]*>(.*?)<\/label>/si';

            if ( $required ) {
                $asterisk = '<span class="cfv-required-asterisk" aria-hidden="true">*</span>';
                $html = preg_replace_callback( $label_pattern, function ( $m ) use ( $asterisk ) {
                    return str_replace( $m[1], $m[1] . ' ' . $asterisk, $m[0] );
                }, $html );
            } elseif ( $show_optional ) {
                $optional = '<span class="cfv-optional-label">(Optional)</span>';
                $html = preg_replace_callback( $label_pattern, function ( $m ) use ( $optional ) {
                    return str_replace( $m[1], $m[1] . ' ' . $optional, $m[0] );
                }, $html );
            }

            // --- Inject empty error span after the field input/textarea/select ---
            $error_span    = '<span class="cfv-error-tip" data-field="' . esc_attr( $field_name ) . '" role="alert" aria-live="polite"></span>';
            $input_pattern = '/(<(?:input|textarea|select)\b[^>]*\bname=["\']?' . preg_quote( $field_name, '/' ) . '["\']?[^>]*\/?>)/si';
            $html = preg_replace( $input_pattern, '$1' . $error_span, $html );

            // --- Inject counter element below textarea/text fields with max_length ---
            if ( $counter_format !== 'off' && $max_length > 0 ) {
                $counter = '<span class="cfv-counter" data-field="' . esc_attr( $field_name ) . '" data-max="' . esc_attr( $max_length ) . '" data-format="' . esc_attr( $counter_format ) . '"></span>';
                // Insert after the error span we just added.
                $html = str_replace( $error_span, $error_span . $counter, $html );
            }

            // --- Apply max-height to textarea for scrollbar ---
            if ( $max_height > 0 ) {
                $html = preg_replace(
                    '/(<textarea\b[^>]*\bname=["\']?' . preg_quote( $field_name, '/' ) . '["\']?[^>]*)(>)/si',
                    '$1 style="max-height:' . $max_height . 'px;overflow-y:auto;" $2',
                    $html
                );
            }
        }

        return $html;
    }
}
```

- [ ] **Step 2: Wire decorator into hooks**

Replace `decorate_form_elements` in `class-cfv-hooks.php`:

```php
public static function decorate_form_elements( string $form_html ): string {
    $form = WPCF7_ContactForm::get_current();
    if ( ! $form ) {
        return $form_html;
    }
    return CFV_Field_Decorator::decorate( $form_html, $form->id() );
}
```

- [ ] **Step 3: Verify decoration in browser**

Configure a CF7 form field as required in the Validation tab. Save. View the form on the frontend. Inspect the HTML — confirm:
- Red asterisk `<span class="cfv-required-asterisk">` appears after the field label
- Empty `<span class="cfv-error-tip">` appears after the input
- For a textarea with max_length set, a `<span class="cfv-counter">` appears after the error span

- [ ] **Step 4: Commit**

```bash
git add includes/class-cfv-field-decorator.php includes/class-cfv-hooks.php
git commit -m "feat: add CFV_Field_Decorator to inject asterisks, optional labels, error spans, and counters into CF7 form HTML"
```

---

## Task 12: Client-side Validation Engine

**Files:**
- Create: `assets/js/cfv-validation.js`

- [ ] **Step 1: Create cfv-validation.js**

```javascript
/* global cfvConfig */
( function () {
    'use strict';

    // =========================================================================
    // Pure validation rule functions
    // =========================================================================

    const Rules = {
        required:          ( v ) => v.trim() !== '',
        alphaOnly:         ( v ) => /^[a-zA-Z\s]*$/.test( v ),
        noLeadingSpaces:   ( v ) => ! /^\s/.test( v ),
        minLength:         ( v, n ) => v.trim().length >= parseInt( n, 10 ),
        maxLength:         ( v, n ) => v.trim().length <= parseInt( n, 10 ),
        email:             ( v ) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( v.trim() ),
        url:               ( v ) => /^https?:\/\/.+\..+/.test( v.trim() ),
        numericOnly:       ( v ) => /^-?\d*\.?\d*$/.test( v.replace( /\s/g, '' ) ),
        minValue:          ( v, n ) => parseFloat( v ) >= parseFloat( n ),
        maxValue:          ( v, n ) => parseFloat( v ) <= parseFloat( n ),
        notNegative:       ( v ) => parseFloat( v ) >= 0,
        notZero:           ( v ) => parseFloat( v ) !== 0,
        phoneBasic:        ( v, min, max ) => {
            const digits = v.replace( /[\s\-()+]/g, '' );
            return /^\d+$/.test( digits ) && digits.length >= min && digits.length <= max;
        },
        noSpecialChars:    ( v ) => ! /[!@#$%^&*()=+\[\]{};'"\\|<>?/`~]/.test( v ),
        noEmoji:           ( v ) => ! /[\u{1F000}-\u{1FFFF}]/u.test( v ),
        fileType:          ( file, allowedStr ) => {
            const allowed = allowedStr.split( ',' ).map( t => t.trim().toLowerCase() );
            const ext = file.name.split( '.' ).pop().toLowerCase();
            return allowed.includes( ext );
        },
        fileSize:          ( file, maxMb ) => file.size <= parseFloat( maxMb ) * 1024 * 1024,
        selectRequired:    ( v, placeholder ) => v !== placeholder && v !== '',
        checkboxRequired:  ( group ) => group.some( ( cb ) => cb.checked ),
        securityPattern:   ( v ) => ! /<script|<\/script|eval\s*\(|alert\s*\(|<\?php|<\?=|\bDROP\b|\bINSERT\b|\bSELECT\b/i.test( v ),
    };

    // =========================================================================
    // Error message builder
    // =========================================================================

    function buildMessage( rule, label, config ) {
        const messages = {
            required:        `${ label } is required`,
            alphaOnly:       `${ label } must contain letters only`,
            noLeadingSpaces: `${ label } must not start with a space`,
            minLength:       `${ label } must be at least ${ config.min_length } characters`,
            maxLength:       `${ label } must be no more than ${ config.max_length } characters`,
            email:           `${ label } must be a valid email address`,
            url:             `${ label } must be a valid URL including http:// or https://`,
            numericOnly:     `${ label } must contain numbers only`,
            minValue:        `${ label } must be at least ${ config.min_value }`,
            maxValue:        `${ label } must be no more than ${ config.max_value }`,
            notNegative:     `${ label } must be a positive number`,
            notZero:         `${ label } cannot be zero`,
            phoneBasic:      `${ label } must be a valid phone number`,
            noSpecialChars:  `${ label } must not contain special characters`,
            noEmoji:         `${ label } must not contain emoji`,
            fileType:        `${ label } accepts ${ config.allowed_types } files only`,
            fileSize:        `${ label } must be under ${ config.max_size_mb }MB`,
            selectRequired:  `${ label } is required`,
            checkboxRequired:`${ label } — please select at least one option`,
            securityPattern: `${ label } contains invalid characters`,
        };
        return messages[ rule ] || `${ label } is invalid`;
    }

    // =========================================================================
    // Validate a single field — returns { valid, message }
    // =========================================================================

    function validateField( fieldName, fieldEl, config, itiInstance ) {
        const type    = config.type || 'text';
        const label   = config.label || toLabel( fieldName );
        let   value   = fieldEl ? fieldEl.value : '';

        // For phone with intl-tel-input, use full E.164 number.
        if ( itiInstance ) {
            value = itiInstance.getNumber() || '';
        }

        // Required check.
        if ( config.required ) {
            if ( type === 'checkbox' || type === 'radio' ) {
                const group = Array.from( document.querySelectorAll( `[name="${ fieldName }"], [name="${ fieldName }[]"]` ) );
                if ( ! Rules.checkboxRequired( group ) ) {
                    return { valid: false, message: buildMessage( 'checkboxRequired', label, config ) };
                }
            } else if ( type === 'select' ) {
                if ( ! Rules.selectRequired( value, config.placeholder_value || '' ) ) {
                    return { valid: false, message: buildMessage( 'selectRequired', label, config ) };
                }
            } else {
                if ( ! Rules.required( value ) ) {
                    return { valid: false, message: buildMessage( 'required', label, config ) };
                }
            }
        }

        // Skip further checks if empty and not required.
        if ( ! value.trim() && ! config.required ) return { valid: true };

        // Type-specific rules.
        if ( type === 'email' ) {
            if ( ! Rules.email( value ) ) return { valid: false, message: buildMessage( 'email', label, config ) };
        }

        if ( type === 'url' ) {
            if ( ! Rules.url( value ) ) return { valid: false, message: buildMessage( 'url', label, config ) };
        }

        if ( type === 'number' ) {
            if ( ! Rules.numericOnly( value ) ) return { valid: false, message: buildMessage( 'numericOnly', label, config ) };
            if ( config.min_value !== '' && config.min_value !== undefined && ! Rules.minValue( value, config.min_value ) )
                return { valid: false, message: buildMessage( 'minValue', label, config ) };
            if ( config.max_value !== '' && config.max_value !== undefined && ! Rules.maxValue( value, config.max_value ) )
                return { valid: false, message: buildMessage( 'maxValue', label, config ) };
            if ( ! config.allow_negative && ! Rules.notNegative( value ) )
                return { valid: false, message: buildMessage( 'notNegative', label, config ) };
            if ( ! config.allow_zero && ! Rules.notZero( value ) )
                return { valid: false, message: buildMessage( 'notZero', label, config ) };
        }

        if ( type === 'tel' ) {
            if ( ! itiInstance ) {
                // Standard phone validation.
                if ( ! Rules.phoneBasic( value, config.min_length || 7, config.max_length || 15 ) )
                    return { valid: false, message: buildMessage( 'phoneBasic', label, config ) };
            } else {
                // intl-tel-input: validate E.164 format.
                const digits = value.replace( /\D/g, '' );
                if ( digits.length < ( config.min_length || 7 ) || digits.length > ( config.max_length || 15 ) )
                    return { valid: false, message: buildMessage( 'phoneBasic', label, config ) };
            }
        }

        if ( type === 'text' || type === 'name' || type === 'textarea' ) {
            if ( config.alpha_only && ! Rules.alphaOnly( value ) )
                return { valid: false, message: buildMessage( 'alphaOnly', label, config ) };
            if ( config.no_leading_spaces && ! Rules.noLeadingSpaces( value ) )
                return { valid: false, message: buildMessage( 'noLeadingSpaces', label, config ) };
            if ( config.min_length && ! Rules.minLength( value, config.min_length ) )
                return { valid: false, message: buildMessage( 'minLength', label, config ) };
            if ( config.max_length && ! Rules.maxLength( value, config.max_length ) )
                return { valid: false, message: buildMessage( 'maxLength', label, config ) };
            if ( config.allow_special_chars === false && ! Rules.noSpecialChars( value ) )
                return { valid: false, message: buildMessage( 'noSpecialChars', label, config ) };
            if ( config.allow_emoji === false && ! Rules.noEmoji( value ) )
                return { valid: false, message: buildMessage( 'noEmoji', label, config ) };
            if ( type === 'textarea' && config.security_sanitize && ! Rules.securityPattern( value ) )
                return { valid: false, message: buildMessage( 'securityPattern', label, config ) };
        }

        if ( type === 'file' ) {
            const files = fieldEl?.files || [];
            for ( const file of files ) {
                if ( ! Rules.fileType( file, config.allowed_types || 'jpg,jpeg,png,pdf' ) )
                    return { valid: false, message: buildMessage( 'fileType', label, config ) };
                if ( ! Rules.fileSize( file, config.max_size_mb || 5 ) )
                    return { valid: false, message: buildMessage( 'fileSize', label, config ) };
            }
        }

        return { valid: true };
    }

    // =========================================================================
    // Error display helpers
    // =========================================================================

    function showError( fieldName, message ) {
        const span = document.querySelector( `.cfv-error-tip[data-field="${ fieldName }"]` );
        if ( span ) {
            span.textContent = message;
            span.style.display = 'block';
        }
    }

    function clearError( fieldName ) {
        const span = document.querySelector( `.cfv-error-tip[data-field="${ fieldName }"]` );
        if ( span ) {
            span.textContent = '';
            span.style.display = 'none';
        }
    }

    function clearAllErrors( formEl ) {
        formEl.querySelectorAll( '.cfv-error-tip' ).forEach( ( s ) => {
            s.textContent = '';
            s.style.display = 'none';
        } );
    }

    // =========================================================================
    // Utility
    // =========================================================================

    function toLabel( name ) {
        return name.replace( /^(your-|the-)/, '' ).replace( /[-_]/g, ' ' ).replace( /\b\w/g, c => c.toUpperCase() );
    }

    function collapseWhitespace( value ) {
        return value.replace( /\s+/g, ' ' ).trim();
    }

    // =========================================================================
    // Apply input mask
    // =========================================================================

    function applyMask( el, mask ) {
        if ( ! mask ) return;
        const raw = el.value.replace( /\D/g, '' );
        let result = '';
        let rawIdx = 0;

        for ( let i = 0; i < mask.length && rawIdx < raw.length; i++ ) {
            const m = mask[ i ];
            if ( m === '9' ) {
                if ( /\d/.test( raw[ rawIdx ] ) ) result += raw[ rawIdx++ ];
            } else if ( m === 'a' ) {
                if ( /[a-zA-Z]/.test( raw[ rawIdx ] ) ) result += raw[ rawIdx++ ];
                else rawIdx++;
            } else if ( m === '*' ) {
                result += raw[ rawIdx++ ];
            } else {
                result += m;
            }
        }
        el.value = result;
    }

    // =========================================================================
    // Per-form instance
    // =========================================================================

    function createInstance( formEl, formId, instanceIndex ) {
        const instanceKey = `${ formId }_${ instanceIndex }`;
        const formConfig  = window.cfvConfig?.forms?.[ formId ] || { global: {}, fields: {} };
        const fieldConfigs = formConfig.fields || {};

        function getFieldEl( name ) {
            return formEl.querySelector( `[name="${ name }"]` ) || formEl.querySelector( `[name="${ name }[]"]` );
        }

        function runFieldValidation( name ) {
            const config  = fieldConfigs[ name ];
            if ( ! config ) return true;

            const fieldEl = getFieldEl( name );
            // Read iti instances lazily here (not at init time) so cfv-intl-phone.js
            // has had a chance to populate window.cfvItiInstances by the time the
            // user first interacts with a field.
            const itiInstances = window.cfvItiInstances?.[ instanceKey ] || {};
            const iti     = itiInstances[ name ] || null;
            const result  = validateField( name, fieldEl, config, iti );

            if ( result.valid ) {
                clearError( name );
            } else {
                showError( name, result.message );
            }
            return result.valid;
        }

        function runAllValidations() {
            let allValid = true;
            for ( const name of Object.keys( fieldConfigs ) ) {
                if ( ! runFieldValidation( name ) ) allValid = false;
            }
            return allValid;
        }

        // Attach per-field event listeners.
        Object.keys( fieldConfigs ).forEach( ( name ) => {
            const config  = fieldConfigs[ name ];
            const fieldEl = getFieldEl( name );
            if ( ! fieldEl ) return;

            const events = [ 'focus', 'input', 'blur', 'change' ];
            events.forEach( ( evt ) => {
                fieldEl.addEventListener( evt, () => {
                    // Collapse whitespace on blur.
                    if ( evt === 'blur' && config.collapse_whitespace ) {
                        fieldEl.value = collapseWhitespace( fieldEl.value );
                    }
                    // Apply input mask on input.
                    if ( evt === 'input' && config.input_mask ) {
                        applyMask( fieldEl, config.input_mask );
                    }
                    runFieldValidation( name );
                } );
            } );
        } );

        // Submit handler.
        formEl.addEventListener( 'submit', ( e ) => {
            const valid = runAllValidations();
            if ( ! valid ) {
                e.preventDefault();
                e.stopPropagation();
                // Focus first error field.
                const firstError = formEl.querySelector( '.cfv-error-tip:not(:empty)' );
                if ( firstError ) {
                    const name = firstError.dataset.field;
                    const el   = getFieldEl( name );
                    if ( el ) el.focus();
                }
                return;
            }

            // Disable submit button and show spinner.
            const btn = formEl.querySelector( '[type="submit"]' );
            if ( btn ) {
                btn.disabled = true;
                btn.classList.add( 'cfv-loading' );
            }
        } );

        // CF7 form reset after successful submission.
        formEl.addEventListener( 'wpcf7mailsent', () => {
            clearAllErrors( formEl );
            // Reset intl-tel-input instances.
            Object.values( itiInstances ).forEach( ( iti ) => {
                iti.setNumber( '' );
                const defaultCountry = fieldConfigs[ Object.keys( itiInstances ).find( k => itiInstances[ k ] === iti ) ]?.default_country || 'auto';
                if ( defaultCountry !== 'auto' ) iti.setCountry( defaultCountry );
            } );
            // Re-enable submit button.
            const btn = formEl.querySelector( '[type="submit"]' );
            if ( btn ) {
                btn.disabled = false;
                btn.classList.remove( 'cfv-loading' );
            }
        } );

        // Re-enable button if CF7 returns any response (including error).
        formEl.addEventListener( 'wpcf7invalid', () => {
            const btn = formEl.querySelector( '[type="submit"]' );
            if ( btn ) {
                btn.disabled = false;
                btn.classList.remove( 'cfv-loading' );
            }
        } );
    }

    // =========================================================================
    // Init — scan all CF7 forms on page
    // =========================================================================

    function init() {
        const forms = document.querySelectorAll( '.wpcf7 form' );
        const seenFormIds = {};

        forms.forEach( ( formEl ) => {
            const wrapper = formEl.closest( '.wpcf7' );
            const formId  = parseInt( wrapper?.dataset?.id || '0', 10 );
            if ( ! formId ) return;

            seenFormIds[ formId ] = ( seenFormIds[ formId ] || 0 );
            const instanceIndex   = seenFormIds[ formId ]++;

            createInstance( formEl, formId, instanceIndex );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', init );
} )();
```

- [ ] **Step 2: Verify validation fires in browser**

Configure a CF7 field as Required in the Validation tab. On the frontend, click the submit button without filling the field. Confirm the error message appears below the field. Fill in the field and confirm the error clears. Check browser console — no JS errors.

- [ ] **Step 3: Test focus and input events**

Click into a name field (focus). Type a number — confirm "must contain letters only" error appears immediately. Clear and type letters — confirm error clears.

- [ ] **Step 4: Commit**

```bash
git add assets/js/cfv-validation.js
git commit -m "feat: add client-side validation engine with focus/input/blur/submit event handling"
```

---

## Task 13: Character Counter

**Files:**
- Create: `assets/js/cfv-counter.js`

- [ ] **Step 1: Create cfv-counter.js**

```javascript
( function () {
    'use strict';

    function initCounters() {
        document.querySelectorAll( '.cfv-counter' ).forEach( ( counter ) => {
            const fieldName = counter.dataset.field;
            const max       = parseInt( counter.dataset.max, 10 );
            const format    = counter.dataset.format || 'count/max';

            const field = document.querySelector( `[name="${ fieldName }"]` );
            if ( ! field || ! max ) return;

            function update() {
                const len       = field.value.length;
                const remaining = max - len;
                const nearLimit = len >= max * 0.9;
                const atLimit   = len >= max;

                if ( format === 'remaining' ) {
                    counter.textContent = `${ remaining } characters remaining`;
                } else {
                    counter.textContent = `${ len } / ${ max }`;
                }

                counter.classList.toggle( 'cfv-counter--near',  nearLimit && ! atLimit );
                counter.classList.toggle( 'cfv-counter--over',  atLimit );
            }

            field.addEventListener( 'input', update );
            update(); // Set initial state.
        } );
    }

    document.addEventListener( 'DOMContentLoaded', initCounters );
} )();
```

- [ ] **Step 2: Verify counter in browser**

Configure a textarea in the Validation tab with max_length = 100 and counter_format = `count/max`. On the frontend, the counter should show `0 / 100`. Type 90 characters — counter should turn red. Reach 100 — counter should turn bold red.

- [ ] **Step 3: Commit**

```bash
git add assets/js/cfv-counter.js
git commit -m "feat: add character counter module with near-limit and over-limit colour states"
```

---

## Task 14: intl-tel-input Integration

**Files:**
- Create: `assets/vendor/intl-tel-input/intlTelInput.min.js`
- Create: `assets/vendor/intl-tel-input/intlTelInput.min.css`
- Create: `assets/vendor/intl-tel-input/flags.webp`
- Create: `assets/js/cfv-intl-phone.js`
- Modify: `includes/class-cfv-hooks.php` — `enqueue_frontend_assets`

- [ ] **Step 1: Download intl-tel-input vendor files**

```bash
# From the plugin root directory:
mkdir -p assets/vendor/intl-tel-input

# Download intl-tel-input 18.x release files
# Get the latest release from: https://github.com/jackocnr/intl-tel-input/releases
# Download and place:
#   build/js/intlTelInput.min.js  → assets/vendor/intl-tel-input/intlTelInput.min.js
#   build/css/intlTelInput.min.css → assets/vendor/intl-tel-input/intlTelInput.min.css
#   build/img/flags.webp           → assets/vendor/intl-tel-input/flags.webp
```

After downloading, update the path in intlTelInput.min.css so the flags.webp reference uses a relative path (it defaults to `../img/flags.webp` — change to `flags.webp` since all files are in the same directory).

- [ ] **Step 2: Create cfv-intl-phone.js**

```javascript
/* global cfvConfig, intlTelInput */
( function () {
    'use strict';

    // Global registry: instanceKey → { fieldName → iti instance }
    window.cfvItiInstances = window.cfvItiInstances || {};

    function init() {
        const forms    = document.querySelectorAll( '.wpcf7 form' );
        const seenIds  = {};

        forms.forEach( ( formEl ) => {
            const wrapper    = formEl.closest( '.wpcf7' );
            const formId     = parseInt( wrapper?.dataset?.id || '0', 10 );
            if ( ! formId ) return;

            seenIds[ formId ]   = ( seenIds[ formId ] || 0 );
            const instanceIndex = seenIds[ formId ]++;
            const instanceKey   = `${ formId }_${ instanceIndex }`;
            const formConfig    = window.cfvConfig?.forms?.[ formId ] || {};
            const fields        = formConfig.fields || {};

            window.cfvItiInstances[ instanceKey ] = {};

            Object.entries( fields ).forEach( ( [ fieldName, config ] ) => {
                if ( ! config.enable_intl ) return;

                const input = formEl.querySelector( `[name="${ fieldName }"]` );
                if ( ! input ) return;

                const iti = intlTelInput( input, {
                    initialCountry:    config.default_country === 'auto' ? 'auto' : config.default_country,
                    geoIpLookup:       config.default_country === 'auto'
                        ? ( cb ) => fetch( 'https://ipapi.co/json' ).then( r => r.json() ).then( d => cb( d.country_code ) ).catch( () => cb( 'us' ) )
                        : null,
                    utilsScript:       null,   // not needed for basic use
                    separateDialCode:  true,
                } );

                window.cfvItiInstances[ instanceKey ][ fieldName ] = iti;

                // Inject hidden input for full E.164 number.
                const hiddenInput = document.createElement( 'input' );
                hiddenInput.type  = 'hidden';
                hiddenInput.name  = `cfv_phone_full_${ fieldName }`;
                input.parentNode.insertBefore( hiddenInput, input.nextSibling );

                // On form submit, write full number to hidden input.
                formEl.addEventListener( 'submit', () => {
                    hiddenInput.value = iti.getNumber() || '';
                }, { capture: true } ); // capture: true so this fires before cfv-validation.js submit handler

                // On successful submission, reset the field.
                formEl.addEventListener( 'wpcf7mailsent', () => {
                    iti.setNumber( '' );
                    if ( config.default_country && config.default_country !== 'auto' ) {
                        iti.setCountry( config.default_country );
                    }
                    hiddenInput.value = '';
                } );
            } );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', init );
} )();
```

- [ ] **Step 3: Enqueue intl-tel-input assets conditionally**

In `class-cfv-hooks.php`, update `enqueue_frontend_assets` to add these lines before `wp_localize_script`:

```php
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
```

- [ ] **Step 4: Verify intl-tel-input in browser**

Enable intl-tel-input on a phone field in the Validation tab. On the frontend, confirm the country flag dropdown appears. Select a country, enter a number, submit. Confirm the hidden `cfv_phone_full_*` input contains the E.164 format number (inspect in DevTools → form data on submit).

- [ ] **Step 5: Commit**

```bash
git add assets/vendor/intl-tel-input/ assets/js/cfv-intl-phone.js includes/class-cfv-hooks.php
git commit -m "feat: add intl-tel-input integration for international phone fields"
```

---

## Task 15: CSS Styles

**Files:**
- Create: `assets/css/cfv-styles.css`

- [ ] **Step 1: Create cfv-styles.css**

```css
/* =============================================================================
   CF7 Validation Addon Styles
   ============================================================================= */

/* Hide CF7's native error tips — our cfv-error-tip replaces them */
.wpcf7-not-valid-tip {
    display: none !important;
}

/* =============================================================================
   Required asterisk
   ============================================================================= */
.cfv-required-asterisk {
    color: #d63638;
    font-weight: bold;
    margin-left: 2px;
}

/* =============================================================================
   Optional label
   ============================================================================= */
.cfv-optional-label {
    color: #757575;
    font-size: 0.85em;
    font-weight: normal;
    margin-left: 4px;
}

/* =============================================================================
   Inline error tip
   ============================================================================= */
.cfv-error-tip {
    display: none;
    color: #d63638;
    font-size: 0.875em;
    margin-top: 4px;
}

.cfv-error-tip:not(:empty) {
    display: block;
}

/* Highlight the field itself when invalid */
.wpcf7-form-control.wpcf7-not-valid,
input.cfv-invalid,
textarea.cfv-invalid,
select.cfv-invalid {
    border-color: #d63638;
    outline-color: #d63638;
}

/* =============================================================================
   Character counter
   ============================================================================= */
.cfv-counter {
    display: block;
    font-size: 0.8em;
    color: #757575;
    margin-top: 2px;
    text-align: right;
}

.cfv-counter--near {
    color: #e07000;
}

.cfv-counter--over {
    color: #d63638;
    font-weight: bold;
}

/* =============================================================================
   Disabled checkboxes / radios
   ============================================================================= */
.cfv-disabled {
    opacity: 0.45;
    pointer-events: none;
    cursor: not-allowed;
}

/* =============================================================================
   Submit button — loading state
   ============================================================================= */
[type="submit"].cfv-loading {
    position: relative;
    color: transparent;
    pointer-events: none;
}

[type="submit"].cfv-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: cfv-spin 0.6s linear infinite;
}

@keyframes cfv-spin {
    to { transform: rotate( 360deg ); }
}

/* =============================================================================
   Validation Tab (admin)
   ============================================================================= */
.cfv-validation-tab {
    padding: 16px 0;
}

.cfv-validation-tab h3 {
    margin-top: 24px;
    margin-bottom: 8px;
    font-size: 1rem;
    font-weight: 600;
}

.cfv-global-settings {
    background: #f6f7f7;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 24px;
}

.cfv-fields-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cfv-field-row {
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

.cfv-field-row__header {
    background: #f6f7f7;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.cfv-field-row__name code {
    font-size: 0.95em;
}

.cfv-field-row__type {
    color: #757575;
    font-size: 0.85em;
}

.cfv-field-row__cf-required {
    background: #e0f0ff;
    color: #007cba;
    font-size: 0.75em;
    padding: 1px 6px;
    border-radius: 3px;
}

.cfv-field-row__body {
    padding: 14px;
    display: grid;
    grid-template-columns: repeat( auto-fill, minmax( 280px, 1fr ) );
    gap: 12px;
}

.cfv-rules-group {
    display: contents;
}

.cfv-rules-note {
    grid-column: 1 / -1;
    font-size: 0.85em;
    color: #757575;
    margin: 0;
}

/* =============================================================================
   intl-tel-input path override
   ============================================================================= */
.iti__flag-container {
    z-index: 10;
}
```

- [ ] **Step 2: Verify styles in browser**

Frontend: check red asterisk, optional label colour, inline errors, counter colours, spinner on submit. Admin: check Validation tab layout — fields list, global settings panel, rule inputs grid.

- [ ] **Step 3: Commit**

```bash
git add assets/css/cfv-styles.css
git commit -m "feat: add plugin CSS for errors, asterisk, optional label, counter, spinner, and admin tab"
```

---

## Task 16: PHP Validator Class

**Files:**
- Create: `includes/class-cfv-validator.php`

- [ ] **Step 1: Create the PHP validator class**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFV_Validator {

    /**
     * Validate a CF7 submission against the form's config.
     *
     * @param int   $form_id    CF7 form post ID.
     * @param array $submission Raw $_POST data.
     * @param array $files      Raw $_FILES data.
     * @return array Keyed by field name, values are error message strings. Empty = all valid.
     */
    public static function validate( int $form_id, array $submission, array $files = [] ): array {
        $config  = CFV_Config::get( $form_id );
        $fields  = $config['fields'] ?? [];
        $errors  = [];

        foreach ( $fields as $field_name => $field_config ) {
            $type  = $field_config['type'] ?? 'text';
            $label = ! empty( $field_config['label'] )
                ? $field_config['label']
                : CFV_Config::generate_label( $field_name );

            // Determine the submitted value.
            // For phone with intl-tel-input, use the hidden full-number field.
            if ( $type === 'tel' && ! empty( $field_config['enable_intl'] ) ) {
                $value = sanitize_text_field( wp_unslash( $submission[ "cfv_phone_full_$field_name" ] ?? '' ) );
            } else {
                $value = sanitize_textarea_field( wp_unslash( $submission[ $field_name ] ?? '' ) );
            }

            $error = self::validate_field( $field_name, $value, $label, $field_config, $submission, $files );
            if ( $error ) {
                $errors[ $field_name ] = $error;
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------

    private static function validate_field(
        string $field_name,
        string $value,
        string $label,
        array  $config,
        array  $submission,
        array  $files
    ): string {
        $type     = $config['type']     ?? 'text';
        $required = ! empty( $config['required'] );

        // Required check.
        if ( $required ) {
            if ( $type === 'checkbox' || $type === 'radio' ) {
                $group_values = (array) ( $submission[ $field_name ] ?? $submission[ "{$field_name}[]" ] ?? [] );
                if ( empty( $group_values ) ) {
                    return "$label — please select at least one option";
                }
            } elseif ( $type === 'select' ) {
                $placeholder = $config['placeholder_value'] ?? '';
                if ( $value === $placeholder || $value === '' ) {
                    return "$label is required";
                }
            } elseif ( $type === 'file' ) {
                if ( empty( $files[ $field_name ]['name'] ) || $files[ $field_name ]['error'] === UPLOAD_ERR_NO_FILE ) {
                    return "$label is required";
                }
            } else {
                if ( trim( $value ) === '' ) {
                    return "$label is required";
                }
            }
        }

        // Skip further checks if empty and not required.
        if ( trim( $value ) === '' && $type !== 'file' ) return '';

        // Email.
        if ( $type === 'email' ) {
            $trimmed = trim( $value );
            if ( ! filter_var( $trimmed, FILTER_VALIDATE_EMAIL ) ) {
                return "$label must be a valid email address";
            }
        }

        // URL.
        if ( $type === 'url' ) {
            $trimmed = trim( $value );
            if ( ! filter_var( $trimmed, FILTER_VALIDATE_URL ) || ! preg_match( '/^https?:\/\//i', $trimmed ) ) {
                return "$label must be a valid URL including http:// or https://";
            }
        }

        // Phone.
        if ( $type === 'tel' ) {
            if ( ! empty( $config['enable_intl'] ) ) {
                // E.164 format: + followed by 8-15 digits.
                if ( ! preg_match( '/^\+\d{8,15}$/', $value ) ) {
                    return "$label must be a valid phone number";
                }
            } else {
                $digits = preg_replace( '/[\s\-()+]/', '', $value );
                $min    = (int) ( $config['min_length'] ?? 7 );
                $max    = (int) ( $config['max_length'] ?? 15 );
                if ( ! ctype_digit( $digits ) || strlen( $digits ) < $min || strlen( $digits ) > $max ) {
                    return "$label must be a valid phone number";
                }
            }
        }

        // Number.
        if ( $type === 'number' ) {
            if ( ! is_numeric( $value ) ) {
                return "$label must contain numbers only";
            }
            $num = floatval( $value );
            if ( ! ( $config['allow_negative'] ?? false ) && $num < 0 ) {
                return "$label must be a positive number";
            }
            if ( ! ( $config['allow_zero'] ?? true ) && $num === 0.0 ) {
                return "$label cannot be zero";
            }
            if ( $config['min_value'] !== '' && $config['min_value'] !== null && $num < floatval( $config['min_value'] ) ) {
                return "$label must be at least {$config['min_value']}";
            }
            if ( $config['max_value'] !== '' && $config['max_value'] !== null && $num > floatval( $config['max_value'] ) ) {
                return "$label must be no more than {$config['max_value']}";
            }
        }

        // Text / name / textarea.
        if ( in_array( $type, [ 'text', 'name', 'textarea' ], true ) ) {
            if ( ! empty( $config['alpha_only'] ) && ! preg_match( '/^[a-zA-Z\s]+$/', $value ) ) {
                return "$label must contain letters only";
            }
            if ( ! empty( $config['no_leading_spaces'] ) && ltrim( $value ) !== $value ) {
                return "$label must not start with a space";
            }
            if ( ! empty( $config['min_length'] ) && mb_strlen( trim( $value ) ) < (int) $config['min_length'] ) {
                return "$label must be at least {$config['min_length']} characters";
            }
            if ( ! empty( $config['max_length'] ) && mb_strlen( trim( $value ) ) > (int) $config['max_length'] ) {
                return "$label must be no more than {$config['max_length']} characters";
            }
            if ( isset( $config['allow_special_chars'] ) && ! $config['allow_special_chars'] ) {
                if ( preg_match( '/[!@#$%^&*()\[\]{};:\'\"\\\\|<>?\/`~=+]/', $value ) ) {
                    return "$label must not contain special characters";
                }
            }
            if ( $type === 'textarea' && ! empty( $config['security_sanitize'] ) ) {
                $stripped = self::strip_dangerous_patterns( $value );
                if ( $stripped !== $value ) {
                    return "$label contains invalid characters";
                }
            }
        }

        // File.
        if ( $type === 'file' && ! empty( $files[ $field_name ] ) ) {
            $file = $files[ $field_name ];
            if ( $file['error'] === UPLOAD_ERR_OK ) {
                $allowed_types = array_map( 'trim', explode( ',', $config['allowed_types'] ?? 'jpg,jpeg,png,pdf' ) );
                $ext           = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, $allowed_types, true ) ) {
                    return "$label accepts " . implode( ', ', $allowed_types ) . " files only";
                }
                $max_bytes = ( (float) ( $config['max_size_mb'] ?? 5 ) ) * 1024 * 1024;
                if ( $file['size'] > $max_bytes ) {
                    return "$label must be under {$config['max_size_mb']}MB";
                }
            }
        }

        return '';
    }

    /**
     * Strip code-like and dangerous patterns from message field content.
     * Returns the stripped string — caller compares with original to detect changes.
     */
    public static function strip_dangerous_patterns( string $value ): string {
        $value = strip_tags( $value );
        $patterns = [
            '/<script[\s\S]*?<\/script>/i',
            '/<\?php/i',
            '/<\?=/i',
            '/eval\s*\(/i',
            '/alert\s*\(/i',
            '/\b(DROP|INSERT|SELECT|UPDATE|DELETE|CREATE|ALTER|EXEC)\b/i',
            '/--/',
            '/\{[^}]*\}/',
        ];
        foreach ( $patterns as $pattern ) {
            $value = preg_replace( $pattern, '', $value );
        }
        return $value;
    }
}
```

- [ ] **Step 2: Verify class loads without errors**

Reload WP admin with `WP_DEBUG` on. Check `debug.log` — no PHP errors from `CFV_Validator`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-cfv-validator.php
git commit -m "feat: add CFV_Validator class with full PHP server-side validation ruleset"
```

---

## Task 17: Server-side Validation Hooks + CF7 Error Override

**Files:**
- Modify: `includes/class-cfv-hooks.php` — `validate_submission` and `override_error_response`

- [ ] **Step 1: Implement validate_submission**

This runs before CF7 sends the email. If our validator finds errors, we abort the submission and store the errors for the response override.

Replace `validate_submission` in `class-cfv-hooks.php`:

```php
// Store errors between validate_submission and override_error_response.
private static array $validation_errors = [];

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
```

- [ ] **Step 2: Implement override_error_response**

Replace `override_error_response` in `class-cfv-hooks.php`:

```php
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
```

- [ ] **Step 3: Test server-side validation**

Disable JavaScript in browser (DevTools → Settings → Disable JavaScript). Submit a CF7 form with a required field empty. Confirm the CF7 error response fires and the custom error message (not CF7's default) appears. Re-enable JavaScript.

- [ ] **Step 4: Verify CF7 default errors are hidden**

With JS enabled, trigger a validation error. Inspect the DOM — confirm `.wpcf7-not-valid-tip` has `display: none` from our CSS, and only `.cfv-error-tip` is visible.

- [ ] **Step 5: Commit**

```bash
git add includes/class-cfv-hooks.php
git commit -m "feat: implement server-side validation hook and CF7 error response override"
```

---

## Task 18: Form Duplication Meta Copy

**Files:**
- Modify: `includes/class-cfv-hooks.php` — `maybe_copy_config_on_duplicate`

- [ ] **Step 1: Implement form duplication handler**

Replace `maybe_copy_config_on_duplicate` in `class-cfv-hooks.php`:

```php
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
```

- [ ] **Step 2: Verify duplication copies config**

Install the "Duplicate Post" plugin (or use WPCF7's own "Copy" button if available). Duplicate a CF7 form that has validation settings configured. Open the duplicated form's Validation tab — confirm the same settings appear.

- [ ] **Step 3: Commit**

```bash
git add includes/class-cfv-hooks.php
git commit -m "feat: copy validation config meta when a CF7 form is duplicated"
```

---

## Task 19: File Preview and Upload Progress Indicator

**Files:**
- Modify: `assets/js/cfv-validation.js` — add file preview and progress logic
- Modify: `assets/css/cfv-styles.css` — add progress bar styles

- [ ] **Step 1: Add file preview logic to cfv-validation.js**

Inside the `createInstance` function, after the per-field event listeners block, add:

```javascript
// File preview and upload progress.
Object.keys( fieldConfigs ).forEach( ( name ) => {
    const config  = fieldConfigs[ name ];
    if ( config.type !== 'file' || ! config.show_preview ) return;

    const fieldEl = getFieldEl( name );
    if ( ! fieldEl ) return;

    // Create preview container.
    const preview = document.createElement( 'div' );
    preview.className = 'cfv-file-preview';
    preview.dataset.field = name;
    fieldEl.parentNode.insertBefore( preview, fieldEl.nextSibling );

    fieldEl.addEventListener( 'change', () => {
        preview.innerHTML = '';
        const files = Array.from( fieldEl.files || [] );
        files.forEach( ( file ) => {
            const item = document.createElement( 'div' );
            item.className = 'cfv-file-preview__item';

            if ( file.type.startsWith( 'image/' ) ) {
                const img = document.createElement( 'img' );
                img.src = URL.createObjectURL( file );
                img.className = 'cfv-file-preview__img';
                item.appendChild( img );
            } else {
                const icon = document.createElement( 'span' );
                icon.className = 'cfv-file-preview__icon';
                icon.textContent = '📄';
                item.appendChild( icon );
            }

            const label = document.createElement( 'span' );
            label.className = 'cfv-file-preview__name';
            label.textContent = file.name;
            item.appendChild( label );

            preview.appendChild( item );
        } );
    } );
} );
```

- [ ] **Step 2: Add upload progress bar**

In the submit handler inside `createInstance`, after the button disable block, add:

```javascript
// Inject progress bar if not already present.
let progressBar = formEl.querySelector( '.cfv-upload-progress' );
if ( ! progressBar ) {
    progressBar = document.createElement( 'div' );
    progressBar.className = 'cfv-upload-progress';
    const inner = document.createElement( 'div' );
    inner.className = 'cfv-upload-progress__bar';
    progressBar.appendChild( inner );
    formEl.appendChild( progressBar );
}

// Hook into CF7's XHR to track upload progress.
// CF7 dispatches wpcf7beforesubmit — we intercept XMLHttpRequest.prototype.open.
const origOpen = XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open = function ( ...args ) {
    this.upload.addEventListener( 'progress', ( e ) => {
        if ( e.lengthComputable ) {
            const pct = Math.round( ( e.loaded / e.total ) * 100 );
            const bar = formEl.querySelector( '.cfv-upload-progress__bar' );
            if ( bar ) bar.style.width = pct + '%';
        }
    } );
    this.upload.addEventListener( 'loadend', () => {
        // Reset after a short delay.
        setTimeout( () => {
            const bar = formEl.querySelector( '.cfv-upload-progress__bar' );
            if ( bar ) bar.style.width = '0%';
        }, 1000 );
        XMLHttpRequest.prototype.open = origOpen; // Restore.
    } );
    origOpen.apply( this, args );
};
```

- [ ] **Step 3: Add preview and progress styles to cfv-styles.css**

Append to `assets/css/cfv-styles.css`:

```css
/* =============================================================================
   File preview
   ============================================================================= */
.cfv-file-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.cfv-file-preview__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    max-width: 80px;
}

.cfv-file-preview__img {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.cfv-file-preview__icon {
    font-size: 2rem;
}

.cfv-file-preview__name {
    font-size: 0.72em;
    word-break: break-all;
    text-align: center;
    color: #555;
}

/* =============================================================================
   Upload progress bar
   ============================================================================= */
.cfv-upload-progress {
    width: 100%;
    height: 4px;
    background: #eee;
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}

.cfv-upload-progress__bar {
    height: 100%;
    width: 0%;
    background: #007cba;
    border-radius: 2px;
    transition: width 0.2s ease;
}
```

- [ ] **Step 4: Verify preview and progress in browser**

Enable `show_preview` on a file field in the Validation tab. On the frontend, select an image file — confirm the preview appears below the field. Select a non-image file — confirm a document icon and filename appear. Submit the form and confirm the progress bar moves.

- [ ] **Step 5: Commit**

```bash
git add assets/js/cfv-validation.js assets/css/cfv-styles.css
git commit -m "feat: add file preview thumbnails and upload progress indicator"
```

---

## Task 20: End-to-End QA (maps to QA Checklist — Sheet1.csv)

At this point all code is in place. Work through the QA checklist at `QA Checklist - Sheet1.csv`.

- [ ] **Step 1: Run through mandatory fields tests**
- Required field empty → correct error message shown
- Asterisk visible, red
- Optional label toggle works

- [ ] **Step 2: Run through First Name / Last Name tests**
- Leading space blocked (focus + input events)
- Non-alpha character rejected
- Min 2 chars enforced for first name
- Max 56 chars enforced for both

- [ ] **Step 3: Run through Email tests**
- Invalid format rejected
- Leading/trailing whitespace trimmed and blocked

- [ ] **Step 4: Run through Dropdown tests**
- Default placeholder option selected → required error
- Disabled options cannot be selected

- [ ] **Step 5: Run through Checkbox / Radio tests**
- Required group with none selected → error on submit
- Disabled checkboxes greyed out and unclickable

- [ ] **Step 6: Run through URL tests**
- Missing `http://` rejected
- Valid URL accepted

- [ ] **Step 7: Run through Numeric field tests**
- Non-numeric input blocked
- Negative value rejected when disallowed
- Zero rejected when disallowed
- Min/max value enforced

- [ ] **Step 8: Run through Text field tests**
- Special character blocking
- Consecutive whitespace collapsed on blur
- Emoji blocking when disabled

- [ ] **Step 9: Run through Phone tests**
- Standard phone: invalid format rejected
- intl-tel-input: flag dropdown works, full E.164 number submitted to email
- Reset on submission

- [ ] **Step 10: Run through File Upload tests**
- Wrong file type → error
- Oversized file → error
- Preview shown when enabled

- [ ] **Step 11: Run through Message field tests**
- Max 1500 chars enforced
- Character counter visible and accurate
- Scrollbar appears at max-height
- Code patterns blocked

- [ ] **Step 12: Run through Submit button tests**
- Double-click prevented (button disabled after first click)
- Spinner visible during submission
- Fields reset after successful submission

- [ ] **Step 13: Test same form twice on one page**
- Add the same CF7 shortcode twice on a test page
- Both forms validate independently — errors in form 1 don't affect form 2

- [ ] **Step 14: Test server-side validation with JS disabled**
- Disable JS, submit invalid data, confirm PHP validator catches all errors

- [ ] **Step 15: Final commit**

```bash
git add -A
git commit -m "feat: CF7 Validation Addon v1.0.0 — all validation rules implemented and QA verified"
```
