import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { testAPI } from '@ZAPRedux/Slices/settingSlice/settingSlice';

const Setting = () => {
	const dispatch = useDispatch();
	const { data, loading } = useSelector((state) => state.setting);
    console.log(data);

	const [method, setMethod] = useState('GET');
	const [path, setPath] = useState('');
	const [body, setBody] = useState('');

	const handleSubmit = () => {
		try {
			dispatch(
				testAPI({
					method,
					path,
					body: body ? JSON.parse(body) : null,
				})
			);
		} catch (e) {
			alert('Invalid JSON body');
		}
	};

	return (
		<div style={{ maxWidth: '900px', margin: 'auto', padding: '20px', fontFamily: 'sans-serif' }}>
			<h2 style={{ fontSize: '24px', fontWeight: 'bold', marginBottom: '20px' }}>
				API Tester
			</h2>

			<div style={{ marginBottom: '15px' }}>
				{/* Method input (optional if you want dynamic method) */}
				<select value={method} onChange={e => setMethod(e.target.value)} style={{ marginRight: '10px' }}>
					<option value="GET">GET</option>
					<option value="POST">POST</option>
					<option value="PUT">PUT</option>
					<option value="DELETE">DELETE</option>
				</select>

				<input
					type="text"
					placeholder=""
					value={path}
					onChange={(e) => setPath(e.target.value)}
					style={{ padding: '8px', width: '60%', marginRight: '10px' }}
				/>
			</div>

			{(method === 'POST' || method === 'PUT') && (
				<textarea
					placeholder='JSON Body e.g. { "name": "example" }'
					value={body}
					onChange={(e) => setBody(e.target.value)}
					rows={5}
					style={{ width: '100%', padding: '10px', marginBottom: '10px', fontFamily: 'monospace' }}
				/>
			)}

			<button
				onClick={handleSubmit}
				disabled={loading}
				style={{
					padding: '10px 20px',
					backgroundColor: '#3182ce',
					color: '#fff',
					border: 'none',
					borderRadius: '4px',
					cursor: 'pointer',
				}}
			>
				{loading ? 'Loading...' : 'Send Request'}
			</button>

			<hr style={{ margin: '30px 0' }} />

			<h3 style={{ fontWeight: 'bold', marginBottom: '10px' }}>data</h3>

			{!data || data.length === 0 ? (
				<p style={{ color: '#666' }}>No response yet</p>
			) : (
				<div
						style={{
							background: '#f7fafc',
							padding: '10px',
							border: '1px solid #e2e8f0',
							borderRadius: '5px',
							marginBottom: '10px',
						}}
					>
						<pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
							{JSON.stringify(data, null, 2)}
						</pre>
					</div>
			)}
		</div>
	);
};

export default Setting;
