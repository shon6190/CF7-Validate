import { ToggleControl } from '@wordpress/components';

export default function GlobalSettings( { global, onChange } ) {
	return (
		<div className="cfv-global-settings">
			<h3>Global Settings</h3>
			<ToggleControl
				label='Show "(Optional)" label on non-required fields'
				checked={ !! global.show_optional_label }
				onChange={ ( val ) => onChange( { ...global, show_optional_label: val } ) }
			/>
		</div>
	);
}
