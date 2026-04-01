import { TextControl, ToggleControl } from '@wordpress/components';

export default function NameRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <p className="cfv-rules-note">Name field defaults: alpha only, no leading spaces, min 2 chars (first name), max 56 chars. Override below.</p>
            <TextControl label="Min characters" type="number" value={ rules.min_length ?? '' } onChange={ v => set( 'min_length', v ) } />
            <TextControl label="Max characters" type="number" value={ rules.max_length ?? 56 } onChange={ v => set( 'max_length', v ) } />
            <ToggleControl label="Alpha only" checked={ rules.alpha_only !== false } onChange={ v => set( 'alpha_only', v ) } />
            <ToggleControl label="No leading spaces" checked={ rules.no_leading_spaces !== false } onChange={ v => set( 'no_leading_spaces', v ) } />
        </div>
    );
}
