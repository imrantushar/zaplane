import { createPortal } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { ChakraProvider } from '@chakra-ui/react';
import { Provider } from 'react-redux';
import { BrowserRouter as Router } from 'react-router-dom';
import BackendDashboard from './containers/BackendDashboard';
import AdminMenu from '@ZAPContainers/BackendDashboard/AdminMenu';
import { theme } from '../assets/scss/chakra/theme';
import { store } from '@ZAPRedux/store';
import { WorkFlowHook } from '@ZAPContainers/BackendDashboard/pages/workflows/WorkFlowHook';

import '../assets/scss/backend.scss';

document.addEventListener('DOMContentLoaded', () => {
	WorkFlowHook();
	const container = document.getElementById('zaplane-app');
	if (container) {
		container.classList.add('zaplane-scope');

		const root = createRoot(container);
		const menuPage = document.getElementById('toplevel_page_zaplane');

		function MenuPortal({ children }) {
			menuPage.innerHTML = '';
			menuPage.classList.add('zaplane-scope');
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
