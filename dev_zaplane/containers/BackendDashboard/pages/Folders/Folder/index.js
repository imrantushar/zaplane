import { __ } from '@wordpress/i18n';
import { route_path } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';

import { getFolders } from '@ZAPRedux/Slices/folderSlice/folderSlice';
import { useDispatch, useSelector } from 'react-redux';
import WorkflowTable from '@ZAPComponents/WorkflowTable';
import PageLayout from "@ZAPComponents/PageLayout";

const Folder = ({ id }) => {
    const dispatch =useDispatch()
     const { folders } = useSelector((state) => state.folder);
      const allFolders = folders?.data || [];
    
      useEffect(() => {
        dispatch(getFolders());
      }, [dispatch]);
      const title = allFolders.find((v)=>v.id === id)
    return (
        <PageLayout
            breadcrumbs={[
                { label: 'Folders', href: `${route_path}admin.php?page=zaplane-folders` },
                { label: title?.title || 'Loading...' }
            ]}
        >
            <WorkflowTable folderId={id} showHeader />
        </PageLayout>
    );
};

export default Folder;