import { createPortal } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { ChakraProvider, createSystem, defaultConfig } from '@chakra-ui/react';
import './app.scss';


const theme = createSystem(defaultConfig, {})


document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'zaplane-app' );
	if ( container ) {
		const root = createRoot( container );
		root.render(
            <ChakraProvider value={theme}>
                <h1>Welcome to Zaplane</h1>
			
            </ChakraProvider>
		);
	}
} );
