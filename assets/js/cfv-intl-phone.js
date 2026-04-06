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
            const formId     = parseInt( wrapper?.dataset?.wpcf7Id || wrapper?.dataset?.id || '0', 10 );
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
                    utilsScript:       null,
                    separateDialCode:  true,
                } );

                window.cfvItiInstances[ instanceKey ][ fieldName ] = iti;

                // Mark the sibling error tip as inline so it sits beside the
                // intl input rather than on a new line below it.
                const controlWrap = input.closest( '[data-name]' );
                if ( controlWrap ) {
                    const errorTip = controlWrap.nextElementSibling;
                    if ( errorTip && errorTip.classList.contains( 'cfv-error-tip' ) ) {
                        errorTip.classList.add( 'cfv-error-tip--inline' );
                    }
                }

                // Inject hidden input for full E.164 number.
                const hiddenInput = document.createElement( 'input' );
                hiddenInput.type  = 'hidden';
                hiddenInput.name  = `cfv_phone_full_${ fieldName }`;
                input.parentNode.insertBefore( hiddenInput, input.nextSibling );

                // On form submit, write full E.164-style number to hidden input.
                // Build manually from dial code + subscriber number so this works
                // without utils.js loaded.
                formEl.addEventListener( 'submit', () => {
                    const dialCode = iti.getSelectedCountryData().dialCode || '';
                    const subscriber = input.value.trim().replace( /^0+/, '' ); // strip leading zeros
                    hiddenInput.value = dialCode ? `+${ dialCode }${ subscriber }` : subscriber;
                }, { capture: true } );

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
