import { __, sprintf } from '@wordpress/i18n';
import { API, namespace, route_path } from '@ZAPUtils/helper';
import React, { useEffect, useState } from 'react';
import { FiLayers } from 'react-icons/fi';

import { getFolders, getFolderWorkflows } from '@ZAPRedux/Slices/folderSlice/folderSlice';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';
import { useDispatch, useSelector } from 'react-redux';
import WorkflowTable from '@ZAPComponents/WorkflowTable';
import PageLayout from "@ZAPComponents/PageLayout";
import { primaryBtn } from '../../../../../../assets/scss/chakra/recipe';

const secondaryBtn = {
    padding: '8px 16px',
    borderRadius: '4px',
    border: '1px solid var(--zaplane-border-color)',
    backgroundColor: 'var(--zaplane-background)',
    color: 'var(--zaplane-font-color)',
    fontSize: '13px',
    fontWeight: 600,
    cursor: 'pointer',
};

const Folder = ({ id }) => {
    const dispatch =useDispatch()
     const { folders, folderWorkflows } = useSelector((state) => state.folder);
      const allFolders = folders?.data || [];
      const [changing, setChanging] = useState(null);

      useEffect(() => {
        dispatch(getFolders());
      }, [dispatch]);
      const title = allFolders.find((v)=>v.id === id)

    // Switches on every workflow in the folder, or pauses the ones that are on.
    const setAll = async status => {
        setChanging(status);
        try {
            const res = await API.post(`${namespace}folders/${id}/status`, { status });
            const rows = res?.data?.workflows || [];
            const failed = rows.filter(row => row.error);

            let message;
            if (failed.length === 1) {
                message = sprintf(__('“%1$s” could not be turned on: %2$s', 'zaplane'), failed[0].title, failed[0].error);
            } else if (failed.length) {
                message = sprintf(__('%d workflows could not be turned on. Open each one to see what it still needs.', 'zaplane'), failed.length);
            } else {
                message = status === 'active'
                    ? __('Every workflow in this folder is on.', 'zaplane')
                    : __('The workflows in this folder are paused.', 'zaplane');
            }

            dispatch(showNotification({ isShow: true, type: failed.length ? 'error' : 'success', message }));
            await dispatch(getFolderWorkflows({
                folder_id: id,
                page: folderWorkflows?.currentPage || 1,
                perPage: folderWorkflows?.itemPerPage || 10,
            }));
        } catch (e) {
            dispatch(showNotification({
                isShow: true,
                type: 'error',
                message: e?.response?.data?.message || __('The workflows could not be changed.', 'zaplane'),
            }));
        }
        setChanging(null);
    };

    return (
        <PageLayout
            breadcrumbs={[
                { label: 'Folders', href: `${route_path}admin.php?page=zaplane-folders` },
                { label: title?.title || 'Loading...' }
            ]}
            actions={
                <div className="flex items-center gap-2">
                    <button type="button" style={{ ...secondaryBtn, opacity: changing ? 0.5 : 1 }} disabled={!!changing} onClick={() => setAll('paused')}>
                        {changing === 'paused' ? __('Pausing…', 'zaplane') : __('Pause all', 'zaplane')}
                    </button>
                    <button type="button" style={{ ...primaryBtn, opacity: changing ? 0.5 : 1 }} disabled={!!changing} onClick={() => setAll('active')}>
                        {changing === 'active' ? __('Turning on…', 'zaplane') : __('Turn all on', 'zaplane')}
                    </button>
                </div>
            }
        >
            {title?.recipe && (
                <p className="mb-4 flex items-center gap-2 text-[13px] text-[var(--zaplane-font-secondary-color)]">
                    <FiLayers size={13} />
                    {sprintf(__('Set up from the “%s” group recipe', 'zaplane'), title.recipe.title)}
                </p>
            )}
            <WorkflowTable folderId={id} showHeader />
        </PageLayout>
    );
};

export default Folder;
