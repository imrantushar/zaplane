import { createPortal } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { ChakraProvider, createSystem, defaultConfig } from '@chakra-ui/react';
import Plugin from './form/plugin';
import './app.scss';


const theme = createSystem(defaultConfig, {})


document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'zenreset-app' );
	if ( container ) {
		const root = createRoot( container );
		root.render(
            <ChakraProvider value={theme}>
                <h1>Welcome to ZenReset</h1>
				<Plugin />
            </ChakraProvider>
		);
	}
} );
