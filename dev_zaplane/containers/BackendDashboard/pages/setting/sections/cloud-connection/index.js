import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { __ } from '@wordpress/i18n';

import {
	fetchCloudStatus,
	pairCloud,
	disconnectCloud,
	testCloud,
} from '@ZAPRedux/Slices/cloudSlice/cloudSlice';

const Card = ({ title, children, actions }) => (
	<div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-6">
		<div className="flex items-center justify-between mb-4">
			<h2 className="text-lg font-semibold text-gray-900 m-0">{title}</h2>
			{actions ? <div>{actions}</div> : null}
		</div>
		{children}
	</div>
);

const Stat = ({ label, value }) => (
	<div className="flex flex-col">
		<span className="text-xs uppercase tracking-wide text-gray-500">{label}</span>
		<span className="text-base font-medium text-gray-900 mt-1 break-all">{value ?? '—'}</span>
	</div>
);

const PairForm = ({ onSubmit, isPairing }) => {
	const [cloudUrl, setCloudUrl] = useState('https://app.zaplane.pro');
	const [code, setCode] = useState('');

	return (
		<div className="bg-white border border-gray-200 rounded-lg shadow-sm p-8">
			<div className="flex items-start gap-4 mb-6">
				<div className="shrink-0 w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-2xl">
					☁
				</div>
				<div className="flex-1">
					<h2 className="text-xl font-semibold text-gray-900 m-0 mb-1">
						{__('Connect to Zaplane Cloud', 'zaplane')}
					</h2>
					<p className="text-sm text-gray-600 m-0">
						{__(
							'Pair this site with your Zaplane Cloud workspace to unlock cross-site automation, durable run history, and team collaboration.',
							'zaplane'
						)}
					</p>
				</div>
			</div>

			<div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
				<div className="border border-gray-200 rounded-lg p-4">
					<div className="text-sm font-semibold text-gray-900 mb-1">
						{__('Cross-site workflows', 'zaplane')}
					</div>
					<div className="text-xs text-gray-600">
						{__('One workflow can span multiple WordPress sites in your team.', 'zaplane')}
					</div>
				</div>
				<div className="border border-gray-200 rounded-lg p-4">
					<div className="text-sm font-semibold text-gray-900 mb-1">
						{__('Durable run history', 'zaplane')}
					</div>
					<div className="text-xs text-gray-600">
						{__('Run logs live in the cloud. Replay any failed step with edited inputs.', 'zaplane')}
					</div>
				</div>
				<div className="border border-gray-200 rounded-lg p-4">
					<div className="text-sm font-semibold text-gray-900 mb-1">
						{__('Team workspaces', 'zaplane')}
					</div>
					<div className="text-xs text-gray-600">
						{__('Invite teammates, share workflows, audit who changed what.', 'zaplane')}
					</div>
				</div>
			</div>

			<form
				onSubmit={(e) => {
					e.preventDefault();
					onSubmit({ cloud_url: cloudUrl.trim(), code: code.trim().toUpperCase() });
				}}
				className="space-y-4"
			>
				<label className="flex flex-col">
					<span className="text-sm font-medium text-gray-700 mb-1">{__('Cloud URL', 'zaplane')}</span>
					<input
						type="url"
						value={cloudUrl}
						onChange={(e) => setCloudUrl(e.target.value)}
						required
						className="border border-gray-300 rounded px-3 py-2 text-sm w-full max-w-md"
					/>
					<span className="text-xs text-gray-500 mt-1">
						{__('Default: https://app.zaplane.pro. Change for self-hosted clouds.', 'zaplane')}
					</span>
				</label>
				<label className="flex flex-col">
					<span className="text-sm font-medium text-gray-700 mb-1">
						{__('Pairing code', 'zaplane')}
					</span>
					<input
						type="text"
						value={code}
						onChange={(e) => setCode(e.target.value.toUpperCase())}
						required
						maxLength={8}
						placeholder="ABCD2345"
						className="border border-gray-300 rounded px-3 py-2 text-sm font-mono uppercase tracking-widest w-48"
					/>
					<span className="text-xs text-gray-500 mt-1">
						{__('Generate one at app.zaplane.pro → New site. Codes expire after 15 minutes.', 'zaplane')}
					</span>
				</label>
				<button
					type="submit"
					disabled={isPairing || !code || !cloudUrl}
					className="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50"
				>
					{isPairing ? __('Connecting…', 'zaplane') : __('Connect', 'zaplane')}
				</button>
			</form>
		</div>
	);
};

const CloudConnection = () => {
	const dispatch = useDispatch();
	const { status, isLoadingStatus, isPairing, isDisconnecting, isTesting, lastTestResult } = useSelector(
		(state) => state.cloud
	);

	useEffect(() => {
		dispatch(fetchCloudStatus());
	}, [dispatch]);

	if (!status?.paired) {
		return <PairForm onSubmit={(payload) => dispatch(pairCloud(payload))} isPairing={isPairing} />;
	}

	return (
		<>
			<div className="flex items-center justify-between bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6">
				<div className="flex items-center gap-3">
					<span className="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-50 text-base">
						☁
					</span>
					<div>
						<div className="text-sm font-semibold text-gray-900">
							{__('Connected to Zaplane Cloud', 'zaplane')}
						</div>
						<div className="text-xs text-gray-600">
							{__('Team:', 'zaplane')}{' '}
							<span className="font-medium">{status.team_name || '—'}</span>
						</div>
					</div>
				</div>
				<div className="flex items-center gap-2">
					<button
						type="button"
						onClick={() => dispatch(testCloud())}
						disabled={isTesting}
						className="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50"
					>
						{isTesting ? __('Testing…', 'zaplane') : __('Test connection', 'zaplane')}
					</button>
					<button
						type="button"
						onClick={() => {
							if (confirm(__('Disconnect from Zaplane Cloud? Workflows in progress will continue to use the cloud until they complete.', 'zaplane'))) {
								dispatch(disconnectCloud());
							}
						}}
						disabled={isDisconnecting}
						className="px-3 py-1.5 text-sm bg-white border border-red-300 text-red-700 rounded hover:bg-red-50 disabled:opacity-50"
					>
						{isDisconnecting ? __('Disconnecting…', 'zaplane') : __('Disconnect', 'zaplane')}
					</button>
				</div>
			</div>

			<Card title={__('Connection details', 'zaplane')}>
				<div className="grid grid-cols-2 md:grid-cols-3 gap-4">
					<Stat label={__('Cloud URL', 'zaplane')} value={status.cloud_url || '—'} />
					<Stat label={__('Site ID', 'zaplane')} value={status.site_id || '—'} />
					<Stat label={__('Team ID', 'zaplane')} value={status.team_id || '—'} />
					<Stat
						label={__('Paired since', 'zaplane')}
						value={status.paired_at ? new Date(status.paired_at * 1000).toLocaleString() : '—'}
					/>
					<Stat label={__('Heartbeat endpoint', 'zaplane')} value={status.heartbeat_endpoint || '—'} />
					<Stat label={__('Ingest endpoint', 'zaplane')} value={status.ingest_endpoint || '—'} />
				</div>

				{lastTestResult ? (
					<div
						className={`mt-5 p-3 rounded text-sm border ${
							lastTestResult.ok
								? 'bg-green-50 border-green-200 text-green-800'
								: 'bg-red-50 border-red-200 text-red-800'
						}`}
					>
						<strong>
							{lastTestResult.ok ? __('Last test: OK', 'zaplane') : __('Last test: failed', 'zaplane')}
						</strong>{' '}
						<span className="text-xs opacity-75">
							{new Date(lastTestResult.at * 1000).toLocaleTimeString()}
						</span>
					</div>
				) : null}
			</Card>

			<Card title={__('How does it work?', 'zaplane')}>
				<ul className="text-sm text-gray-700 space-y-2 list-disc pl-5">
					<li>
						{__(
							'When a Zaplane workflow trigger fires on this site, the event is forwarded to your cloud workspace and executed there.',
							'zaplane'
						)}
					</li>
					<li>
						{__(
							'Steps that target WordPress (e.g. enroll a user in LearnDash) are sent back here as signed REST callbacks.',
							'zaplane'
						)}
					</li>
					<li>
						{__(
							'Run history, retries, and dead-letter handling all live in the cloud — your local database stays small.',
							'zaplane'
						)}
					</li>
					<li>
						{__(
							'If the cloud becomes unreachable for more than 10 minutes, the plugin falls back to running workflows locally so customers are not blocked.',
							'zaplane'
						)}
					</li>
				</ul>
			</Card>
		</>
	);
};

export default CloudConnection;
