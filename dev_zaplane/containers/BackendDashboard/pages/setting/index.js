import React from 'react';
import { __ } from '@wordpress/i18n';

import PageLayout from '@ZAPComponents/PageLayout';
import { useQuery } from '@ZAPUtils/helper';

import General from './sections/general';
import BackgroundWorker from './sections/background-worker';

const SECTIONS = [
	{ id: 'general', label: 'General', component: General },
	{ id: 'worker', label: 'Background Worker', component: BackgroundWorker },
];

const Setting = () => {
	const query = useQuery();
	const requested = query.get('section');
	const active = SECTIONS.find((s) => s.id === requested) || SECTIONS[0];
	const ActiveComponent = active.component;

	const buildHref = (sectionId) => {
		const params = new URLSearchParams(window.location.search);
		params.set('page', 'zaplane-settings');
		params.set('section', sectionId);
		return `${window.location.pathname}?${params.toString()}`;
	};

	return (
		<PageLayout title={__('Settings', 'zaplane')} heading={__('Settings', 'zaplane')}>
			<div className="flex flex-col md:flex-row gap-6 max-w-6xl">
				<aside className="md:w-56 shrink-0">
					<nav className="bg-white border border-gray-200 rounded-lg overflow-hidden">
						{SECTIONS.map((section) => {
							const isActive = section.id === active.id;
							return (
								<a
									key={section.id}
									href={buildHref(section.id)}
									className={`block px-4 py-3 text-sm border-l-4 ${
										isActive
											? 'border-blue-600 bg-blue-50 text-blue-900 font-medium'
											: 'border-transparent text-gray-700 hover:bg-gray-50'
									}`}
								>
									{__(section.label, 'zaplane')}
								</a>
							);
						})}
					</nav>
				</aside>
				<section className="flex-1 min-w-0">
					<ActiveComponent />
				</section>
			</div>
		</PageLayout>
	);
};

export default Setting;
