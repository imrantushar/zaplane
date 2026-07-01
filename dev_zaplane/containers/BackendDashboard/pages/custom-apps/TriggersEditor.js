import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextInput, SelectInput, GhostButton } from './parts';
import RequestEditor from './RequestEditor';
import FieldsEditor from './FieldsEditor';
import { ArgsEditor } from './local';

const MODES = [
	{ value: 'polling', label: __('Polling', 'zaplane') },
	{ value: 'webhook', label: __('Webhook', 'zaplane') },
];

const TriggerCard = ({ trigger, update, remove, kind }) => {
	const isLocal = kind === 'local';
	const mode = trigger.mode || 'polling';
	const patchPolling = (p) => update({ polling: { ...(trigger.polling || {}), ...p } });
	const patchWebhook = (p) => update({ webhook: { ...(trigger.webhook || {}), ...p } });

	return (
		<div className="rounded-lg border border-gray-200 p-4">
			<div className="mb-3 flex items-center justify-between">
				<span className="text-xs font-semibold uppercase tracking-wide text-gray-400">
					{__('Trigger', 'zaplane')}
				</span>
				<GhostButton tone="danger" onClick={remove}>
					{__('Remove trigger', 'zaplane')}
				</GhostButton>
			</div>

			<div className="mb-3 grid grid-cols-3 gap-3">
				<TextInput
					label={__('Key', 'zaplane')}
					value={trigger.key}
					onChange={(v) => update({ key: v })}
					placeholder="deal_won"
				/>
				<TextInput
					label={__('Label', 'zaplane')}
					value={trigger.label}
					onChange={(v) => update({ label: v })}
					placeholder="Deal Won"
				/>
				{!isLocal && (
					<SelectInput
						label={__('Mode', 'zaplane')}
						value={mode}
						onChange={(v) => update({ mode: v })}
						options={MODES}
					/>
				)}
			</div>

			{isLocal && (
				<div className="grid grid-cols-[1fr] gap-3">
					<TextInput
						label={__('WordPress hook', 'zaplane')}
						value={trigger.hook}
						onChange={(v) => update({ hook: v })}
						placeholder="woocommerce_order_status_completed"
						help={__('The do_action hook the other plugin fires.', 'zaplane')}
						mono
					/>
					<ArgsEditor
						args={trigger.args || []}
						onChange={(args) => update({ args })}
						help={__('Name each hook argument in order — these become the trigger output fields.', 'zaplane')}
					/>
				</div>
			)}

			{!isLocal && mode === 'polling' && (
				<>
					<p className="mb-1 text-xs font-medium text-gray-600">{__('Poll request', 'zaplane')}</p>
					<RequestEditor
						request={trigger.polling?.request || { method: 'GET' }}
						onChange={(request) => patchPolling({ request })}
					/>
					<div className="mt-3 grid grid-cols-2 gap-3">
						<TextInput
							label={__('Items path', 'zaplane')}
							value={trigger.polling?.items_path}
							onChange={(v) => patchPolling({ items_path: v })}
							placeholder="data"
							help={__('Empty means the response is itself the list.', 'zaplane')}
							mono
						/>
						<TextInput
							label={__('Dedupe key', 'zaplane')}
							value={trigger.polling?.dedupe_key}
							onChange={(v) => patchPolling({ dedupe_key: v })}
							placeholder="id"
							mono
						/>
					</div>
				</>
			)}

			{!isLocal && mode === 'webhook' && (
				<div className="grid grid-cols-2 gap-3">
					<TextInput
						label={__('Event path (optional)', 'zaplane')}
						value={trigger.webhook?.event_path}
						onChange={(v) => patchWebhook({ event_path: v })}
						placeholder="type"
						help={__('A body path whose value must equal "match".', 'zaplane')}
						mono
					/>
					<TextInput
						label={__('Match value (optional)', 'zaplane')}
						value={trigger.webhook?.match}
						onChange={(v) => patchWebhook({ match: v })}
						placeholder="deal.won"
					/>
					<TextInput
						label={__('Items path (optional)', 'zaplane')}
						value={trigger.webhook?.items_path}
						onChange={(v) => patchWebhook({ items_path: v })}
						placeholder="data"
						mono
					/>
				</div>
			)}

			{!isLocal && (
				<>
					<p className="mb-1 mt-4 text-xs font-medium text-gray-600">{__('Output fields', 'zaplane')}</p>
					<FieldsEditor fields={trigger.fields || []} onChange={(fields) => update({ fields })} />
				</>
			)}
		</div>
	);
};

const TriggersEditor = ({ triggers = [], onChange, kind = 'http' }) => {
	const update = (index, patch) => {
		const next = triggers.slice();
		next[index] = { ...next[index], ...patch };
		onChange(next);
	};
	const remove = (index) => onChange(triggers.filter((_, i) => i !== index));
	const add = () =>
		onChange([
			...triggers,
			kind === 'local'
				? { key: '', label: '', mode: 'hook', hook: '', args: [] }
				: { key: '', label: '', mode: 'polling', polling: { request: { method: 'GET' }, items_path: '', dedupe_key: 'id' } },
		]);

	return (
		<div className="flex flex-col gap-4">
			{triggers.map((trigger, index) => (
				<TriggerCard
					key={index}
					trigger={trigger}
					kind={kind}
					update={(patch) => update(index, patch)}
					remove={() => remove(index)}
				/>
			))}
			<div>
				<GhostButton tone="primary" onClick={add}>
					+ {__('Add trigger', 'zaplane')}
				</GhostButton>
			</div>
		</div>
	);
};

export default TriggersEditor;
