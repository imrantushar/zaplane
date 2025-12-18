import { useQuery } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';


const renderSwitch = (page, id, action, path) => {
	console.log(page,'page');

	switch (page) {
		case 'zaplane':
			return <>Zaplane Dashboard</>;
		// case 'gamify-points':
		// 	if(path === "points-types"){
        //      return <PointType />;
		// 	}
		// 	if ( action || id ) {
		// 		return <PointType action={ action } id={ id } />;
		// 	}
		// 	return <Points />;
		// case 'gamify-logs':
		// 	return <Logs />;

		// case 'gamify-settings':
		// 	return <Settings />;

		// case 'point-type':
		// 	return <PointType />;

		// case 'gamify-achievements':
		// 	if(path === 'achievements-type'){
		// 		return <AchievementsType />
		// 	}
		// 	if ( action || id ) {
		// 		return <AchievementsType action={ action } id={ id } />;
		// 	}
		// 	return <Achievements />;

		// case 'gamify-levels':
		// 	if(path === 'levels-types'){
		// 		return <LevelType />;
		// 	}
		// 	return <Levels />;
		// case 'gamify-leaderboards':
		// 	return <Leaderboards />;

		default:
			return <>No page found</>;
	}
};

export default function BackendDashboard() {
	const query = useQuery();
	
	return (
		<div className="gamify-admin-content">
			{renderSwitch(
				query.get('page'),
				parseInt(query.get('id')),
				query.get('action'),
				query.get('path')
			)}
		</div>
	);
}
