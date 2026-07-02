import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FiEdit2, FiDownload, FiTrash2 } from 'react-icons/fi';
import ListTable from '@ZAPComponents/ListTable';
import Search from '@ZAPComponents/Search';
import OptionMenu from '@ZAPComponents/OptionMenu';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';

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

/** A small rounded icon/monogram for a custom app in the list. */
const AppIcon = ({ app }) => {
	const isImage = typeof app.icon === 'string' && /^(https?:|data:)/.test(app.icon);
	if (isImage) {
		return (
			<div
				className="flex h-9 w-9 items-center justify-center overflow-hidden rounded-[6px]"
				style={{ border: '1px solid var(--zaplane-border-color)', background: 'var(--zaplane-background-gray-color)' }}
			>
				<img src={app.icon} alt={app.name} style={{ width: '20px', height: '20px', objectFit: 'contain' }} />
			</div>
		);
	}
	return (
		<div
			className="flex h-9 w-9 items-center justify-center rounded-[6px] text-sm font-semibold uppercase"
			style={{
				border: '1px solid var(--zaplane-border-color)',
				background: 'var(--zaplane-background-gray-color)',
				color: 'var(--zaplane-font-secondary-color)',
			}}
		>
			{(app.name || app.slug || '?').charAt(0)}
		</div>
	);
};

const CustomAppList = ({ apps, onEdit, onDelete, onExport, onCreate }) => {
	const [searchTerm, setSearchTerm] = useState('');

	if (!apps.length) return <EmptyState onCreate={onCreate} />;

	const filtered = searchTerm
		? apps.filter(
				(a) =>
					a.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
					a.slug?.toLowerCase().includes(searchTerm.toLowerCase())
		  )
		: apps;

	const columns = [
		{
			name: <span>{__('App / Name', 'zaplane')}</span>,
			cell: (row) => (
				<div className="flex items-center gap-3">
					<AppIcon app={row} />
					<div className="flex flex-col">
						<span className="zaplane-label font-[400]">{row.name}</span>
						<span className="zaplane-sub-title font-mono text-xs" style={muted}>
							{row.slug}
						</span>
					</div>
				</div>
			),
			textAlign: 'start',
		},
		{
			name: <span>{__('Kind', 'zaplane')}</span>,
			cell: (row) => (
				<ZAPLabel
					label={(row.kind || 'http') === 'local' ? __('WordPress', 'zaplane') : __('REST API', 'zaplane')}
					type="simple"
				/>
			),
			textAlign: 'center',
		},
		{
			name: <span>{__('Actions', 'zaplane')}</span>,
			cell: (row) => <ZAPLabel label={String((row.actions || []).length)} type="simple" />,
			textAlign: 'center',
		},
		{
			name: <span>{__('Triggers', 'zaplane')}</span>,
			cell: (row) => <ZAPLabel label={String((row.triggers || []).length)} type="simple" />,
			textAlign: 'center',
		},
		{
			name: <span>{__('Action', 'zaplane')}</span>,
			cell: (row) => (
				<OptionMenu
					options={[
						{
							label: __('Edit', 'zaplane'),
							icon: <FiEdit2 />,
							type: 'button',
							onClick: () => onEdit(row.slug),
						},
						{
							label: __('Export', 'zaplane'),
							icon: <FiDownload />,
							type: 'button',
							onClick: () => onExport(row),
						},
						{
							label: __('Delete', 'zaplane'),
							icon: <FiTrash2 />,
							type: 'button',
							suffix: 'trash',
							hasBorder: false,
							onClick: () => onDelete(row.slug),
						},
					]}
				/>
			),
			textAlign: 'center',
		},
	];

	return (
		<ListTable
			columns={columns}
			data={filtered}
			isRowSelectable={false}
			showSubHeader={true}
			subHeaderComponent={
				<Search placeholder={__('Search custom apps...', 'zaplane')} onSearchHandler={setSearchTerm} />
			}
			showColumnFilter={false}
			showPagination={false}
			noDataText={__('No custom apps match your search', 'zaplane')}
			totalItems={filtered.length}
			suffix="custom-app-table"
		/>
	);
};

export default CustomAppList;
