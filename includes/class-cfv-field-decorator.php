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
            //
            // CF7's standard HTML wraps the label around the field control:
            //   <label>Label text <span class="wpcf7-form-control-wrap" data-name="field-name">...</span></label>
            // There is NO `for` attribute, so matching on `for="field-name"` never works.
            // Instead, match a <label> whose content contains the CF7 wrap span with data-name="field-name",
            // then inject between the label text and the wrap span.
            if ( $required || $show_optional ) {
                $quoted        = preg_quote( $field_name, '/' );
                $inject        = $required
                    ? '<span class="cfv-required-asterisk" aria-hidden="true">*</span>'
                    : '<span class="cfv-optional-label">(Optional)</span>';

                // Pattern: <label ...> [label text] <span ... data-name="field-name" ...>
                // Capture: $1 = <label...>, $2 = text before wrap span, $3 = the wrap span opening.
                // Inject the badge before any trailing <br>/whitespace so it sits inline
                // with the label text rather than on a new line after the <br>.
                $label_pattern = '/(<label\b[^>]*>)((?:(?!<\/label>)[\s\S])*?)(<span\b[^>]*\bdata-name=["\']?' . $quoted . '["\']?)/si';
                $html = preg_replace_callback( $label_pattern, function ( $m ) use ( $inject ) {
                    $text     = preg_replace( '/(\s*<br\s*\/?>\s*)+$/i', '', $m[2] );
                    $trailing = substr( $m[2], strlen( $text ) );
                    return $m[1] . $text . ' ' . $inject . $trailing . $m[3];
                }, $html );
            }

            // --- Inject empty error span after the field input/textarea/select ---
            $error_span = '<span class="cfv-error-tip" data-field="' . esc_attr( $field_name ) . '" role="alert" aria-live="polite"></span>';

            // Textarea has separate open/close tags — inject after </textarea> to avoid
            // the span appearing as literal text content inside the element.
            // textarea and select have open+close tags — match the full element and
            // inject after the closing tag to avoid the span landing inside as content.
            // input is self-closing so it can be matched by its opening tag alone.
            $quoted = preg_quote( $field_name, '/' );

            if ( preg_match( '/(<textarea\b[^>]*\bname=["\']?' . $quoted . '["\']?)/si', $html ) ) {
                $html = preg_replace(
                    '/(<textarea\b[^>]*\bname=["\']?' . $quoted . '["\']?[^>]*>[\s\S]*?<\/textarea>)/si',
                    '$1' . $error_span,
                    $html
                );
            } elseif ( preg_match( '/(<select\b[^>]*\bname=["\']?' . $quoted . '["\']?)/si', $html ) ) {
                $html = preg_replace(
                    '/(<select\b[^>]*\bname=["\']?' . $quoted . '["\']?[^>]*>[\s\S]*?<\/select>)/si',
                    '$1' . $error_span,
                    $html
                );
            } else {
                // input — self-closing, inject after the opening tag.
                $html = preg_replace(
                    '/(<input\b[^>]*\bname=["\']?' . $quoted . '["\']?[^>]*\/?>)/si',
                    '$1' . $error_span,
                    $html
                );
            }

            // --- Inject counter element below textarea/text fields with max_length ---
            if ( $counter_format !== 'off' && $max_length > 0 ) {
                $counter = '<span class="cfv-counter" data-field="' . esc_attr( $field_name ) . '" data-max="' . esc_attr( $max_length ) . '" data-format="' . esc_attr( $counter_format ) . '"></span>';
                // Insert after the error span we just added.
                $html = str_replace( $error_span, $error_span . $counter, $html );
            }

            // --- Apply maxlength and max-height attributes to textarea ---
            if ( $max_length > 0 || $max_height > 0 ) {
                $ta_pattern = '/(<textarea\b[^>]*\bname=["\']?' . preg_quote( $field_name, '/' ) . '["\']?[^>]*)(>)/si';
                $html = preg_replace_callback( $ta_pattern, function ( $m ) use ( $max_length, $max_height ) {
                    // Strip any maxlength CF7 already put on the element so ours wins.
                    $tag = preg_replace( '/\s+maxlength=["\']?\d+["\']?/i', '', $m[1] );
                    $attrs = '';
                    if ( $max_length > 0 ) {
                        $attrs .= ' maxlength="' . esc_attr( $max_length ) . '"';
                    }
                    if ( $max_height > 0 ) {
                        $attrs .= ' style="max-height:' . esc_attr( $max_height ) . 'px;overflow-y:auto;"';
                    }
                    return $tag . $attrs . $m[2];
                }, $html );
            }
        }

        return $html;
    }
}
