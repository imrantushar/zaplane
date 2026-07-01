import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextInput, SelectInput, GhostButton, Labeled } from './parts';

const HANDLER_TYPES = [
	{ value: 'do_action', label: __('Fire a hook (do_action)', 'zaplane') },
	{ value: 'apply_filters', label: __('Apply a filter (apply_filters)', 'zaplane') },
	{ value: 'function', label: __('Call a function', 'zaplane') },
];

/** Edits an ordered list of argument templates (plain strings, may hold {{ }}). */
export const ArgsEditor = ({ args = [], onChange, help }) => {
	const update = (i, v) => {
		const next = args.slice();
		next[i] = v;
		onChange(next);
	};
	const remove = (i) => onChange(args.filter((_, idx) => idx !== i));
	const add = () => onChange([...args, '']);

	return (
		<Labeled label={__('Arguments (in order)', 'zaplane')} help={help}>
			<div className="flex flex-col gap-2">
				{args.map((arg, i) => (
					<div key={i} className="flex items-center gap-2">
						<span className="w-6 text-xs" style={{ color: 'var(--zaplane-font-secondary-color)' }}>
							#{i + 1}
						</span>
						<input
							className="zaplane-input"
							style={{ fontFamily: 'var(--zaplane-font-mono, monospace)' }}
							value={arg}
							placeholder="{{ order_id }}"
							onChange={(e) => update(i, e.target.value)}
						/>
						<GhostButton tone="danger" onClick={() => remove(i)}>
							✕
						</GhostButton>
					</div>
				))}
				<div>
					<GhostButton tone="primary" onClick={add}>
						+ {__('Add argument', 'zaplane')}
					</GhostButton>
				</div>
			</div>
		</Labeled>
	);
};

/** Edits a local action's PHP handler (hook or function + args). */
export const HandlerEditor = ({ handler = {}, onChange }) => {
	const type = handler.type || 'do_action';
	const patch = (p) => onChange({ ...handler, ...p });

	return (
		<div className="flex flex-col gap-3">
			<SelectInput
				label={__('Handler', 'zaplane')}
				value={type}
				onChange={(v) => patch({ type: v })}
				options={HANDLER_TYPES}
			/>

			{type === 'function' ? (
				<TextInput
					label={__('Function name', 'zaplane')}
					value={handler.callable}
					onChange={(v) => patch({ callable: v })}
					placeholder="my_plugin_create_thing"
					help={__(
						'Any callable plugin/WordPress function. If it doesn’t exist the action fails cleanly (no fatal); a few dangerous functions like exec/system are blocked.',
						'zaplane'
					)}
					mono
				/>
			) : (
				<TextInput
					label={__('Hook name', 'zaplane')}
					value={handler.name}
					onChange={(v) => patch({ name: v })}
					placeholder="my_plugin_do_thing"
					mono
				/>
			)}

			<ArgsEditor args={handler.args || []} onChange={(args) => patch({ args })} />
		</div>
	);
};
