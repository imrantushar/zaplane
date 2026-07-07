import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { GhostButton } from './parts';
import { testRequest } from './api';

/** Flatten a value into [ { path, preview } ] leaf entries for click-to-map. */
const flatten = (value, prefix = '', out = []) => {
	if (Array.isArray(value)) {
		// Sample the first element so paths look like "0.id".
		if (value.length) flatten(value[0], prefix ? `${prefix}.0` : '0', out);
	} else if (value && typeof value === 'object') {
		Object.entries(value).forEach(([k, v]) => flatten(v, prefix ? `${prefix}.${k}` : k, out));
	} else {
		out.push({ path: prefix, preview: String(value) });
	}
	return out;
};

/**
 * "Send test request" — runs a request template live against the API and shows
 * the response. Leaf paths are clickable so the user can map them to outputs.
 *
 * @param {() => object} getPayload  Returns { manifest, request, config, credentials }.
 * @param {(path:string) => void} [onPick]  Called with a dot-path when a leaf is clicked.
 */
const TestPanel = ({ getPayload, onPick }) => {
	const [result, setResult] = useState(null);
	const [loading, setLoading] = useState(false);

	const run = async () => {
		setLoading(true);
		setResult(null);
		try {
			setResult(await testRequest(getPayload()));
		} catch (e) {
			setResult({ error: e?.response?.data?.message || e.message, status: 0 });
		} finally {
			setLoading(false);
		}
	};

	const leaves = result && result.body !== undefined ? flatten(result.body) : [];
	const ok = result && !result.error && result.status >= 200 && result.status < 300;

	return (
		<div
			className="rounded p-3"
			style={{ border: '1px solid var(--zaplane-border-color)', background: 'var(--zaplane-background-gray-color)' }}
		>
			<div className="flex items-center gap-3">
				<GhostButton tone="primary" onClick={run}>
					{loading ? __('Sending…', 'zaplane') : __('Send test request', 'zaplane')}
				</GhostButton>
				{result && (
					<span
						className="text-xs font-medium"
						style={{ color: ok ? 'var(--zaplane-primary)' : 'var(--zaplane-danger)' }}
					>
						{result.error ? __('Error', 'zaplane') : `HTTP ${result.status}`}
					</span>
				)}
				{result?.request?.url && (
					<span className="truncate text-xs" style={{ color: 'var(--zaplane-font-secondary-color)' }}>
						{result.request.url}
					</span>
				)}
			</div>

			{result?.error && (
				<p className="mt-2 text-xs" style={{ color: 'var(--zaplane-danger)' }}>
					{result.error}
				</p>
			)}

			{leaves.length > 0 && (
				<div className="mt-3">
					<p className="mb-1 text-xs" style={{ color: 'var(--zaplane-font-secondary-color)' }}>
						{onPick
							? __('Click a field to map it to an output:', 'zaplane')
							: __('Response fields:', 'zaplane')}
					</p>
					<div className="flex flex-wrap gap-1.5">
						{leaves.slice(0, 60).map((leaf) => (
							<button
								key={leaf.path}
								type="button"
								onClick={() => onPick && onPick(leaf.path)}
								title={leaf.preview}
								className="rounded px-2 py-0.5 font-mono text-[11px]"
								style={{
									background: 'var(--zaplane-background)',
									color: 'var(--zaplane-primary)',
									border: '1px solid var(--zaplane-border-color)',
								}}
							>
								{leaf.path}
							</button>
						))}
					</div>
				</div>
			)}

			{result && result.body !== undefined && (
				<pre
					className="mt-3 max-h-52 overflow-auto rounded p-2 text-[11px]"
					style={{ background: 'var(--zaplane-font-color)', color: 'var(--zaplane-background)' }}
				>
					{JSON.stringify(result.body, null, 2)}
				</pre>
			)}
		</div>
	);
};

export default TestPanel;
