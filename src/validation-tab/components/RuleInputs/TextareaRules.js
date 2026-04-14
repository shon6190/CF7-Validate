import { TextControl, ToggleControl, SelectControl } from '@wordpress/components';

export default function TextareaRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Max characters" type="number" value={ rules.max_length ?? 1500 } onChange={ v => set( 'max_length', v ) } />
            <SelectControl
                label="Character counter"
                value={ rules.counter_format ?? 'count/max' }
                options={ [
                    { label: 'Count / Max (e.g. 13 / 1500)', value: 'count/max' },
                    { label: 'Remaining (e.g. 1487 remaining)', value: 'remaining' },
                    { label: 'Off', value: 'off' },
                ] }
                onChange={ v => set( 'counter_format', v ) }
            />
            <TextControl label="Max height (px) before scrollbar" type="number" value={ rules.max_height ?? 200 } onChange={ v => set( 'max_height', v ) } />
            <ToggleControl label="Strip code/security operators" checked={ rules.security_sanitize !== false } onChange={ v => set( 'security_sanitize', v ) } />
        </div>
    );
}
