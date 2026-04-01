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
