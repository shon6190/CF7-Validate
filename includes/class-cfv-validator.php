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
                $raw   = wp_unslash( $submission[ "cfv_phone_full_$field_name" ] ?? '' );
                $value = sanitize_text_field( $raw );
            } else {
                // CF7 returns select/checkbox/radio values as arrays; unwrap to a
                // scalar string for all non-checkbox/radio types (BUG-005).
                $raw_input = wp_unslash( $submission[ $field_name ] ?? $submission[ "{$field_name}[]" ] ?? '' );
                if ( $type !== 'checkbox' && $type !== 'radio' && is_array( $raw_input ) ) {
                    $raw_input = $raw_input[0] ?? '';
                }
                $raw   = is_array( $raw_input ) ? '' : (string) $raw_input;
                $value = sanitize_textarea_field( $raw );
            }

            $error = self::validate_field( $field_name, $raw, $value, $label, $field_config, $submission, $files );
            if ( $error ) {
                $errors[ $field_name ] = $error;
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------

    private static function validate_field(
        string $field_name,
        string $raw,
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
                // BUG-002: filter out empty strings before the empty-check so that
                // an empty-string POST value doesn't satisfy required (BUG-002).
                $group_values = (array) ( $submission[ $field_name ] ?? $submission[ "{$field_name}[]" ] ?? [] );
                $group_values = array_filter( $group_values, fn( $v ) => $v !== '' );
                if ( empty( $group_values ) ) {
                    return "Please select at least one $label";
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

        // Leading / trailing space check — use $raw (pre-sanitize) because
        // sanitize_textarea_field trims the value before we get to check it (BUG-003).
        $text_based = [ 'text', 'name', 'email', 'tel', 'textarea', 'url', 'number' ];
        if ( in_array( $type, $text_based, true ) && $raw !== trim( $raw ) ) {
            return "$label must not have leading or trailing spaces";
        }

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
            if ( isset( $config['allow_emoji'] ) && ! $config['allow_emoji'] ) {
                if ( preg_match( '/[\x{1F000}-\x{1FFFF}]/u', $value ) ) {
                    return "$label must not contain emoji";
                }
            }
            if ( $type === 'textarea' && ! empty( $config['security_sanitize'] ) ) {
                // BUG-001: check $raw so that <script> tags aren't silently stripped
                // by sanitize_textarea_field before we get to inspect them.
                $stripped = self::strip_dangerous_patterns( $raw );
                if ( $stripped !== $raw ) {
                    return "$label contains invalid characters";
                }
            }
        }

        // File.
        if ( $type === 'file' && ! empty( $files[ $field_name ] ) ) {
            $file          = $files[ $field_name ];
            $allowed_types = array_map( 'trim', explode( ',', $config['allowed_types'] ?? 'jpg,jpeg,png,pdf' ) );
            $max_bytes     = ( (float) ( $config['max_size_mb'] ?? 5 ) ) * 1024 * 1024;

            // Normalise to a list of files — CF7 sends a single-file array for
            // one upload but a nested array for multiple files.
            $file_list = is_array( $file['name'] )
                ? array_map( fn( $i ) => [
                    'name'  => $file['name'][ $i ],
                    'error' => $file['error'][ $i ],
                    'size'  => $file['size'][ $i ],
                  ], array_keys( $file['name'] ) )
                : [ $file ];

            foreach ( $file_list as $f ) {
                if ( $f['error'] !== UPLOAD_ERR_OK ) {
                    continue;
                }
                $ext = strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, $allowed_types, true ) ) {
                    return "$label accepts " . implode( ', ', $allowed_types ) . " files only";
                }
                if ( $f['size'] > $max_bytes ) {
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
            // BUG-004: context-requiring SQL patterns to avoid false positives
            // on common words like "select", "delete", "create", "update".
            '/\bDROP\s+TABLE\b/i',
            '/\bINSERT\s+INTO\b/i',
            '/\bSELECT\s+.+\s+FROM\b/i',
            '/\bUPDATE\s+\w+\s+SET\b/i',
            '/\bDELETE\s+FROM\b/i',
            '/\bCREATE\s+(TABLE|DATABASE|INDEX)\b/i',
            '/\bALTER\s+TABLE\b/i',
            '/\bEXEC\s*\(/i',
            '/--/',
            '/\{[^}]*\}/',
        ];
        foreach ( $patterns as $pattern ) {
            $value = preg_replace( $pattern, '', $value );
        }
        return $value;
    }
}
