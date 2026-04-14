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
        const tagType  = match[ 1 ].toLowerCase();
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
