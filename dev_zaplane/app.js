import { createPortal } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import { Provider } from 'react-redux';
import { BrowserRouter as Router } from 'react-router-dom';
import BackendDashboard from './containers/BackendDashboard';
import AdminMenu from '@ZAPContainers/BackendDashboard/AdminMenu';
import { store } from '@ZAPRedux/store';
import { WorkFlowHook } from '@ZAPContainers/BackendDashboard/pages/workflows/WorkFlowHook';
import { initThemeMode } from '@ZAPUtils/theme';

import '../assets/scss/backend.scss';

document.addEventListener('DOMContentLoaded', () => {
	WorkFlowHook();
	const container = document.getElementById('zaplane-app');
	if (container) {
		container.classList.add('zaplane-scope');
		// Stamp the active theme mode before the first paint (no flash).
		initThemeMode();

		const root = createRoot(container);
		const menuPage = document.getElementById('toplevel_page_zaplane');
		if (menuPage) menuPage.innerHTML = '';
		function MenuPortal({ children }) {
			return createPortal(children, menuPage);
		}
		flushSync(() => {
			root.render(
				<Provider store={store}>
						<Router>
							<MenuPortal>
								<AdminMenu />
							</MenuPortal>
							<BackendDashboard />
						</Router>
				</Provider>
			);
		});
	}
});
