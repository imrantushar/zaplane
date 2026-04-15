import { useEffect, useState } from "react";
import {
    Box,
    Button,
    Flex,

    Image,
    SimpleGrid,

} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import TopBar from "@ZAPComponents/TopBar";
import { plugin_root_url } from "@ZAPUtils/helper";
import { IoIosArrowForward } from "react-icons/io";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import FolderCard from "./FolderCard";
import SubTopBar from "@ZAPComponents/SubTopBar";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { getFolders } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import CreateFolderModal from "@ZAPComponents/CreateFolderModal";
import WorkflowFolderEmptyState from "./WorkflowFolderEmptyState";
import ZAPLoading from "@ZAPComponents/Loading";


const Folders = () => {
    const dispatch = useDispatch();
    const [isFolderModalOpen, setIsFolderModalOpen] = useState(false);
    const { folders } = useSelector((state) => state.folder);
    const allFolders = folders?.data || [];
    const isLoading = folders.isLoading
    useEffect(() => {
        dispatch(getFolders());

    }, []);


   if(isLoading)return <ZAPLoading/>
    return (
        <>
            <TopBar
                leftContent={() => (
                    <>
                        <Flex
                            height="40px"
                            width="40px"
                            borderRadius="20px"
                            background="var(--zaplane-second-primary)"
                            alignItems="center"
                            justifyContent="center"
                        >
                            <img
                                src={`${plugin_root_url}assets/images/zaplane.svg`}
                            />
                        </Flex>

                        <IoIosArrowForward />

                        <ZAPLabel
                            as="h2"
                            type="subtitle"
                            fontWeight="medium"
                            label={__("Folders", "zaplane")}
                        />
                    </>
                )}
            />
            <SubTopBar heading={__("Folders", "zaplane")}>

                <Button
                    onClick={() => setIsFolderModalOpen(true)}
                    {...primaryBtn}
                >
                    {__("Create Folder", "zaplane")}
                </Button>
            </SubTopBar>

            <div className="zaplane-page-content">
                {allFolders.length ? (<SimpleGrid columns={{ base: 1, sm: 2, lg: 3 }} spacing={4} gap='20px'>
                    {allFolders?.map((folder) => (
                        <FolderCard key={folder.id} folder={folder} />
                    ))}
                </SimpleGrid>) :
                    <WorkflowFolderEmptyState />}


            </div>

            <CreateFolderModal
                isOpen={isFolderModalOpen}
                onClose={() => setIsFolderModalOpen(false)}
            />
        </>
    );
};

export default Folders;