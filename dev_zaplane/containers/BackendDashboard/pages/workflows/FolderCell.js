import { useState, useEffect, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { Box, Text, Icon, HStack, Input, Button, Spinner } from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { LuFolderOpen, LuFolderPlus, LuMinus } from "react-icons/lu";
import {
    getFolders,
    createFolder,
    addWorkflowToFolder,
    removeWorkflowFromFolder,
} from "@ZAPRedux/Slices/folderSlice/folderSlice";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";

const FolderCell = ({ row }) => {
    const dispatch = useDispatch();
    const { folders } = useSelector((state) => state.folder);
    const allFolders = folders?.data || [];

    const [open, setOpen] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [folderName, setFolderName] = useState("");
    const [creating, setCreating] = useState(false);
    const [assigning, setAssigning] = useState(false);

    const [selectedFolder, setSelectedFolder] = useState(
        row?.folder_id ? { id: row.folder_id, title: row.folder_title } : null
    );

    const popoverRef = useRef(null);

    useEffect(() => {
        if (!allFolders.length) {
            dispatch(getFolders());
        }
    }, []);

    useEffect(() => {
        const handleOutside = (e) => {
            if (popoverRef.current && !popoverRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        if (open) document.addEventListener("mousedown", handleOutside);
        return () => document.removeEventListener("mousedown", handleOutside);
    }, [open]);

    const handleSelectFolder = async (folder) => {
        setOpen(false);
        setAssigning(true);
        try {
            await dispatch(addWorkflowToFolder({ folder_id: folder.id, workflow_id: row.id }));
            setSelectedFolder(folder);
        } finally {
            setAssigning(false);
        }
    };

    const handleRemove = async () => {
        if (!selectedFolder) return;
        setAssigning(true);
        try {
            await dispatch(removeWorkflowFromFolder({ folder_id: selectedFolder.id, workflow_id: row.id }));
            setSelectedFolder(null);
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
                setSelectedFolder({ id: newFolder.id, title: newFolder.title || trimmed });
                await dispatch(addWorkflowToFolder({ folder_id: newFolder.id, workflow_id: row.id }));
                dispatch(getFolders());
            }
        } finally {
            setCreating(false);
            setFolderName("");
            setModalOpen(false);
        }
    };

    if (assigning) {
        return (
            <Box display="flex" justifyContent="center" alignItems="center" minH="32px">
                <Spinner size="sm" />
            </Box>
        );
    }

    if (selectedFolder) {
        return (
            <HStack
                spacing={1}
                px={2}
                py={1}
                borderRadius="md"
                border="0.5px solid"
                borderColor="gray.200"
                bg="gray.50"
                display="inline-flex"
                alignItems="center"
                maxW="160px"
            >
                <Icon as={LuFolderOpen} boxSize="14px" color="gray.500" flexShrink={0} />
                <Text

                    className="zaplane-label"
                >
                    {selectedFolder.title}
                </Text>
                <Box
                    as="button"
                    onClick={handleRemove}
                    display="flex"
                    alignItems="center"
                    justifyContent="center"
                    w="16px"
                    h="16px"
                    borderRadius="sm"
                    flexShrink={0}
                    _hover={{ bg: "gray.200" }}
                    aria-label={__("Remove from folder", "zaplane")}
                >
                    <Icon as={LuMinus} boxSize="11px" color="gray.500" />
                </Box>
            </HStack>
        );
    }

    return (
        <>
            <Box position="relative" display="inline-block" ref={popoverRef}>
                <Button
                    size="sm"
                    variant="outline"
                    leftIcon={<Icon as={LuFolderOpen} boxSize="14px" />}
                    fontSize="13px"
                    fontWeight="400"
                    h="30px"
                    px={3}
                    onClick={() => setOpen((v) => !v)}
                    aria-label={__("Add to folder", "zaplane")}
                >
                    {__("Add", "zaplane")}
                </Button>

                {open && (
                    <Box
                        position="absolute"
                        top="calc(100% + 4px)"
                        left="0"
                        zIndex={9999}
                        bg="white"
                        border="0.5px solid"
                        borderColor="gray.200"
                        borderRadius="md"
                        boxShadow="md"
                        minW="160px"
                        maxW="220px"
                        py={1}
                        overflow="hidden"
                    >
                        {allFolders.length === 0 && (
                            <Text className="zaplane-label" px={3} py={2}>
                                {__("No folders yet", "zaplane")}
                            </Text>
                        )}

                        {allFolders.map((folder) => (
                            <Box
                                key={folder.id}
                                as="button"
                                w="100%"
                                textAlign="left"
                                px={3}
                                py="7px"
                                fontSize="13px"
                                color="gray.700"
                                _hover={{ bg: "gray.50" }}
                                onClick={() => handleSelectFolder(folder)}
                                display="block"
                            >
                                {folder.title}
                            </Box>
                        ))}

                        {allFolders.length > 0 && (
                            <Box borderTop="0.5px solid" borderColor="gray.100" my={1} />
                        )}

                        <Box
                            as="button"
                            w="100%"
                            textAlign="left"
                            px={3}
                            py="7px"
                            fontSize="13px"
                            color="gray.600"
                            _hover={{ bg: "gray.50" }}
                            display="flex"
                            alignItems="center"
                            gap="6px"
                            onClick={() => {
                                setOpen(false);
                                setModalOpen(true);
                            }}
                        >
                            <Icon as={LuFolderPlus} boxSize="14px" />
                            {__("Create New", "zaplane")}
                        </Box>
                    </Box>
                )}
            </Box>

            <WPModal
                isOpen={modalOpen}
                title={__("Create Folder", "zaplane")}
                onRequestClose={() => {
                    setModalOpen(false);
                    setFolderName("");
                }}
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
                    onKeyDown={(e) => {
                        if (e.key === "Enter") handleCreateFolder();
                        if (e.key === "Escape") {
                            setModalOpen(false);
                            setFolderName("");
                        }
                    }}
                    autoFocus
                    mb={5}
                />

                <HStack justifyContent="flex-end" spacing={3}>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setModalOpen(false);
                            setFolderName("");
                        }}
                    >
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