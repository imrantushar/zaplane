import { useEffect, useState } from "react";

import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import FolderCard from "./FolderCard";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { getFolders } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import CreateFolderModal from "@ZAPComponents/CreateFolderModal";
import WorkflowFolderEmptyState from "./WorkflowFolderEmptyState";
import FolderSkeleton from "@ZAPComponents/ZaplaneLoader/FolderSkeleton";
import PageLayout from "@ZAPComponents/PageLayout";
const Folders = () => {
  const dispatch = useDispatch();
  const [isFolderModalOpen, setIsFolderModalOpen] = useState(false);
  const {
    folders
  } = useSelector(state => state.folder);
  const allFolders = folders?.data || [];
  const isLoading = folders.isLoading;
  useEffect(() => {
    dispatch(getFolders());
  }, []);
  return <PageLayout title="Folders" isLoading={isLoading} skeleton={FolderSkeleton} actions={
                <button 
                    onClick={() => setIsFolderModalOpen(true)} 
                    className="bg-[var(--zaplane-primary)] hover:opacity-90 text-white font-medium py-2 px-6 rounded-lg transition-all shadow-sm active:scale-95"
                >
                    {__("Create Folder", "zaplane")}
                </button>
            }>
            {allFolders.length ? <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {allFolders?.map(folder => <FolderCard key={folder.id} folder={folder} />)}
                </div> : <WorkflowFolderEmptyState />}

            <CreateFolderModal isOpen={isFolderModalOpen} onClose={() => setIsFolderModalOpen(false)} />
        </PageLayout>;
};
export default Folders;