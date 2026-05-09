import React, { useCallback, useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { __ } from '@wordpress/i18n';

import {
	fetchWorkerStatus,
	fetchWorkerEnvironment,
	fetchWorkerSettings,
	saveWorkerSettings,
	fetchWorkerUnitFile,
	fetchWorkerFailures,
	fetchWorkerRetention,
	saveWorkerRetention,
	runWorkerCleanup,
	setWorkerEnabled,
} from '@ZAPRedux/Slices/workerSlice/workerSlice';

const HEALTH_STYLES = {
	up: { dot: 'bg-green-500', text: 'text-green-600', label: 'Running' },
	warming: { dot: 'bg-amber-500', text: 'text-amber-600', label: 'Starting' },
	down: { dot: 'bg-red-500', text: 'text-red-600', label: 'Not running' },
	unknown: { dot: 'bg-gray-400', text: 'text-gray-500', label: 'Unknown' },
};

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

const Check = ({ ok, children }) => (
	<div className="flex items-center gap-2 py-1">
		<span
			className={`inline-flex items-center justify-center w-5 h-5 rounded-full text-white text-xs ${
				ok ? 'bg-green-500' : 'bg-red-500'
			}`}
		>
			{ok ? '✓' : '✗'}
		</span>
		<span className="text-sm text-gray-700">{children}</span>
	</div>
);

const formatAge = (ts) => {
	if (!ts) return '—';
	const diff = Math.max(0, Math.floor(Date.now() / 1000) - ts);
	if (diff < 60) return `${diff}s ago`;
	if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
	if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
	return `${Math.floor(diff / 86400)}d ago`;
};

const formatUptime = (start) => {
	if (!start) return '—';
	const diff = Math.max(0, Math.floor(Date.now() / 1000) - start);
	if (diff < 60) return `${diff}s`;
	if (diff < 3600) return `${Math.floor(diff / 60)}m ${diff % 60}s`;
	if (diff < 86400) return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m`;
	return `${Math.floor(diff / 86400)}d ${Math.floor((diff % 86400) / 3600)}h`;
};

const Intro = ({ onEnable, isSaving }) => (
	<div className="bg-white border border-gray-200 rounded-lg shadow-sm p-8">
		<div className="flex items-start gap-4 mb-6">
			<div className="shrink-0 w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-2xl">
				⚡
			</div>
			<div className="flex-1">
				<h2 className="text-xl font-semibold text-gray-900 m-0 mb-1">
					{__('Background Worker', 'zaplane')}
				</h2>
				<p className="text-sm text-gray-600 m-0">
					{__(
						'A long-lived PHP process that drains the Zaplane queue continuously, so workflows fire instantly instead of waiting for wp-cron.',
						'zaplane'
					)}
				</p>
			</div>
		</div>

		<div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
			<div className="border border-gray-200 rounded-lg p-4">
				<div className="text-sm font-semibold text-gray-900 mb-1">
					{__('Sub-second triggers', 'zaplane')}
				</div>
				<div className="text-xs text-gray-600">
					{__('Workflows execute as soon as they are queued — no minute-resolution wait.', 'zaplane')}
				</div>
			</div>
			<div className="border border-gray-200 rounded-lg p-4">
				<div className="text-sm font-semibold text-gray-900 mb-1">
					{__('No PHP timeouts', 'zaplane')}
				</div>
				<div className="text-xs text-gray-600">
					{__('Long-running steps complete reliably; the daemon manages its own memory budget.', 'zaplane')}
				</div>
			</div>
			<div className="border border-gray-200 rounded-lg p-4">
				<div className="text-sm font-semibold text-gray-900 mb-1">
					{__('Safe for other plugins', 'zaplane')}
				</div>
				<div className="text-xs text-gray-600">
					{__('Only drains the zaplane queue. wp-cron and WooCommerce keep working unchanged.', 'zaplane')}
				</div>
			</div>
		</div>

		<div className="bg-amber-50 border border-amber-200 rounded p-3 mb-6 text-xs text-amber-900">
			{__(
				'Requires Linux VPS with systemd or supervisor and SSH access. Will not work on shared hosting.',
				'zaplane'
			)}
		</div>

		<button
			type="button"
			onClick={onEnable}
			disabled={isSaving}
			className="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50"
		>
			{isSaving ? __('Enabling…', 'zaplane') : __('Enable Background Worker', 'zaplane')}
		</button>
	</div>
);

const BackgroundWorker = () => {
	const dispatch = useDispatch();
	const {
		status,
		jobsPerMin,
		environment,
		recommendedRunner,
		settings,
		unitFile,
		failures,
		failuresTotal,
		retention,
		isSavingSettings,
		isLoadingUnitFile,
		isLoadingFailures,
		isSavingRetention,
		isRunningCleanup,
	} = useSelector((state) => state.worker);

	const enabled = settings?.enabled === true;

	const [form, setForm] = useState(settings);
	const [retentionDays, setRetentionDays] = useState(retention?.days ?? 30);
	const [copied, setCopied] = useState(false);

	useEffect(() => {
		dispatch(fetchWorkerSettings());
	}, [dispatch]);

	useEffect(() => {
		if (!enabled) return;
		dispatch(fetchWorkerStatus());
		dispatch(fetchWorkerEnvironment());
		dispatch(fetchWorkerFailures(20));
		dispatch(fetchWorkerRetention());
	}, [dispatch, enabled]);

	useEffect(() => {
		setForm(settings);
	}, [settings]);

	useEffect(() => {
		if (retention?.days !== undefined) {
			setRetentionDays(retention.days);
		}
	}, [retention?.days]);

	useEffect(() => {
		if (!enabled) return;
		if (form?.runner) {
			dispatch(fetchWorkerUnitFile(form.runner));
		}
	}, [dispatch, form?.runner, enabled]);

	useEffect(() => {
		if (!enabled) return;
		const tick = () => {
			if (typeof document !== 'undefined' && document.hidden) return;
			dispatch(fetchWorkerStatus());
		};
		const id = setInterval(tick, 5000);
		return () => clearInterval(id);
	}, [dispatch, enabled]);

	const handleSave = useCallback(
		(e) => {
			e.preventDefault();
			dispatch(saveWorkerSettings(form)).then(() => {
				dispatch(fetchWorkerUnitFile(form.runner));
			});
		},
		[dispatch, form]
	);

	const handleCopy = useCallback(async () => {
		if (!unitFile?.content) return;
		try {
			await navigator.clipboard.writeText(unitFile.content);
			setCopied(true);
			setTimeout(() => setCopied(false), 2000);
		} catch (err) {
			// ignore
		}
	}, [unitFile]);

	if (!enabled) {
		return (
			<Intro
				onEnable={() => dispatch(setWorkerEnabled(true))}
				isSaving={isSavingSettings}
			/>
		);
	}

	const health = status?.health ?? 'unknown';
	const healthStyle = HEALTH_STYLES[health] || HEALTH_STYLES.unknown;

	return (
		<>
			<div className="flex items-center justify-between bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6">
				<div className="flex items-center gap-3">
					<span className="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-base">
						⚡
					</span>
					<div>
						<div className="text-sm font-semibold text-gray-900">
							{__('Background Worker is enabled', 'zaplane')}
						</div>
						<div className="text-xs text-gray-600">
							{__(
								'Workflows in the zaplane queue fire instantly. wp-cron and other plugins keep working as before.',
								'zaplane'
							)}
						</div>
					</div>
				</div>
				<button
					type="button"
					onClick={() => dispatch(setWorkerEnabled(false))}
					className="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50"
				>
					{__('Disable', 'zaplane')}
				</button>
			</div>

			<Card
				title={
					<div className="flex items-center gap-3">
						<span>{__('Worker status', 'zaplane')}</span>
						<span className={`inline-block w-2.5 h-2.5 rounded-full ${healthStyle.dot}`} />
						<span className={`text-sm font-normal ${healthStyle.text}`}>
							{__(healthStyle.label, 'zaplane')}
						</span>
					</div>
				}
				actions={
					<button
						type="button"
						onClick={() => dispatch(fetchWorkerStatus())}
						className="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50"
					>
						{__('Refresh', 'zaplane')}
					</button>
				}
			>
				<div className="grid grid-cols-2 md:grid-cols-4 gap-4">
					<Stat label={__('PID', 'zaplane')} value={status?.pid || '—'} />
					<Stat label={__('Host', 'zaplane')} value={status?.host || '—'} />
					<Stat label={__('Uptime', 'zaplane')} value={formatUptime(status?.started_at)} />
					<Stat label={__('Last heartbeat', 'zaplane')} value={formatAge(status?.updated_at)} />
					<Stat label={__('Jobs processed', 'zaplane')} value={status?.jobs_processed ?? 0} />
					<Stat label={__('Jobs / min', 'zaplane')} value={jobsPerMin || 0} />
					<Stat label={__('Jobs failed', 'zaplane')} value={status?.jobs_failed ?? 0} />
					<Stat
						label={__('Avg runtime', 'zaplane')}
						value={status?.avg_runtime_ms ? `${status.avg_runtime_ms} ms` : '—'}
					/>
					<Stat
						label={__('Memory', 'zaplane')}
						value={
							status?.memory_mb !== undefined
								? `${status.memory_mb} MB / ${status?.memory_peak_mb || 0} MB peak`
								: '—'
						}
					/>
					<Stat
						label={__('Queue depth', 'zaplane')}
						value={status?.queue_depth >= 0 ? status.queue_depth : '—'}
					/>
					<Stat label={__('Group', 'zaplane')} value={status?.group || 'zaplane'} />
					<Stat label={__('State', 'zaplane')} value={status?.reported_state || '—'} />
				</div>
				{status?.stop_reason ? (
					<div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
						{__('Stop reason:', 'zaplane')}{' '}
						<code className="bg-white px-1.5 py-0.5 rounded">{status.stop_reason}</code>
					</div>
				) : null}
			</Card>

			{failures && failures.length > 0 ? (
				<Card
					title={
						<div className="flex items-center gap-3">
							<span>{__('Recent failures', 'zaplane')}</span>
							<span className="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-semibold">
								{failuresTotal}
							</span>
						</div>
					}
					actions={
						<button
							type="button"
							onClick={() => dispatch(fetchWorkerFailures(20))}
							className="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50"
						>
							{isLoadingFailures ? __('Refreshing…', 'zaplane') : __('Refresh', 'zaplane')}
						</button>
					}
				>
					<p className="text-sm text-gray-600 mb-3">
						{__('Failed actions in the zaplane group. Read from Action Scheduler logs.', 'zaplane')}
					</p>
					<div className="overflow-x-auto">
						<table className="min-w-full text-sm">
							<thead>
								<tr className="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
									<th className="py-2 pr-4 font-medium">{__('ID', 'zaplane')}</th>
									<th className="py-2 pr-4 font-medium">{__('Hook', 'zaplane')}</th>
									<th className="py-2 pr-4 font-medium">{__('Failed at', 'zaplane')}</th>
									<th className="py-2 pr-4 font-medium">{__('Error', 'zaplane')}</th>
								</tr>
							</thead>
							<tbody>
								{failures.map((f) => (
									<tr key={f.id} className="border-b border-gray-100 align-top">
										<td className="py-2 pr-4 font-mono text-xs text-gray-700">{f.id}</td>
										<td className="py-2 pr-4 font-mono text-xs text-gray-700">{f.hook}</td>
										<td className="py-2 pr-4 text-xs text-gray-600 whitespace-nowrap">
											{f.failed_at
												? new Date(f.failed_at).toLocaleString()
												: f.scheduled_for
													? new Date(f.scheduled_for).toLocaleString()
													: '—'}
										</td>
										<td className="py-2 pr-4 text-xs text-red-700 break-words max-w-2xl">
											{f.message || '—'}
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
					{failuresTotal > failures.length ? (
						<p className="text-xs text-gray-500 mt-3">
							{__('Showing', 'zaplane')} {failures.length} {__('of', 'zaplane')} {failuresTotal}{' '}
							{__('failed actions.', 'zaplane')}
						</p>
					) : null}
				</Card>
			) : null}

			<Card title={__('Environment', 'zaplane')}>
				<div className="grid grid-cols-1 md:grid-cols-2 gap-x-8">
					<div>
						<Check ok={environment?.os === 'Linux'}>
							{__('Operating system:', 'zaplane')}{' '}
							<span className="font-mono">{environment?.os || '—'}</span>
						</Check>
						<Check ok={!!environment?.has_systemd}>{__('systemd available', 'zaplane')}</Check>
						<Check ok={!!environment?.has_supervisor}>{__('supervisor available', 'zaplane')}</Check>
						<Check ok={!!environment?.has_pcntl}>{__('PCNTL signal handling', 'zaplane')}</Check>
					</div>
					<div className="text-sm text-gray-700 space-y-2">
						<div>
							<div className="text-xs uppercase tracking-wide text-gray-500">{__('WP path', 'zaplane')}</div>
							<code className="block bg-gray-50 px-2 py-1 rounded text-xs break-all">
								{environment?.wp_path || '—'}
							</code>
						</div>
						<div>
							<div className="text-xs uppercase tracking-wide text-gray-500">{__('WP-CLI binary', 'zaplane')}</div>
							<code className="block bg-gray-50 px-2 py-1 rounded text-xs break-all">
								{environment?.wp_cli_bin || '—'}
							</code>
						</div>
						<div>
							<div className="text-xs uppercase tracking-wide text-gray-500">{__('Run user', 'zaplane')}</div>
							<code className="block bg-gray-50 px-2 py-1 rounded text-xs">{environment?.run_user || '—'}</code>
						</div>
						<div>
							<div className="text-xs uppercase tracking-wide text-gray-500">
								{__('PHP memory limit', 'zaplane')}
							</div>
							<code className="block bg-gray-50 px-2 py-1 rounded text-xs">
								{environment?.memory_limit || '—'}
							</code>
						</div>
						<div>
							<div className="text-xs uppercase tracking-wide text-gray-500">
								{__('Recommended runner', 'zaplane')}
							</div>
							<code className="block bg-gray-50 px-2 py-1 rounded text-xs font-bold">
								{recommendedRunner || '—'}
							</code>
						</div>
					</div>
				</div>
				{recommendedRunner === 'unsupported' ? (
					<div className="mt-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-800">
						{__(
							'The worker daemon requires Linux with systemd or supervisor. Use cloud-managed workers instead.',
							'zaplane'
						)}
					</div>
				) : null}
			</Card>

			<Card title={__('Worker settings', 'zaplane')}>
				<form onSubmit={handleSave} className="space-y-4">
					<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
						<label className="flex flex-col">
							<span className="text-sm font-medium text-gray-700 mb-1">{__('Runner', 'zaplane')}</span>
							<select
								value={form.runner || 'systemd'}
								onChange={(e) => setForm({ ...form, runner: e.target.value })}
								className="border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
							>
								<option value="systemd">systemd</option>
								<option value="supervisor">supervisor</option>
							</select>
						</label>
						<label className="flex flex-col">
							<span className="text-sm font-medium text-gray-700 mb-1">
								{__('Memory cap (MB)', 'zaplane')}
							</span>
							<input
								type="number"
								min="64"
								step="32"
								value={form.memory ?? 256}
								onChange={(e) => setForm({ ...form, memory: parseInt(e.target.value, 10) || 0 })}
								className="border border-gray-300 rounded px-3 py-2 text-sm"
							/>
							<span className="text-xs text-gray-500 mt-1">
								{__('Worker exits cleanly above this; supervisor restarts it.', 'zaplane')}
							</span>
						</label>
						<label className="flex flex-col">
							<span className="text-sm font-medium text-gray-700 mb-1">
								{__('Max jobs per process', 'zaplane')}
							</span>
							<input
								type="number"
								min="0"
								step="100"
								value={form.max_jobs ?? 1000}
								onChange={(e) => setForm({ ...form, max_jobs: parseInt(e.target.value, 10) || 0 })}
								className="border border-gray-300 rounded px-3 py-2 text-sm"
							/>
							<span className="text-xs text-gray-500 mt-1">
								{__('Exits after N jobs to mitigate leaks. 0 = disable.', 'zaplane')}
							</span>
						</label>
						<label className="flex flex-col">
							<span className="text-sm font-medium text-gray-700 mb-1">
								{__('Max uptime (seconds)', 'zaplane')}
							</span>
							<input
								type="number"
								min="0"
								step="60"
								value={form.max_time ?? 3600}
								onChange={(e) => setForm({ ...form, max_time: parseInt(e.target.value, 10) || 0 })}
								className="border border-gray-300 rounded px-3 py-2 text-sm"
							/>
							<span className="text-xs text-gray-500 mt-1">
								{__('Exits after N seconds. 0 = run forever.', 'zaplane')}
							</span>
						</label>
					</div>
					<div className="pt-2">
						<button
							type="submit"
							disabled={isSavingSettings}
							className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50"
						>
							{isSavingSettings
								? __('Saving…', 'zaplane')
								: __('Save & regenerate unit file', 'zaplane')}
						</button>
					</div>
				</form>
			</Card>

			<Card title={__('Run history retention', 'zaplane')}>
				<p className="text-sm text-gray-600 mb-4">
					{__(
						'Workflow run history grows quickly on busy sites. The cleanup job runs daily and deletes runs (and their node-runs) older than the configured window.',
						'zaplane'
					)}
				</p>
				<form
					onSubmit={(e) => {
						e.preventDefault();
						dispatch(saveWorkerRetention(retentionDays));
					}}
					className="flex flex-wrap items-end gap-4"
				>
					<label className="flex flex-col">
						<span className="text-sm font-medium text-gray-700 mb-1">
							{__('Keep history for (days)', 'zaplane')}
						</span>
						<input
							type="number"
							min="0"
							step="1"
							value={retentionDays}
							onChange={(e) => setRetentionDays(parseInt(e.target.value, 10) || 0)}
							className="border border-gray-300 rounded px-3 py-2 text-sm w-40"
						/>
						<span className="text-xs text-gray-500 mt-1">{__('0 = disable cleanup.', 'zaplane')}</span>
					</label>
					<button
						type="submit"
						disabled={isSavingRetention}
						className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50"
					>
						{isSavingRetention ? __('Saving…', 'zaplane') : __('Save', 'zaplane')}
					</button>
					<button
						type="button"
						onClick={() => dispatch(runWorkerCleanup())}
						disabled={isRunningCleanup}
						className="px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded hover:bg-gray-50 disabled:opacity-50"
					>
						{isRunningCleanup ? __('Running…', 'zaplane') : __('Run cleanup now', 'zaplane')}
					</button>
				</form>

				{retention?.last_run && retention.last_run.finished_at ? (
					<div className="mt-5 text-xs text-gray-600 grid grid-cols-2 md:grid-cols-4 gap-3 border-t border-gray-100 pt-4">
						<Stat
							label={__('Last cleanup', 'zaplane')}
							value={
								retention.last_run.finished_at
									? new Date(retention.last_run.finished_at * 1000).toLocaleString()
									: '—'
							}
						/>
						<Stat
							label={__('Runs deleted', 'zaplane')}
							value={retention.last_run.runs_deleted ?? 0}
						/>
						<Stat
							label={__('Node-runs deleted', 'zaplane')}
							value={retention.last_run.node_runs_deleted ?? 0}
						/>
						<Stat
							label={__('Duration', 'zaplane')}
							value={
								retention.last_run.duration_ms !== undefined
									? `${retention.last_run.duration_ms} ms`
									: '—'
							}
						/>
					</div>
				) : null}
			</Card>

			<Card
				title={
					unitFile?.type === 'supervisor'
						? __('supervisor configuration', 'zaplane')
						: __('systemd unit file', 'zaplane')
				}
				actions={
					<button
						type="button"
						onClick={handleCopy}
						className="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50"
					>
						{copied ? __('Copied!', 'zaplane') : __('Copy', 'zaplane')}
					</button>
				}
			>
				<p className="text-sm text-gray-600 mb-3">
					{__('Save this content to', 'zaplane')}{' '}
					<code className="bg-gray-100 px-1.5 py-0.5 rounded text-xs">{unitFile?.path || '—'}</code>{' '}
					{__('on your server.', 'zaplane')}
				</p>
				<textarea
					readOnly
					value={isLoadingUnitFile ? __('Loading…', 'zaplane') : unitFile?.content || ''}
					rows={12}
					className="w-full font-mono text-xs bg-gray-50 border border-gray-200 rounded p-3 resize-y"
				/>

				<h3 className="mt-6 mb-2 text-base font-semibold text-gray-900">
					{__('Install commands', 'zaplane')}
				</h3>
				<p className="text-sm text-gray-600 mb-3">
					{__(
						'Run these on your VPS as a user with sudo. The plugin process cannot run them for you.',
						'zaplane'
					)}
				</p>
				<ol className="list-decimal list-inside space-y-2">
					{(unitFile?.install_steps || []).map((step, i) => (
						<li key={i}>
							<pre className="inline-block w-full bg-gray-900 text-gray-100 text-xs rounded p-3 mt-1 overflow-x-auto whitespace-pre-wrap">
								{step}
							</pre>
						</li>
					))}
				</ol>

				<div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
					<h3 className="m-0 mb-2 text-sm font-semibold text-blue-900">
						{__('What about wp-cron?', 'zaplane')}
					</h3>
					<p className="text-sm text-blue-900 m-0">
						{__(
							'Leave wp-cron alone. This worker only drains the zaplane Action Scheduler group, so Zaplane workflows fire sub-second. WooCommerce, Yoast, backups, and every other plugin that relies on wp-cron or other AS groups keep working as before.',
							'zaplane'
						)}
					</p>
					<p className="text-xs text-blue-800 mt-2 mb-0">
						{__(
							'Do NOT add define( \'DISABLE_WP_CRON\', true ) — that will break other plugins.',
							'zaplane'
						)}
					</p>
				</div>
			</Card>
		</>
	);
};

export default BackgroundWorker;
