import React from 'react';
import { __ } from '@wordpress/i18n';
import { transparentMiniBtn } from '../../../../../assets/scss/chakra/recipe';

/*
 * Shared primitives for the Custom Apps builder, styled with Zaplane's design
 * tokens (--zaplane-* vars + the zaplane-input/label/textarea classes) so the
 * page matches Connections, Workflows, etc. rather than introducing its own look.
 */

/** A labelled field wrapper. */
export const Labeled = ({ label, help, children }) => (
	<div className="flex flex-col gap-1.5">
		{label && <span className="zaplane-label">{label}</span>}
		{children}
		{help && (
			<span className="text-xs" style={{ color: 'var(--zaplane-font-secondary-color)' }}>
				{help}
			</span>
		)}
	</div>
);

export const TextInput = ({ label, value, onChange, placeholder, help, type = 'text', mono }) => (
	<Labeled label={label} help={help}>
		<input
			type={type}
			className="zaplane-input"
			style={mono ? { fontFamily: 'var(--zaplane-font-mono, monospace)' } : undefined}
			value={value ?? ''}
			placeholder={placeholder}
			onChange={(e) => onChange(e.target.value)}
		/>
	</Labeled>
);

export const TextArea = ({ label, value, onChange, placeholder, help, rows = 4 }) => (
	<Labeled label={label} help={help}>
		<textarea
			className="zaplane-textarea"
			style={{ fontFamily: 'var(--zaplane-font-mono, monospace)' }}
			rows={rows}
			value={value ?? ''}
			placeholder={placeholder}
			onChange={(e) => onChange(e.target.value)}
		/>
	</Labeled>
);

export const SelectInput = ({ label, value, onChange, options, help }) => (
	<Labeled label={label} help={help}>
		<select className="zaplane-input" value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</select>
	</Labeled>
);

/** A titled card section. */
export const Section = ({ title, description, right, children }) => (
	<section
		className="rounded-lg p-4"
		style={{ border: '1px solid var(--zaplane-border-color)', background: 'var(--zaplane-background)' }}
	>
		<div className="mb-3 flex items-start justify-between gap-3">
			<div>
				<h3 className="text-sm font-semibold" style={{ color: 'var(--zaplane-font-color)' }}>
					{title}
				</h3>
				{description && (
					<p className="text-xs" style={{ color: 'var(--zaplane-font-secondary-color)' }}>
						{description}
					</p>
				)}
			</div>
			{right}
		</div>
		{children}
	</section>
);

export const GhostButton = ({ onClick, children, tone = 'default' }) => {
	const toneVars = {
		default: { color: 'var(--zaplane-font-color)', borderColor: 'var(--zaplane-border-color)' },
		danger: { color: 'var(--zaplane-danger)', borderColor: 'var(--zaplane-danger)' },
		primary: { color: 'var(--zaplane-primary)', borderColor: 'var(--zaplane-primary)' },
	};
	return (
		<button type="button" onClick={onClick} style={{ ...transparentMiniBtn, borderRadius: '4px', ...toneVars[tone] }}>
			{children}
		</button>
	);
};

/**
 * A generic add/remove list of rows.
 * @param {Array} items
 * @param {(next:Array)=>void} onChange
 * @param {(item, update:(patch)=>void, index)=>React.ReactNode} renderRow
 * @param {object} template  Blank row to append.
 */
export const RepeatableList = ({ items = [], onChange, renderRow, template, addLabel }) => {
	const update = (index, patch) => {
		const next = items.slice();
		next[index] = { ...next[index], ...patch };
		onChange(next);
	};
	const remove = (index) => onChange(items.filter((_, i) => i !== index));
	const add = () => onChange([...items, { ...template }]);

	return (
		<div className="flex flex-col gap-3">
			{items.map((item, index) => (
				<div key={index} className="rounded p-3" style={{ border: '1px solid var(--zaplane-border-color)' }}>
					<div className="mb-2 flex justify-end">
						<GhostButton tone="danger" onClick={() => remove(index)}>
							{__('Remove', 'zaplane')}
						</GhostButton>
					</div>
					{renderRow(item, (patch) => update(index, patch), index)}
				</div>
			))}
			<div>
				<GhostButton tone="primary" onClick={add}>
					+ {addLabel || __('Add', 'zaplane')}
				</GhostButton>
			</div>
		</div>
	);
};

/** Parse a JSON string, returning [value, error]. Empty string -> undefined. */
export const parseJson = (text) => {
	if (!text || !text.trim()) return [undefined, null];
	try {
		return [JSON.parse(text), null];
	} catch (e) {
		return [undefined, e.message];
	}
};
