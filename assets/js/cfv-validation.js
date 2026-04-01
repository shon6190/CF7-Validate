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
            const itiInstances = window.cfvItiInstances?.[ instanceKey ] || {};
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
