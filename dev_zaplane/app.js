import { createPortal } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { ChakraProvider, createSystem, defaultConfig } from '@chakra-ui/react';
import '../assets/scss/backend.scss';
import { Provider } from 'react-redux';
import { BrowserRouter as Router } from 'react-router-dom';
import BackendDashboard from './containers/BackendDashboard';
import AdminMenu from '@ZAPContainers/BackendDashboard/AdminMenu';
import { theme } from '../assets/scss/chakra/theme';
import { store } from '@ZAPRedux/store';



document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('zaplane-app');
	if (container) {
		const root = createRoot(container);
		const menuPage = document.getElementById('toplevel_page_zaplane');
		function MenuPortal({ children }) {
			menuPage.innerHTML = '';
			return createPortal(children, menuPage);
		}
		root.render(
			<Provider store={store}>
				<ChakraProvider value={theme}>
					<Router>
						<MenuPortal>
							<AdminMenu />
						</MenuPortal>
						<BackendDashboard />
					</Router>
				</ChakraProvider>
			</Provider>
		);
	}
});