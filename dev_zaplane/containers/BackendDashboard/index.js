import { useQuery } from '@ZAPUtils/helper';
import React from 'react';

import CreateWorkflows from './pages/workflows';
import Workflows from './pages/workflows/workFlowMotion';
import Notification from '@ZAPComponents/Notification';
import Logs from './pages/logs';
import Setting from './pages/setting';
import Connections from './pages/connections';
import Dashboard from './pages/dashboard';



const renderSwitch = (page, id, action, path) => {

	switch (page) {
		case 'zaplane':
			return <Dashboard />;
		case 'zaplane-workflows':
			if ( action || id ) {
				return <Workflows id={ id } />;
			}
			return <CreateWorkflows />;
		case 'zaplane-logs':
			return <Logs />;
		case 'zaplane-connections':
			return <Connections />;
		case 'zaplane-settings':
			return <Setting />;
		default:
			return <>No page found</>;
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
