import { render } from '@wordpress/element';
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

	render( <ValidationTab formId={ formId } />, el );
};

document.addEventListener( 'DOMContentLoaded', mount );
