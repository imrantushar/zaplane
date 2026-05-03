import { useQuery } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';

import CreateWorkflows from './pages/workflows';
import Workflows from './pages/workflows/workFlowMotion';
import Notification from '@ZAPComponents/Notification';
import Logs from './pages/logs';
import Setting from './pages/setting';
import Connections from './pages/connections';
import Dashboard from './pages/dashboard';
import { __ } from '@wordpress/i18n';
import RecipesPage from './pages/recipes';
import Folders from './pages/Folders';
import Folder from './pages/Folders/Folder';



const renderSwitch = (page, id, action, path) => {

	switch (page) {
		case 'zaplane':
			return <Dashboard />;
		case 'zaplane-workflows':
			if (action || id) {
				return <Workflows id={id} />;
			}
			return <CreateWorkflows />;
		case 'zaplane-logs':
			return <Logs />;
		case 'zaplane-connections':
			return <Connections />;
		case 'zaplane-recipes':
			return <RecipesPage />;
		case 'zaplane-folders':
			if (action || id) {
				return <Folder id={id} />;
			}
			return <Folders />;
		case 'zaplane-settings':
			return <Setting />;

		default:
			return <>{__('No page found', 'zaplane')}</>;
	}
};

export default function BackendDashboard() {
	const query = useQuery();

	return (
			<div className="zaplane-admin-content">
				<Notification />
				{renderSwitch(
					query.get('page'),
					parseInt(query.get('id')),
					query.get('action'),
					query.get('path')
				)}
			</div>
	);
}
