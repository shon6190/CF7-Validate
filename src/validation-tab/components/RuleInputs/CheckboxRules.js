import { ToggleControl } from '@wordpress/components';

export default function CheckboxRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <ToggleControl
                label="Required — at least one option must be selected"
                checked={ !! rules.required }
                onChange={ v => set( 'required', v ) }
            />
        </div>
    );
}
