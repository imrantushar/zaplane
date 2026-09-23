import { useQuery, settings } from '@ZAPUtils/helper';
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
import KnowledgePage from './pages/knowledge';
import Folders from './pages/Folders';
import Folder from './pages/Folders/Folder';
import EmailTemplatesPage from './pages/email-templates';
import EmailTemplateEditor from './pages/email-templates/Editor';
import CustomApps from './pages/custom-apps';
import InboxPage from './pages/inbox';



const featureEnabled = key => settings?.features?.[key] !== false;

const FeatureDisabled = ({ name }) => (
	<div className="flex flex-col items-center justify-center py-24 text-center">
		<h3 className="text-[18px] font-semibold text-[var(--zaplane-font-color)]">
			{name} {__('is turned off', 'zaplane')}
		</h3>
		<p className="mt-2 text-[14px] text-[var(--zaplane-font-secondary-color)]">
			{__('Enable it from Zaplane Settings to use this feature.', 'zaplane')}
		</p>
	</div>
);

const renderSwitch = (page, id, action, path) => {

	switch (page) {
		case 'zaplane':
			return <Dashboard />;
		case 'zaplane-workflows':
			if (action || id) {
				return <Workflows id={id} />;
			}
			return <CreateWorkflows />;
		case 'zaplane-inbox':
			if (!featureEnabled('inbox')) return <FeatureDisabled name={__('Inbox', 'zaplane')} />;
			return <InboxPage />;
		case 'zaplane-logs':
			return <Logs />;
		case 'zaplane-connections':
			return <Connections />;
		case 'zaplane-custom-apps':
			if (!featureEnabled('custom_apps')) return <FeatureDisabled name={__('Custom Apps', 'zaplane')} />;
			return <CustomApps id={id} action={action} />;
		case 'zaplane-recipes':
			return <RecipesPage />;
		case 'zaplane-knowledge':
			if (!featureEnabled('knowledge')) return <FeatureDisabled name={__('Business Knowledge', 'zaplane')} />;
			return <KnowledgePage />;
		case 'zaplane-email-templates':
			if (action || id) {
				return <EmailTemplateEditor id={id} />;
			}
			return <EmailTemplatesPage />;
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

	const page = query.get('page');

	return (
			<div className="zaplane-admin-content">
				<Notification />
				{/* Keyed by page so switching menu items replays the enter animation. */}
				<div key={page} className="zaplane-route">
					{renderSwitch(
						page,
						parseInt(query.get('id')),
						query.get('action'),
						query.get('path')
					)}
				</div>
			</div>
	);
}
