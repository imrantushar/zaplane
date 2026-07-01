import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TextInput, GhostButton, RepeatableList } from './parts';
import RequestEditor from './RequestEditor';
import FieldsEditor from './FieldsEditor';
import TestPanel from './TestPanel';
import { HandlerEditor } from './local';

const OutputEditor = ({ outputs = [], onChange }) => (
	<RepeatableList
		items={outputs}
		onChange={onChange}
		addLabel={__('Add output', 'zaplane')}
		template={{ key: '', path: '' }}
		renderRow={(output, update) => (
			<div className="grid grid-cols-2 gap-3">
				<TextInput
					label={__('Output key', 'zaplane')}
					value={output.key}
					onChange={(v) => update({ key: v })}
					placeholder="deal_id"
				/>
				<TextInput
					label={__('Response path', 'zaplane')}
					value={output.path}
					onChange={(v) => update({ path: v })}
					placeholder="data.id"
					mono
				/>
			</div>
		)}
	/>
);

/** Compact key→value form to supply sample values when testing (not persisted). */
const SampleValues = ({ fields = [], values, onChange }) => {
	if (!fields.length) return null;
	return (
		<div className="mb-2 grid grid-cols-2 gap-2">
			{fields.map((f) => (
				<TextInput
					key={f.key}
					label={`${f.label || f.key} (test)`}
					value={values[f.key] || ''}
					onChange={(v) => onChange({ ...values, [f.key]: v })}
				/>
			))}
		</div>
	);
};

const ActionCard = ({ action, update, remove, manifest, kind }) => {
	const [sample, setSample] = useState({});
	const isLocal = kind === 'local';

	const addOutput = (path) => {
		const key = path.split('.').pop();
		const exists = (action.output || []).some((o) => o.path === path);
		if (exists) return;
		update({ output: [...(action.output || []), { key, path }] });
	};

	return (
		<div className="rounded-lg border border-gray-200 p-4">
			<div className="mb-3 flex items-center justify-between">
				<span className="text-xs font-semibold uppercase tracking-wide text-gray-400">
					{__('Action', 'zaplane')}
				</span>
				<GhostButton tone="danger" onClick={remove}>
					{__('Remove action', 'zaplane')}
				</GhostButton>
			</div>

			<div className="mb-3 grid grid-cols-2 gap-3">
				<TextInput
					label={__('Key', 'zaplane')}
					value={action.key}
					onChange={(v) => update({ key: v })}
					placeholder="create_deal"
				/>
				<TextInput
					label={__('Label', 'zaplane')}
					value={action.label}
					onChange={(v) => update({ label: v })}
					placeholder="Create Deal"
				/>
			</div>

			<p className="mb-1 text-xs font-medium text-gray-600">{__('Input fields', 'zaplane')}</p>
			<FieldsEditor fields={action.fields || []} onChange={(fields) => update({ fields })} />

			{isLocal ? (
				<>
					<p className="mb-1 mt-4 text-xs font-medium text-gray-600">{__('Handler', 'zaplane')}</p>
					<HandlerEditor handler={action.handler || {}} onChange={(handler) => update({ handler })} />
				</>
			) : (
				<>
					<p className="mb-1 mt-4 text-xs font-medium text-gray-600">{__('Request', 'zaplane')}</p>
					<RequestEditor request={action.request || {}} onChange={(request) => update({ request })} />
				</>
			)}

			<p className="mb-1 mt-4 text-xs font-medium text-gray-600">{__('Outputs', 'zaplane')}</p>
			<OutputEditor outputs={action.output || []} onChange={(output) => update({ output })} />

			{!isLocal && (
				<>
					<p className="mb-1 mt-4 text-xs font-medium text-gray-600">{__('Test', 'zaplane')}</p>
					<SampleValues fields={action.fields || []} values={sample} onChange={setSample} />
					<TestPanel
						getPayload={() => ({
							manifest: { base_url: manifest.base_url, auth: manifest.auth },
							request: action.request || {},
							config: sample,
							credentials: {},
						})}
						onPick={addOutput}
					/>
				</>
			)}
		</div>
	);
};

const ActionsEditor = ({ actions = [], onChange, manifest, kind = 'http' }) => {
	const update = (index, patch) => {
		const next = actions.slice();
		next[index] = { ...next[index], ...patch };
		onChange(next);
	};
	const remove = (index) => onChange(actions.filter((_, i) => i !== index));
	const add = () =>
		onChange([
			...actions,
			kind === 'local'
				? { key: '', label: '', fields: [], handler: { type: 'do_action', args: [] }, output: [] }
				: { key: '', label: '', fields: [], request: { method: 'GET' }, output: [] },
		]);

	return (
		<div className="flex flex-col gap-4">
			{actions.map((action, index) => (
				<ActionCard
					key={index}
					action={action}
					manifest={manifest}
					kind={kind}
					update={(patch) => update(index, patch)}
					remove={() => remove(index)}
				/>
			))}
			<div>
				<GhostButton tone="primary" onClick={add}>
					+ {__('Add action', 'zaplane')}
				</GhostButton>
			</div>
		</div>
	);
};

export default ActionsEditor;
