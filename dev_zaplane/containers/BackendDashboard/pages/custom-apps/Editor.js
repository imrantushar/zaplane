import React, { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { TextInput, SelectInput, Section, GhostButton } from './parts';
import AuthEditor from './AuthEditor';
import ActionsEditor from './ActionsEditor';
import TriggersEditor from './TriggersEditor';

const slugify = (name) =>
	name
		.toLowerCase()
		.replace(/[^a-z0-9_]+/g, '_')
		.replace(/^_+|_+$/g, '')
		.replace(/_{2,}/g, '_')
		.slice(0, 64);

const TABS = [
	{ key: 'details', label: __('Details', 'zaplane') },
	{ key: 'auth', label: __('Authentication', 'zaplane') },
	{ key: 'actions', label: __('Actions', 'zaplane') },
	{ key: 'triggers', label: __('Triggers', 'zaplane') },
];

const Editor = ({ initial, isNew, onSave, onCancel, onDelete, saving, errors }) => {
	const [manifest, setManifest] = useState(
		() =>
			initial || {
				slug: '',
				name: '',
				kind: 'http',
				base_url: '',
				auth: { type: 'none' },
				actions: [],
				triggers: [],
			}
	);
	const [tab, setTab] = useState('details');
	const [slugTouched, setSlugTouched] = useState(!isNew);

	const patch = (p) => setManifest((m) => ({ ...m, ...p }));

	const setName = (name) => {
		patch({ name, slug: !slugTouched ? slugify(name) : manifest.slug });
	};

	const counts = useMemo(
		() => ({
			actions: (manifest.actions || []).length,
			triggers: (manifest.triggers || []).length,
		}),
		[manifest]
	);

	const isLocal = (manifest.kind || 'http') === 'local';
	const visibleTabs = isLocal ? TABS.filter((t) => t.key !== 'auth') : TABS;

	return (
		<div className="flex flex-col gap-4">
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-2">
					<GhostButton onClick={onCancel}>← {__('Back', 'zaplane')}</GhostButton>
					<h2 className="text-lg font-semibold" style={{ color: 'var(--zaplane-font-color)' }}>
						{manifest.name || __('New Custom App', 'zaplane')}
					</h2>
				</div>
				<div className="flex items-center gap-2">
					{!isNew && (
						<GhostButton tone="danger" onClick={() => onDelete(manifest.slug)}>
							{__('Delete', 'zaplane')}
						</GhostButton>
					)}
					<button
						type="button"
						disabled={saving}
						onClick={() => onSave(manifest)}
						style={{ ...primaryBtn, opacity: saving ? 0.5 : 1 }}
					>
						{saving ? __('Saving…', 'zaplane') : __('Save app', 'zaplane')}
					</button>
				</div>
			</div>

			{errors?.length > 0 && (
				<div
					className="rounded p-3 text-sm"
					style={{
						border: '1px solid var(--zaplane-danger)',
						background: 'var(--zaplane-warn-muted, #fef2f2)',
						color: 'var(--zaplane-danger)',
					}}
				>
					<p className="mb-1 font-medium">{__('Please fix the following:', 'zaplane')}</p>
					<ul className="list-inside list-disc">
						{errors.map((e, i) => (
							<li key={i}>{e}</li>
						))}
					</ul>
				</div>
			)}

			<div className="flex gap-1" style={{ borderBottom: '1px solid var(--zaplane-border-color)' }}>
				{visibleTabs.map((t) => {
					const active = tab === t.key;
					return (
					<button
						key={t.key}
						type="button"
						onClick={() => setTab(t.key)}
						className="px-4 py-2 text-sm font-medium"
						style={{
							color: active ? 'var(--zaplane-primary)' : 'var(--zaplane-font-secondary-color)',
							borderBottom: active ? '2px solid var(--zaplane-primary)' : '2px solid transparent',
						}}
					>
						{t.label}
						{t.key === 'actions' && counts.actions ? ` (${counts.actions})` : ''}
						{t.key === 'triggers' && counts.triggers ? ` (${counts.triggers})` : ''}
					</button>
					);
				})}
			</div>

			{tab === 'details' && (
				<Section title={__('App details', 'zaplane')} description={__('Identity and how this app connects.', 'zaplane')}>
					<div className="grid grid-cols-2 gap-3">
						<TextInput label={__('Name', 'zaplane')} value={manifest.name} onChange={setName} placeholder="Pipedrive" />
						<TextInput
							label={__('Slug', 'zaplane')}
							value={manifest.slug}
							onChange={(v) => {
								setSlugTouched(true);
								patch({ slug: slugify(v) });
							}}
							help={isNew ? __('Auto-generated from the name; editable.', 'zaplane') : __('Slug cannot be changed.', 'zaplane')}
							mono
						/>
						<SelectInput
							label={__('Kind', 'zaplane')}
							value={manifest.kind || 'http'}
							onChange={(v) => patch({ kind: v })}
							options={[
								{ value: 'http', label: __('REST API (external service)', 'zaplane') },
								{ value: 'local', label: __('WordPress plugin (same site)', 'zaplane') },
							]}
							help={
								isLocal
									? __('Integrates with another plugin via WP hooks and PHP functions.', 'zaplane')
									: __('Talks to an external HTTP/REST API.', 'zaplane')
							}
						/>
						{!isLocal && (
							<TextInput
								label={__('Base URL', 'zaplane')}
								value={manifest.base_url}
								onChange={(v) => patch({ base_url: v })}
								placeholder="https://api.pipedrive.com/v1"
								mono
							/>
						)}
						<TextInput
							label={__('Icon (URL or data URI, optional)', 'zaplane')}
							value={manifest.icon}
							onChange={(v) => patch({ icon: v })}
						/>
					</div>
				</Section>
			)}

			{tab === 'auth' && !isLocal && (
				<Section title={__('Authentication', 'zaplane')}>
					<AuthEditor auth={manifest.auth || { type: 'none' }} onChange={(auth) => patch({ auth })} />
				</Section>
			)}

			{tab === 'actions' && (
				<Section title={__('Actions', 'zaplane')} description={__('Things a workflow can do with this app.', 'zaplane')}>
					<ActionsEditor
						actions={manifest.actions || []}
						manifest={manifest}
						kind={manifest.kind || 'http'}
						onChange={(actions) => patch({ actions })}
					/>
				</Section>
			)}

			{tab === 'triggers' && (
				<Section title={__('Triggers', 'zaplane')} description={__('Events that can start a workflow.', 'zaplane')}>
					<TriggersEditor
						triggers={manifest.triggers || []}
						kind={manifest.kind || 'http'}
						onChange={(triggers) => patch({ triggers })}
					/>
				</Section>
			)}
		</div>
	);
};

export default Editor;
