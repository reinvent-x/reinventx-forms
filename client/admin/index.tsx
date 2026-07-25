import { createRoot } from '@wordpress/element';

import App from './App';
import './theme.css';

const node = document.getElementById( 'rvtx-admin' );

if ( node ) {
	node.textContent = '';
	createRoot( node ).render( <App /> );
}
