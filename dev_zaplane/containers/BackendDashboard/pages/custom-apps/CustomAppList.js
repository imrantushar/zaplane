import React from 'react';
import { __ } from '@wordpress/i18n';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { GhostButton } from './parts';

const muted = { color: 'var(--zaplane-font-secondary-color)' };

const EmptyState = ({ onCreate }) => (
	<div
		className="rounded-lg p-10 text-center"
		style={{ border: '1px dashed var(--zaplane-border-color)', background: 'var(--zaplane-background)' }}
	>
		<h3 className="text-base font-semibold" style={{ color: 'var(--zaplane-font-color)' }}>
			{__('No custom apps yet', 'zaplane')}
		</h3>
		<p className="mx-auto mt-1 max-w-md text-sm" style={muted}>
			{__(
				'Build an integration for any REST API or WordPress plugin — define its actions and triggers visually, and use them in your workflows like any built-in app.',
				'zaplane'
			)}
		</p>
		<button type="button" onClick={onCreate} style={{ ...primaryBtn, marginTop: '16px' }}>
			{__('Create your first app', 'zaplane')}
		</button>
	</div>
);

const CustomAppList = ({ apps, onEdit, onDelete, onExport, onCreate }) => {
	if (!apps.length) return <EmptyState onCreate={onCreate} />;

	return (
		<div
			className="overflow-hidden rounded-lg"
			style={{ border: '1px solid var(--zaplane-border-color)', background: 'var(--zaplane-background)' }}
		>
			<table className="w-full text-left text-sm zaplane-table">
				<thead className="text-xs uppercase tracking-wide" style={{ ...muted, background: 'var(--zaplane-background-gray-color)' }}>
					<tr>
						<th className="px-4 py-2.5">{__('Name', 'zaplane')}</th>
						<th className="px-4 py-2.5">{__('Slug', 'zaplane')}</th>
						<th className="px-4 py-2.5">{__('Kind', 'zaplane')}</th>
						<th className="px-4 py-2.5">{__('Actions', 'zaplane')}</th>
						<th className="px-4 py-2.5">{__('Triggers', 'zaplane')}</th>
						<th className="px-4 py-2.5"></th>
					</tr>
				</thead>
				<tbody>
					{apps.map((app) => (
						<tr key={app.slug} style={{ borderTop: '1px solid var(--zaplane-border-color)' }}>
							<td className="px-4 py-3 font-medium" style={{ color: 'var(--zaplane-font-color)' }}>
								{app.name}
							</td>
							<td className="px-4 py-3 font-mono text-xs" style={muted}>
								{app.slug}
							</td>
							<td className="px-4 py-3" style={muted}>
								{(app.kind || 'http') === 'local' ? __('WordPress', 'zaplane') : __('REST API', 'zaplane')}
							</td>
							<td className="px-4 py-3" style={muted}>
								{(app.actions || []).length}
							</td>
							<td className="px-4 py-3" style={muted}>
								{(app.triggers || []).length}
							</td>
							<td className="px-4 py-3">
								<div className="flex justify-end gap-2">
									<GhostButton onClick={() => onEdit(app.slug)}>{__('Edit', 'zaplane')}</GhostButton>
									<GhostButton onClick={() => onExport(app)}>{__('Export', 'zaplane')}</GhostButton>
									<GhostButton tone="danger" onClick={() => onDelete(app.slug)}>
										{__('Delete', 'zaplane')}
									</GhostButton>
								</div>
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
};

export default CustomAppList;
