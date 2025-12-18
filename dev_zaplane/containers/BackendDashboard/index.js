import { useQuery } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';
import Workflows from './pages/workflows';



const renderSwitch = (page, id, action, path) => {
	console.log(page,'page');

	switch (page) {
		case 'zaplane':
			return <>Zaplane Dashboard</>;
		case 'zaplane-workflows':
			return <Workflows />;
		// case 'zaplane-logs':
		// 	return <Logs />;

		// case 'zaplane-settings':
		// 	return <Settings />;

		// case 'point-type':
		// 	return <PointType />;

		// case 'zaplane-achievements':
		// 	if(path === 'achievements-type'){
		// 		return <AchievementsType />
		// 	}
		// 	if ( action || id ) {
		// 		return <AchievementsType action={ action } id={ id } />;
		// 	}
		// 	return <Achievements />;

		// case 'zaplane-levels':
		// 	if(path === 'levels-types'){
		// 		return <LevelType />;
		// 	}
		// 	return <Levels />;
		// case 'zaplane-leaderboards':
		// 	return <Leaderboards />;

		default:
			return <>No page found</>;
	}
};

export default function BackendDashboard() {
	const query = useQuery();
	
	return (
		<div className="zaplane-admin-content">
			{renderSwitch(
				query.get('page'),
				parseInt(query.get('id')),
				query.get('action'),
				query.get('path')
			)}
		</div>
	);
}
