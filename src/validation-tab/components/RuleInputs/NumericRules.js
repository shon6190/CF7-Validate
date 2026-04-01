import { TextControl, ToggleControl } from '@wordpress/components';

export default function NumericRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Min value" type="number" value={ rules.min_value ?? '' } onChange={ v => set( 'min_value', v ) } />
            <TextControl label="Max value" type="number" value={ rules.max_value ?? '' } onChange={ v => set( 'max_value', v ) } />
            <ToggleControl label="Allow negative values" checked={ !! rules.allow_negative } onChange={ v => set( 'allow_negative', v ) } />
            <ToggleControl label="Allow zero" checked={ rules.allow_zero !== false } onChange={ v => set( 'allow_zero', v ) } />
        </div>
    );
}
