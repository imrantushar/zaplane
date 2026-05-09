import React from 'react';
import { __ } from '@wordpress/i18n';

const General = () => {
	return (
		<div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
			<h2 className="text-lg font-semibold text-gray-900 m-0 mb-2">
				{__('General', 'zaplane')}
			</h2>
			<p className="text-sm text-gray-600 m-0">
				{__('General settings will appear here.', 'zaplane')}
			</p>
		</div>
	);
};

export default General;
