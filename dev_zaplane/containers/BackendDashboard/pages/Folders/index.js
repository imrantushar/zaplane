import { useEffect, useState } from "react";
import {
    Button,
    SimpleGrid,

} from "@chakra-ui/react";
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
    const { folders } = useSelector((state) => state.folder);
    const allFolders = folders?.data || [];
    const isLoading = folders.isLoading
    useEffect(() => {
        dispatch(getFolders());

    }, []);


    return (
        <PageLayout
            title="Folders"
            isLoading={isLoading}
            skeleton={FolderSkeleton}
            actions={
                <Button
                    onClick={() => setIsFolderModalOpen(true)}
                    {...primaryBtn}
                >
                    {__("Create Folder", "zaplane")}
                </Button>
            }
        >
            {allFolders.length ? (
                <SimpleGrid columns={{ base: 1, sm: 2, lg: 3 }} spacing={4} gap='20px'>
                    {allFolders?.map((folder) => (
                        <FolderCard key={folder.id} folder={folder} />
                    ))}
                </SimpleGrid>
            ) : (
                <WorkflowFolderEmptyState />
            )}

            <CreateFolderModal
                isOpen={isFolderModalOpen}
                onClose={() => setIsFolderModalOpen(false)}
            />
        </PageLayout>
    );
};

export default Folders;