import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TextInput, SelectInput, Labeled } from './parts';

const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((m) => ({ value: m, label: m }));

/**
 * A JSON object field: edits as text, syncs the parsed object to the parent only
 * when valid, and surfaces a parse error otherwise.
 */
export const JsonField = ({ label, value, onChange, placeholder, help }) => {
	const [text, setText] = useState(() =>
		value && Object.keys(value).length ? JSON.stringify(value, null, 2) : ''
	);
	const [error, setError] = useState(null);

	const handle = (next) => {
		setText(next);
		if (!next.trim()) {
			setError(null);
			onChange(undefined);
			return;
		}
		try {
			onChange(JSON.parse(next));
			setError(null);
		} catch (e) {
			setError(e.message);
		}
	};

	return (
		<Labeled label={label} help={help}>
			<textarea
				className="zaplane-textarea"
				style={{ fontFamily: 'var(--zaplane-font-mono, monospace)', fontSize: '12px' }}
				rows={5}
				value={text}
				placeholder={placeholder}
				onChange={(e) => handle(e.target.value)}
			/>
			{error && (
				<span className="text-xs" style={{ color: 'var(--zaplane-danger)' }}>
					{__('Invalid JSON:', 'zaplane')} {error}
				</span>
			)}
		</Labeled>
	);
};

/**
 * Edits a request template: method, path/url, headers, body. Values may contain
 * {{ placeholders }} that the runtime interpolates.
 */
const RequestEditor = ({ request = {}, onChange }) => {
	const patch = (p) => onChange({ ...request, ...p });

	return (
		<div className="flex flex-col gap-3">
			<div className="grid grid-cols-[120px_1fr] gap-3">
				<SelectInput
					label={__('Method', 'zaplane')}
					value={request.method || 'GET'}
					onChange={(v) => patch({ method: v })}
					options={METHODS}
				/>
				<TextInput
					label={__('Path', 'zaplane')}
					value={request.path}
					onChange={(v) => patch({ path: v })}
					placeholder="/posts/{{ post_id }}"
					help={__('Relative to the app base URL. Use {{ field }} placeholders.', 'zaplane')}
					mono
				/>
			</div>
			<JsonField
				label={__('Headers (JSON)', 'zaplane')}
				value={request.headers}
				onChange={(v) => patch({ headers: v })}
				placeholder={'{\n  "X-Custom": "{{ creds.token }}"\n}'}
			/>
			<JsonField
				label={__('Body (JSON)', 'zaplane')}
				value={request.body}
				onChange={(v) => patch({ body: v })}
				placeholder={'{\n  "title": "{{ title }}"\n}'}
			/>
		</div>
	);
};

export default RequestEditor;
