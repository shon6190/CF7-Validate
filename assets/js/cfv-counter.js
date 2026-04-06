( function () {
    'use strict';

    function initCounters() {
        document.querySelectorAll( '.cfv-counter' ).forEach( ( counter ) => {
            const fieldName = counter.dataset.field;
            const max       = parseInt( counter.dataset.max, 10 );
            const format    = counter.dataset.format || 'count/max';

            const form  = counter.closest( 'form' );
            const root  = form || document;
            // Use the CSS 'i' flag for case-insensitive name matching —
            // sanitize_key() lowercases field names in PHP config but CF7
            // may preserve the original case in the HTML name attribute.
            const field = root.querySelector( `[name="${ fieldName }" i]` )
                       || root.querySelector( `[name="${ fieldName }[]" i]` );
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
