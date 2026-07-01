import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextInput, SelectInput } from './parts';
import FieldsEditor from './FieldsEditor';

const AUTH_TYPES = [
	{ value: 'none', label: __('No auth', 'zaplane') },
	{ value: 'api_key', label: __('API key', 'zaplane') },
	{ value: 'bearer', label: __('Bearer token', 'zaplane') },
	{ value: 'basic', label: __('Basic auth', 'zaplane') },
	{ value: 'oauth2', label: __('OAuth 2.0', 'zaplane') },
];

const INJECT_IN = [
	{ value: 'header', label: __('Header', 'zaplane') },
	{ value: 'query', label: __('Query param', 'zaplane') },
];

const AuthEditor = ({ auth = { type: 'none' }, onChange }) => {
	const patch = (p) => onChange({ ...auth, ...p });
	const patchInject = (p) => patch({ inject: { ...(auth.inject || {}), ...p } });
	const patchOauth = (p) => patch({ oauth2: { ...(auth.oauth2 || {}), ...p } });
	const patchTest = (p) => patch({ test: { ...(auth.test || {}), ...p } });

	const type = auth.type || 'none';

	return (
		<div className="flex flex-col gap-4">
			<SelectInput
				label={__('Authentication', 'zaplane')}
				value={type}
				onChange={(v) => patch({ type: v })}
				options={AUTH_TYPES}
			/>

			{type !== 'none' && (
				<>
					<div>
						<p className="mb-1 text-xs font-medium text-gray-600">
							{__('Connection fields (what the user enters)', 'zaplane')}
						</p>
						<FieldsEditor
							fields={auth.fields || []}
							onChange={(fields) => patch({ fields })}
						/>
					</div>

					{(type === 'api_key' || type === 'bearer') && (
						<div className="grid grid-cols-3 gap-3">
							<SelectInput
								label={__('Inject in', 'zaplane')}
								value={auth.inject?.in || 'header'}
								onChange={(v) => patchInject({ in: v })}
								options={INJECT_IN}
							/>
							<TextInput
								label={__('Name', 'zaplane')}
								value={auth.inject?.name}
								onChange={(v) => patchInject({ name: v })}
								placeholder="Authorization"
							/>
							<TextInput
								label={__('Value template', 'zaplane')}
								value={auth.inject?.template}
								onChange={(v) => patchInject({ template: v })}
								placeholder="Bearer {{ creds.api_key }}"
								mono
							/>
						</div>
					)}

					{type === 'oauth2' && (
						<div className="grid grid-cols-2 gap-3">
							<TextInput
								label={__('Authorize URL', 'zaplane')}
								value={auth.oauth2?.authorize_url}
								onChange={(v) => patchOauth({ authorize_url: v })}
								placeholder="https://provider.com/oauth/authorize"
								mono
							/>
							<TextInput
								label={__('Token URL', 'zaplane')}
								value={auth.oauth2?.token_url}
								onChange={(v) => patchOauth({ token_url: v })}
								placeholder="https://provider.com/oauth/token"
								mono
							/>
							<TextInput
								label={__('Refresh URL (optional)', 'zaplane')}
								value={auth.oauth2?.refresh_url}
								onChange={(v) => patchOauth({ refresh_url: v })}
								mono
							/>
							<TextInput
								label={__('Scopes (space or comma separated)', 'zaplane')}
								value={
									Array.isArray(auth.oauth2?.scopes)
										? auth.oauth2.scopes.join(' ')
										: auth.oauth2?.scopes || ''
								}
								onChange={(v) =>
									patchOauth({ scopes: v.split(/[\s,]+/).filter(Boolean) })
								}
							/>
						</div>
					)}

					<div className="grid grid-cols-2 gap-3">
						<TextInput
							label={__('Test endpoint path (optional)', 'zaplane')}
							value={auth.test?.path}
							onChange={(v) => patchTest({ path: v, method: auth.test?.method || 'GET' })}
							placeholder="/me"
							help={__('Used by "Test connection" to verify credentials.', 'zaplane')}
							mono
						/>
					</div>
				</>
			)}
		</div>
	);
};

export default AuthEditor;
