import { TextControl } from '@wordpress/components';

export default function SelectRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl
                label='Placeholder option value (the value that means "not selected")'
                value={ rules.placeholder_value ?? '' }
                onChange={ v => set( 'placeholder_value', v ) }
            />
        </div>
    );
}
