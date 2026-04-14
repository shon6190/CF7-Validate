import { TextControl, ToggleControl } from '@wordpress/components';

export default function FileRules( { rules, onChange } ) {
    const set = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    return (
        <div className="cfv-rules-group">
            <TextControl
                label="Allowed file types (comma-separated, e.g. jpg,png,pdf)"
                value={ rules.allowed_types ?? 'jpg,jpeg,png,pdf' }
                onChange={ v => set( 'allowed_types', v ) }
            />
            <TextControl label="Max file size (MB)" type="number" value={ rules.max_size_mb ?? 5 } onChange={ v => set( 'max_size_mb', v ) } />
            <ToggleControl label="Allow multiple files" checked={ !! rules.allow_multiple } onChange={ v => set( 'allow_multiple', v ) } />
            <ToggleControl label="Show file preview before submit" checked={ !! rules.show_preview } onChange={ v => set( 'show_preview', v ) } />
        </div>
    );
}
