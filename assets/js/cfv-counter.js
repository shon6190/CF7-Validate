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
