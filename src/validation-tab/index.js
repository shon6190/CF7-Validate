import { createRoot } from '@wordpress/element';
import ValidationTab from './components/ValidationTab';

/**
 * Mount the ValidationTab React component into the CF7 editor panel div.
 */
const mount = () => {
	const el = document.getElementById( 'cfv-validation-tab' );
	if ( ! el ) {
		return;
	}

	const formId = parseInt( el.dataset.formId, 10 );
	if ( ! formId ) {
		return;
	}

	// Prevent change/input events from our React controls bubbling up to
	// CF7's unsaved-changes detector, which would show "Changes you made
	// may not be saved" every time any toggle or text control updates.
	[ 'change', 'input' ].forEach( ( type ) => {
		el.addEventListener( type, ( e ) => e.stopPropagation() );
	} );

	createRoot( el ).render( <ValidationTab formId={ formId } /> );
};

document.addEventListener( 'DOMContentLoaded', mount );
