import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Box, Text, Icon, HStack, Input, Button, Spinner } from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { LuFolderOpen, LuFolderPlus, LuMinus } from "react-icons/lu";
import {
    getFolders,
    createFolder,
    addWorkflowToFolder,
    removeWorkflowFromFolder,
    getFolderWorkflows,
} from "@ZAPRedux/Slices/folderSlice/folderSlice";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { getWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import ZAPMenu from "@ZAPComponents/ZapMenu";


const FolderCell = ({ row, isFolder = false }) => {
    const dispatch = useDispatch();
    const { folders } = useSelector((state) => state.folder);
    const allFolders = folders?.data || [];

    const [modalOpen, setModalOpen] = useState(false);
    const [folderName, setFolderName] = useState("");
    const [creating, setCreating] = useState(false);
    const [assigning, setAssigning] = useState(false);

    const selectedFolder = allFolders.find((f) => f.id === row?.folder_id);

    useEffect(() => {
        if (!allFolders.length) {
            dispatch(getFolders());
        }
    }, []);
    const handleSelectFolder = async (folder) => {
        setAssigning(true);
        try {
            await dispatch(addWorkflowToFolder({ folder_id: folder.id, workflow_id: row.id }));

            if (isFolder) {
                await dispatch(getFolderWorkflows({
                    folder_id: row?.folder_id,
                }));
            } else {
                await dispatch(getWorkFlow());
            }
        } finally {
            setAssigning(false);
        }
    };

    const handleRemove = async () => {
        if (!selectedFolder) return;
        setAssigning(true);
        try {
            await dispatch(removeWorkflowFromFolder({ folder_id: selectedFolder.id, workflow_id: row.id }));
            if (isFolder) {
                await dispatch(getFolderWorkflows({
                    folder_id: row?.folder_id,
                }));
            } else {
                await dispatch(getWorkFlow());
            }

        } finally {
            setAssigning(false);
        }
    };

    const handleCreateFolder = async () => {
        const trimmed = folderName.trim();
        if (!trimmed) return;
        setCreating(true);
        try {
            const res = await dispatch(createFolder({ title: trimmed }));
            const newFolder = res?.payload?.data || res?.payload;
            if (newFolder?.id) {
                await dispatch(addWorkflowToFolder({ folder_id: newFolder.id, workflow_id: row.id }));
                dispatch(getFolders());
                dispatch(getWorkFlow());
            }
        } finally {
            setCreating(false);
            setFolderName("");
            setModalOpen(false);
        }
    };
    const menuItems = [
        ...allFolders.map((folder) => ({
            label: folder.title,
            icon: LuFolderOpen,
            onClick: () => handleSelectFolder(folder),
        })),
        ...(allFolders.length ? [{ type: "divider" }] : []),
        {
            label: __("Create New", "zaplane"),
            icon: LuFolderPlus,
            onClick: () => setModalOpen(true),
        },
    ];

    if (assigning) {
        return (
            <Box display="flex" justifyContent="center" alignItems="center" minH="32px">
                <Spinner size="sm" />
            </Box>
        );
    }

    return (
        <>
            {selectedFolder ? (
                <ZAPMenu
                    items={menuItems}
                    trigger={
                        <HStack
                            spacing={1}
                            px={'14px'}
                            py={1}
                            h='36px'
                             borderRadius="999px"
                            border="0.5px solid"
                            borderColor="gray.200"
                            bg="gray.50"
                            display="inline-flex"
                            alignItems="center"
                            maxW="160px"
                            cursor="pointer"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <Icon as={LuFolderOpen} boxSize="14px" color="gray.500" flexShrink={0} />
                            <Text className="zaplane-label" flex={1} isTruncated>
                                {selectedFolder.title}
                            </Text>
                            <Box
                                as="span"
                                display="flex"
                                alignItems="center"
                                justifyContent="center"
                                w="16px"
                                h="16px"
                                borderRadius="sm"
                                flexShrink={0}
                                _hover={{ bg: "gray.200" }}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleRemove();
                                }}
                                aria-label={__("Remove from folder", "zaplane")}
                            >
                                <Icon as={LuMinus} boxSize="11px" color="gray.500" />
                            </Box>
                        </HStack>
                    }
                />
            ) : (
                <ZAPMenu
                    items={menuItems}
                    trigger={
                        <Button
                            size="14px"
                            variant="outline"
                            fontSize="14px"
                            fontWeight="500"
                            py={1}
                            spacing={1}
                            h="36px"
                            borderRadius="999px"
                            px={'16px'}
                            onClick={(e) => e.stopPropagation()}
                            aria-label={__("Add to folder", "zaplane")}
                        >
                            <Icon as={LuFolderOpen} boxSize="14px" color="gray.500" mr={1} />
                            {__("Add", "zaplane")}
                        </Button>
                    }
                />
            )}

            <WPModal
                isOpen={modalOpen}
                title={__("Create Folder", "zaplane")}
                onRequestClose={() => { setModalOpen(false); setFolderName(""); }}
                shouldCloseOnClickOutside
                size="medium"
                suffix="create-folder"
            >
                <Text className="zaplane-label" mb={4}>
                    {__("Streamline your workflows by organizing them into folders.", "zaplane")}
                </Text>

                <Input
                    placeholder={__("Folder Name", "zaplane")}
                    value={folderName}
                    onChange={(e) => setFolderName(e.target.value)}
                    className="zaplane-input"
                    onKeyDown={(e) => {
                        if (e.key === "Enter") handleCreateFolder();
                        if (e.key === "Escape") { setModalOpen(false); setFolderName(""); }
                    }}
                    autoFocus
                    mb={5}
                />

                <HStack justifyContent="flex-end" spacing={3}>
                    <Button variant="outline" size="sm" onClick={() => { setModalOpen(false); setFolderName(""); }}>
                        {__("Cancel", "zaplane")}
                    </Button>
                    <Button
                        {...primaryBtn}
                        isLoading={creating}
                        isDisabled={!folderName.trim()}
                        onClick={handleCreateFolder}
                    >
                        {__("Create", "zaplane")}
                    </Button>
                </HStack>
            </WPModal>
        </>
    );
};

export default FolderCell;