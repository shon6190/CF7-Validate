import { TextControl, ToggleControl } from '@wordpress/components';

export default function PhoneRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Min length (digits)" type="number" value={ rules.min_length ?? 7 } onChange={ v => set( 'min_length', v ) } />
            <TextControl label="Max length (digits)" type="number" value={ rules.max_length ?? 15 } onChange={ v => set( 'max_length', v ) } />
            <ToggleControl
                label="Enable international phone input (intl-tel-input)"
                checked={ !! rules.enable_intl }
                onChange={ v => set( 'enable_intl', v ) }
            />
            { rules.enable_intl && (
                <TextControl
                    label="Default country code (e.g. us, gb, au — or 'auto')"
                    value={ rules.default_country ?? 'auto' }
                    onChange={ v => set( 'default_country', v ) }
                />
            ) }
        </div>
    );
}
