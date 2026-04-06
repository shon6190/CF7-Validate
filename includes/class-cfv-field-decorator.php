<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFV_Field_Decorator {

    /**
     * Post-process rendered CF7 form HTML.
     * Injects: red asterisks on required fields, optional labels on non-required fields,
     * empty error spans below each field, counter elements on length-limited fields.
     */
    public static function decorate( string $html, int $form_id ): string {
        $config        = CFV_Config::get( $form_id );
        $fields        = $config['fields'] ?? [];
        $global        = $config['global'] ?? [];
        $show_optional = ! empty( $global['show_optional_label'] );

        foreach ( $fields as $field_name => $field_config ) {
            $required       = ! empty( $field_config['required'] );
            $counter_format = $field_config['counter_format'] ?? 'off';
            $max_length     = ! empty( $field_config['max_length'] ) ? (int) $field_config['max_length'] : 0;
            $max_height     = ! empty( $field_config['max_height'] ) ? (int) $field_config['max_height'] : 0;
            $quoted         = preg_quote( $field_name, '/' );

            // ----------------------------------------------------------------
            // Inject asterisk or (Optional) inside the wrapping <label>.
            //
            // CF7 standard output:
            //   <label>Label text<br>
            //     <span class="wpcf7-form-control-wrap" data-name="field-name">...</span>
            //   </label>
            // Inject the badge before any trailing <br>/whitespace so it sits
            // inline with the label text, not on a new line after the <br>.
            // ----------------------------------------------------------------
            if ( $required || $show_optional ) {
                $inject = $required
                    ? '<span class="cfv-required-asterisk" aria-hidden="true">*</span>'
                    : '<span class="cfv-optional-label">(Optional)</span>';

                // Try to inject inside a wrapping <label> that contains the control-wrap span.
                // This works for standard text/textarea/select fields where CF7 wraps the
                // label around the entire field including the <span data-name="...">.
                $label_pattern = '/(<label\b[^>]*>)((?:(?!<\/label>)[\s\S])*?)(<span\b[^>]*\bdata-name=["\']?' . $quoted . '["\']?)/si';
                $html_before   = $html;
                $html          = preg_replace_callback( $label_pattern, function ( $m ) use ( $inject ) {
                    $text     = preg_replace( '/(\s*<br\s*\/?>\s*)+$/i', '', $m[2] );
                    $trailing = substr( $m[2], strlen( $text ) );
                    return $m[1] . $text . ' ' . $inject . $trailing . $m[3];
                }, $html );

                // Fallback for radio/checkbox groups: CF7 renders no outer wrapping label —
                // only per-option labels inside <span class="wpcf7-list-item">.
                // Inject the badge immediately before the control-wrap opening tag so it
                // appears as a group label above/beside the options.
                if ( $html === $html_before ) {
                    // Consume any <br> + whitespace that immediately precedes the
                    // control-wrap span so the badge stays inline, not on its own line.
                    $wrap_pattern = '/(\s*<br\s*\/?>\s*)?(<span\b[^>]*\bdata-name=["\']?' . $quoted . '["\']?[^>]*>)/si';
                    $html         = preg_replace( $wrap_pattern, ' ' . $inject . ' $2', $html, 1 );
                }
            }

            // ----------------------------------------------------------------
            // Inject empty error span after the CF7 control-wrap span.
            //
            // CF7 wraps EVERY field type in:
            //   <span class="wpcf7-form-control-wrap" data-name="field-name">
            //     [input | textarea | select | checkbox group | radio group | file]
            //   </span>
            //
            // Injecting after this outer span works universally — no more
            // special-casing per element type. Regex cannot count nested spans,
            // so we scan character-by-character after the opening tag.
            // ----------------------------------------------------------------
            $error_span = '<span class="cfv-error-tip" data-field="' . esc_attr( $field_name ) . '" role="alert" aria-live="polite"></span>';
            $html       = self::inject_after_control_wrap( $html, $field_name, $error_span );

            // ----------------------------------------------------------------
            // Counter element (appended after the error span already injected).
            // ----------------------------------------------------------------
            if ( $counter_format !== 'off' && $max_length > 0 ) {
                $counter = '<span class="cfv-counter"'
                    . ' data-field="' . esc_attr( $field_name ) . '"'
                    . ' data-max="' . esc_attr( $max_length ) . '"'
                    . ' data-format="' . esc_attr( $counter_format ) . '"'
                    . '></span>';
                $html = str_replace( $error_span, $error_span . $counter, $html );
            }

            // ----------------------------------------------------------------
            // Apply maxlength (overriding CF7's own) and max-height on textarea.
            // ----------------------------------------------------------------
            if ( $max_length > 0 || $max_height > 0 ) {
                $html = preg_replace_callback(
                    '/(<textarea\b[^>]*\bname=["\']?' . $quoted . '["\']?[^>]*)(>)/si',
                    function ( $m ) use ( $max_length, $max_height ) {
                        $tag   = preg_replace( '/\s+maxlength=["\']?\d+["\']?/i', '', $m[1] );
                        $attrs = '';
                        if ( $max_length > 0 ) {
                            $attrs .= ' maxlength="' . esc_attr( $max_length ) . '"';
                        }
                        if ( $max_height > 0 ) {
                            $attrs .= ' style="max-height:' . esc_attr( $max_height ) . 'px;overflow-y:auto;"';
                        }
                        return $tag . $attrs . $m[2];
                    },
                    $html
                );
            }
        }

        return $html;
    }

    /**
     * Inject $inject immediately after the closing </span> of the CF7
     * control-wrap span (<span ... data-name="field-name">) by counting
     * nested <span> / </span> pairs — regex alone cannot handle nesting.
     *
     * Works for all CF7 field types: text, textarea, select, checkbox groups,
     * radio groups, and file inputs.
     */
    private static function inject_after_control_wrap( string $html, string $field_name, string $inject ): string {
        $quoted = preg_quote( $field_name, '/' );

        // Find the opening control-wrap span for this field.
        if ( ! preg_match(
            '/<span\b[^>]*\bdata-name=["\']?' . $quoted . '["\']?[^>]*>/si',
            $html,
            $match,
            PREG_OFFSET_CAPTURE
        ) ) {
            return $html; // Field not in HTML — nothing to do.
        }

        $open_tag_end = $match[0][1] + strlen( $match[0][0] ); // position after opening tag
        $pos          = $open_tag_end;
        $depth        = 1;
        $len          = strlen( $html );

        while ( $depth > 0 && $pos < $len ) {
            $next_open  = stripos( $html, '<span',  $pos );
            $next_close = stripos( $html, '</span', $pos );

            if ( $next_open !== false && ( $next_close === false || $next_open < $next_close ) ) {
                // Another opening span comes first — go deeper.
                $depth++;
                $pos = $next_open + 5; // skip '<span'
            } elseif ( $next_close !== false ) {
                $depth--;
                $close_end = stripos( $html, '>', $next_close ) + 1;
                if ( $depth === 0 ) {
                    // This is the matching closing tag — inject right after it.
                    return substr( $html, 0, $close_end ) . $inject . substr( $html, $close_end );
                }
                $pos = $close_end;
            } else {
                break; // Malformed HTML — bail.
            }
        }

        return $html;
    }
}
