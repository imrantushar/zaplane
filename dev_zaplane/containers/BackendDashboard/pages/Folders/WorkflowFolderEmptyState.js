import {
    Box,
    VStack,
    Text,
    Button,
    Icon,
} from "@chakra-ui/react";
import { FolderPlus } from "lucide-react";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { useState } from "react";
import { __ } from "@wordpress/i18n";
import CreateFolderModal from "@ZAPComponents/CreateFolderModal";


const WorkflowFolderEmptyState = ({ onCreateFolder }) => {
    const [isFolderModalOpen, setIsFolderModalOpen] = useState(false);
    return (
        <>
            <Box
                bg="white"
                border="1px solid"
                borderColor="var(--zaplane-border-color, #CBD1D7)"
                borderRadius="lg"
                px={{ base: 6, md: 12 }}
                py={10}
                w="full"
                textAlign="center"
            >
                <VStack gap='18px'>
                    <Icon
                        as={FolderPlus}
                        boxSize={8}
                        color="var(--zaplane-font-color)"
                        strokeWidth={1.5}
                    />

                    <Text
                        fontWeight="700"
                        fontSize="lg"
                        className="zaplane-label"
                        color="var(--zaplane-font-color)"
                    >
                        {__('Create Folders for your Workflows', 'zaplane')}
                    </Text>
                    <Text
                        fontSize="sm"
                        color="var(--zaplane-font-secondary-color)"
                        maxW="sm"
                        lineHeight="tall"
                        className="zaplane-label"

                    >
                        {__('Create folders to categories your workflows and also share with your workspace', 'zaplane')}
                    </Text>
                    <Button
                        onClick={() => setIsFolderModalOpen(true)}
                        {...primaryBtn}
                    >
                        {__("Create Folder", "zaplane")}
                    </Button>

                </VStack>
            </Box>
            <CreateFolderModal
                isOpen={isFolderModalOpen}
                onClose={() => setIsFolderModalOpen(false)}
            /></>

    );
};

export default WorkflowFolderEmptyState;