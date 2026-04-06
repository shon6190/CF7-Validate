import { TextControl, ToggleControl, SelectControl } from '@wordpress/components';

export default function TextRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl label="Min characters" type="number" value={ rules.min_length ?? '' } onChange={ v => set( 'min_length', v ) } />
            <TextControl label="Max characters" type="number" value={ rules.max_length ?? '' } onChange={ v => set( 'max_length', v ) } />
            <ToggleControl label="Allow special characters" checked={ rules.allow_special_chars !== false } onChange={ v => set( 'allow_special_chars', v ) } />
            <ToggleControl label="Allow emoji / Unicode" checked={ rules.allow_emoji !== false } onChange={ v => set( 'allow_emoji', v ) } />
            <ToggleControl label="Collapse consecutive whitespace" checked={ rules.collapse_whitespace !== false } onChange={ v => set( 'collapse_whitespace', v ) } />
            <TextControl label="Input mask (9=digit, a=letter, *=alphanumeric)" value={ rules.input_mask ?? '' } onChange={ v => set( 'input_mask', v ) } />
            <SelectControl
                label="Character counter"
                value={ rules.counter_format ?? 'off' }
                options={ [
                    { label: 'Off', value: 'off' },
                    { label: 'Count / Max (e.g. 13 / 100)', value: 'count/max' },
                    { label: 'Remaining (e.g. 87 remaining)', value: 'remaining' },
                ] }
                onChange={ v => set( 'counter_format', v ) }
            />
        </div>
    );
}
