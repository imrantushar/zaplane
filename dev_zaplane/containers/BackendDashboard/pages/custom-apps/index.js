import React, { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from 'react-redux';
import PageLayout from '@ZAPComponents/PageLayout';
import ZAPLoading from '@ZAPComponents/Loading';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';
import { primaryBtn, outlineBtn } from '../../../../../assets/scss/chakra/recipe';
import CustomAppList from './CustomAppList';
import Editor from './Editor';
import {
	fetchCustomApps,
	fetchCustomApp,
	createCustomApp,
	updateCustomApp,
	deleteCustomApp,
	importCustomApp,
} from './api';

const CustomApps = () => {
	const dispatch = useDispatch();
	const notify = (message, type = 'success') =>
		dispatch(showNotification({ message: __(message, 'zaplane'), isShow: true, type }));

	const [apps, setApps] = useState([]);
	const [loading, setLoading] = useState(true);
	const [view, setView] = useState('list'); // 'list' | 'new' | 'edit'
	const [editing, setEditing] = useState(null);
	const [saving, setSaving] = useState(false);
	const [errors, setErrors] = useState([]);
	const fileInput = useRef(null);

	const load = async () => {
		setLoading(true);
		try {
			setApps(await fetchCustomApps());
		} catch (e) {
			notify('Could not load custom apps.', 'error');
		} finally {
			setLoading(false);
		}
	};

	useEffect(() => {
		load();
	}, []);

	const openNew = () => {
		setEditing(null);
		setErrors([]);
		setView('new');
	};

	const openEdit = async (slug) => {
		setErrors([]);
		try {
			setEditing(await fetchCustomApp(slug));
			setView('edit');
		} catch (e) {
			notify('Could not open that app.', 'error');
		}
	};

	const backToList = () => {
		setView('list');
		setEditing(null);
	};

	const save = async (manifest) => {
		setSaving(true);
		setErrors([]);
		try {
			if (view === 'new') {
				await createCustomApp(manifest);
				notify('Custom app saved. A starter workflow was created for it.');
			} else {
				await updateCustomApp(manifest.slug, manifest);
				notify('Custom app saved.');
			}
			await load();
			backToList();
		} catch (e) {
			const data = e?.response?.data;
			setErrors(data?.data?.errors || [data?.message || __('Save failed.', 'zaplane')]);
			notify('Please fix the errors and try again.', 'error');
		} finally {
			setSaving(false);
		}
	};

	const remove = async (slug) => {
		// eslint-disable-next-line no-alert
		if (!window.confirm(__('Delete this custom app? Workflows using it will stop working.', 'zaplane'))) {
			return;
		}
		try {
			await deleteCustomApp(slug);
			notify('Custom app deleted.');
			await load();
			if (view !== 'list') backToList();
		} catch (e) {
			notify('Could not delete the app.', 'error');
		}
	};

	const exportApp = (app) => {
		const blob = new Blob([JSON.stringify(app, null, 2)], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `${app.slug}.zaplane-app.json`;
		a.click();
		URL.revokeObjectURL(url);
	};

	const onImportFile = async (e) => {
		const file = e.target.files?.[0];
		e.target.value = '';
		if (!file) return;
		try {
			const manifest = JSON.parse(await file.text());
			await importCustomApp(manifest);
			notify('Custom app imported.');
			await load();
		} catch (err) {
			const data = err?.response?.data;
			notify(data?.data?.errors?.[0] || __('Import failed — check the file.', 'zaplane'), 'error');
		}
	};

	if (view === 'new' || view === 'edit') {
		return (
			<PageLayout title="Custom Apps" heading={view === 'new' ? __('New Custom App', 'zaplane') : __('Edit Custom App', 'zaplane')}>
				<div className="zaplane-custom-apps">
					<Editor
						initial={editing}
						isNew={view === 'new'}
						onSave={save}
						onCancel={backToList}
						onDelete={remove}
						saving={saving}
						errors={errors}
					/>
				</div>
			</PageLayout>
		);
	}

	return (
		<PageLayout
			title="Custom Apps"
			heading={__('Custom Apps', 'zaplane')}
			actions={
				<div className="flex gap-2">
					<input ref={fileInput} type="file" accept="application/json" className="hidden" onChange={onImportFile} />
					<button type="button" style={outlineBtn} onClick={() => fileInput.current?.click()}>
						{__('Import', 'zaplane')}
					</button>
					<button type="button" style={primaryBtn} onClick={openNew}>
						{__('New app', 'zaplane')}
					</button>
				</div>
			}
		>
			{loading ? (
				<ZAPLoading />
			) : (
				<CustomAppList apps={apps} onEdit={openEdit} onDelete={remove} onExport={exportApp} onCreate={openNew} />
			)}
		</PageLayout>
	);
};

export default CustomApps;
