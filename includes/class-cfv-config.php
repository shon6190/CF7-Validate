<?php
/**
 * Configuration class for CF7 Validate Pro.
 *
 * Handles reading, writing, and merging of per-form validation config
 * stored as post meta on CF7 form posts.
 *
 * @package CF7_Validate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
                'required'            => false,
                'min_length'          => '',
                'max_length'          => '',
                'alpha_only'          => false,
                'no_leading_spaces'   => false,
                'allow_special_chars' => true,
                'allow_emoji'         => true,
                'collapse_whitespace' => true,
                'input_mask'          => '',
                'counter_format'      => 'off',
            ],
            'name' => [
                'required'            => false,
                'min_length'          => 2,
                'max_length'          => 56,
                'alpha_only'          => true,
                'no_leading_spaces'   => true,
                'allow_special_chars' => false,
                'allow_emoji'         => false,
                'collapse_whitespace' => true,
                'input_mask'          => '',
                'counter_format'      => 'off',
            ],
            'email' => [
                'required'        => false,
                'trim_whitespace' => true,
            ],
            'tel' => [
                'required'        => false,
                'min_length'      => 7,
                'max_length'      => 15,
                'enable_intl'     => false,
                'default_country' => 'auto',
            ],
            'number' => [
                'required'       => false,
                'min_value'      => '',
                'max_value'      => '',
                'allow_negative' => false,
                'allow_zero'     => true,
            ],
            'url' => [
                'required' => false,
            ],
            'textarea' => [
                'required'           => false,
                'max_length'         => 1500,
                'counter_format'     => 'count/max',
                'max_height'         => 200,
                'security_sanitize'  => true,
            ],
            'select' => [
                'required'          => false,
                'placeholder_value' => '',
            ],
            'checkbox' => [
                'required'      => false,
                'default_state' => [],
            ],
            'radio' => [
                'required' => false,
            ],
            'file' => [
                'required'       => false,
                'allowed_types'  => 'jpg,jpeg,png,pdf',
                'max_size_mb'    => 5,
                'allow_multiple' => false,
                'show_preview'   => false,
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
     * Get the full config for a form, merging saved values over global defaults.
     *
     * @param int $form_id CF7 form post ID.
     * @return array { global: array, fields: array }
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
     *
     * @param int   $form_id CF7 form post ID.
     * @param array $config  Config array with 'global' and 'fields' keys.
     */
    public static function save( int $form_id, array $config ): void {
        $sanitized = [
            'global' => [
                'show_optional_label' => ! empty( $config['global']['show_optional_label'] ),
            ],
            'fields' => [],
        ];

        foreach ( $config['fields'] ?? [] as $field_name => $field_config ) {
            $clean_name                         = sanitize_key( $field_name );
            $sanitized['fields'][ $clean_name ] = self::sanitize_field_config( (array) $field_config );
        }

        update_post_meta( $form_id, self::META_KEY, wp_json_encode( $sanitized ) );
    }

    /**
     * Sanitize an individual field config array.
     *
     * @param array $config Raw field config from JS.
     * @return array Sanitized config.
     */
    private static function sanitize_field_config( array $config ): array {
        $out = [];

        foreach ( $config as $key => $value ) {
            $key = sanitize_key( (string) $key );

            if ( is_bool( $value ) ) {
                $out[ $key ] = (bool) $value;
            } elseif ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
                $out[ $key ] = absint( $value );
            } elseif ( is_float( $value ) ) {
                $out[ $key ] = (float) $value;
            } elseif ( is_string( $value ) ) {
                $out[ $key ] = sanitize_text_field( $value );
            } elseif ( is_array( $value ) ) {
                $out[ $key ] = array_map( 'sanitize_text_field', $value );
            }
            // Silently drop null and other types.
        }

        return $out;
    }

    /**
     * Copy validation config from one form to another (used on form duplication).
     *
     * @param int $source_form_id Source CF7 form post ID.
     * @param int $target_form_id Target CF7 form post ID.
     */
    public static function copy( int $source_form_id, int $target_form_id ): void {
        $raw = get_post_meta( $source_form_id, self::META_KEY, true );
        if ( $raw ) {
            update_post_meta( $target_form_id, self::META_KEY, $raw );
        }
    }
}
