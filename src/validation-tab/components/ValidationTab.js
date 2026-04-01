import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import PropTypes from 'prop-types';

/* global cfvAdmin */

/**
 * Root Validation Tab component.
 * Loads config via AJAX, renders settings, saves via AJAX.
 *
 * @param {Object} props
 * @param {number} props.formId CF7 form post ID.
 */
export default function ValidationTab( { formId } ) {
	const [ config, setConfig ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saveMessage, setSaveMessage ] = useState( '' );
	const [ saveError, setSaveError ] = useState( false );

	// Load config on mount.
	useEffect( () => {
		fetch( cfvAdmin.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action: 'cfv_get_config',
				form_id: formId,
				nonce: cfvAdmin.nonce,
			} ),
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				if ( data.success ) {
					setConfig( data.data );
				} else {
					setConfig( { global: {}, fields: {} } );
				}
			} )
			.catch( () => {
				setConfig( { global: {}, fields: {} } );
			} );
	}, [ formId ] );

	const save = () => {
		setSaving( true );
		setSaveMessage( '' );
		setSaveError( false );

		fetch( cfvAdmin.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action: 'cfv_save_config',
				form_id: formId,
				config: JSON.stringify( config ),
				nonce: cfvAdmin.nonce,
			} ),
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				setSaving( false );
				if ( data.success ) {
					setSaveMessage( 'Settings saved.' );
					setSaveError( false );
				} else {
					setSaveMessage( 'Error saving settings.' );
					setSaveError( true );
				}
				setTimeout( () => setSaveMessage( '' ), 3000 );
			} )
			.catch( () => {
				setSaving( false );
				setSaveMessage( 'Network error — settings not saved.' );
				setSaveError( true );
				setTimeout( () => setSaveMessage( '' ), 3000 );
			} );
	};

	if ( ! config ) {
		return <Spinner />;
	}

	return (
		<div className="cfv-validation-tab">
			<h2>Validation Settings</h2>
			{ /* GlobalSettings and FieldRuleRow list added in Tasks 7 and 9 */ }
			{ saveMessage && (
				<p className={ saveError ? 'cfv-save-error' : 'cfv-save-success' }>
					{ saveMessage }
				</p>
			) }
			<button
				className="button button-primary"
				onClick={ save }
				disabled={ saving }
			>
				{ saving ? 'Saving…' : 'Save Validation Settings' }
			</button>
		</div>
	);
}

ValidationTab.propTypes = {
	formId: PropTypes.number.isRequired,
};
