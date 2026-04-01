import { ToggleControl } from '@wordpress/components';

export default function EmailRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <p className="cfv-rules-note">Email format validation and whitespace trimming are always active.</p>
            <ToggleControl label="Required" checked={ !! rules.required } onChange={ v => set( 'required', v ) } />
        </div>
    );
}
