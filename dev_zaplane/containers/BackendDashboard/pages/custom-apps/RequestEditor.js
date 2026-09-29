import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import WPModal from '@ZAPComponents/Modal/WPModal';
import { TextInput, TextArea, SelectInput, GhostButton, Labeled } from './parts';
import { parseCurl } from './curl';

const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((m) => ({ value: m, label: m }));

/**
 * A JSON object field: edits as text, syncs the parsed object to the parent only
 * when valid, and surfaces a parse error otherwise.
 */
export const JsonField = ({ label, value, onChange, placeholder, help }) => {
	const [text, setText] = useState(() =>
		value !== undefined && value !== null && value !== '' ? JSON.stringify(value, null, 2) : ''
	);
	const [error, setError] = useState(null);

	useEffect(() => {
		setText(value !== undefined && value !== null && value !== '' ? JSON.stringify(value, null, 2) : '');
		setError(null);
	}, [value]);

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
	const [isCurlOpen, setCurlOpen] = useState(false);
	const [curl, setCurl] = useState('');
	const [curlError, setCurlError] = useState('');

	const importCurl = () => {
		try {
			const imported = parseCurl(curl);
			onChange({ ...request, ...imported, path: undefined });
			setCurlError('');
			setCurlOpen(false);
			setCurl('');
		} catch (error) {
			setCurlError(error.message);
		}
	};

	return (
		<div className="flex flex-col gap-3">
			<div>
				<GhostButton
					tone="primary"
					onClick={() => setCurlOpen(true)}
					styles={{ padding: '2px 8px', fontSize: '13px' }}
				>
					{__('Import cURL', 'zaplane')}
				</GhostButton>
			</div>
			<div className="grid grid-cols-[120px_1fr] gap-3">
				<SelectInput
					label={__('Method', 'zaplane')}
					value={request.method || 'GET'}
					onChange={(v) => patch({ method: v })}
					options={METHODS}
				/>
				<TextInput
					label={request.url ? __('Request URL', 'zaplane') : __('Path', 'zaplane')}
					value={request.url || request.path}
					onChange={(v) =>
						request.url
							? onChange({ ...request, url: v, path: undefined })
							: patch({ path: v })
					}
					placeholder={request.url ? 'https://api.example.com/posts' : '/posts/{{ post_id }}'}
					help={
						request.url
							? __('Imported absolute URL. Use {{ field }} placeholders.', 'zaplane')
							: __('Relative to the app base URL. Use {{ field }} placeholders.', 'zaplane')
					}
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
			<WPModal
				title={__('Import cURL', 'zaplane')}
				isOpen={isCurlOpen}
				onRequestClose={() => setCurlOpen(false)}
				suffix="curl-import"
				size="medium"
			>
				<div className="flex flex-col gap-3">
					<TextArea
						label={__('cURL Command', 'zaplane')}
						value={curl}
						onChange={(value) => {
							setCurl(value);
							setCurlError('');
						}}
						placeholder={'curl --request POST \'https://api.example.com/items\' --header \'Content-Type: application/json\' --data \'{"name":"Demo"}\''}
						rows={8}
						style={{ padding: '8px', minHeight: '180px' }}
					/>
					{curlError && (
						<span className="text-xs" style={{ color: 'var(--zaplane-danger)' }}>
							{curlError}
						</span>
					)}
					<div className="flex justify-end gap-2">
						<GhostButton
							onClick={() => setCurlOpen(false)}
							styles={{ padding: '7px 18px', minWidth: '88px', fontSize: '13px' }}
						>
							{__('Cancel', 'zaplane')}
						</GhostButton>
						<GhostButton
							tone="primary"
							onClick={importCurl}
							styles={{ padding: '7px 18px', minWidth: '88px', fontSize: '13px' }}
						>
							{__('Import', 'zaplane')}
						</GhostButton>
					</div>
				</div>
			</WPModal>
		</div>
	);
};

export default RequestEditor;
