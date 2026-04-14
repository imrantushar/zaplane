import React, { useState } from 'react';
import {
  Box, Flex, HStack, Icon, IconButton,
  Text, Button, Input,
} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { route_path } from "@ZAPUtils/helper";
import { FiEye, FiFolder } from "react-icons/fi";
import { useNavigate } from "react-router-dom";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { updateFolder, deleteFolder } from '@ZAPRedux/Slices/folderSlice/folderSlice';


const FolderCard = ({ folder }) => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [title, setTitle] = useState(folder?.title || "");
  const [isRenameOpen, setIsRenameOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const workflowCount = folder?.workflow_count ?? 0;
  const workflowLabel = workflowCount === 1
    ? __("1 Workflow", "zaplane")
    : sprintf(__("%d Workflows", "zaplane"), workflowCount);

  const goToFolder = (id) =>
    navigate(`${route_path}admin.php?page=zaplane-folders&action=edit&id=${id}`);


  const handleRename = async () => {
    if (!title?.trim()) return;
    setIsUpdating(true);
    await dispatch(updateFolder({ id: folder?.id, title: title.trim() }));
    setIsUpdating(false);
    setIsRenameOpen(false);
  };

  const handleDelete = async () => {
    setIsDeleting(true);
    await dispatch(deleteFolder(folder?.id));
    setIsDeleting(false);
    setIsDeleteOpen(false);
  };

  return (
    <>
      <Box
        bg="var(--zaplane-background)"
        borderWidth="1px"
        borderColor="var(--zaplane-border-color)"
        borderRadius="12px"
        p={4}
        position="relative"
        minH="104px"
        transition="border-color 0.18s, box-shadow 0.18s"
        onClick={() => goToFolder(folder?.id)}
        _hover={{
          boxShadow: "var(--zaplane-shadow)",
        }}
      >

        <Flex justify="space-between" align="flex-start" gap={3} mb={3}>
          <HStack spacing={2} minW={0} align="flex-start">
            <Icon as={FiFolder} boxSize={5} flexShrink={0} mt={0.5} />
            <Text fontWeight="600" fontSize="15px" m={0} noOfLines={2} cursor="pointer">
              {folder?.title}
            </Text>
          </HStack>


          <ZAPMenu
            isIcon
            items={[
              { label: __("Rename", "zaplane"), onClick: () => setIsRenameOpen(true) },
              { label: __("Delete", "zaplane"), onClick: () => setIsDeleteOpen(true) },
            ]}
          />
        </Flex>


        <Flex justify="space-between" align="center">
          <Text fontSize="13px" color="gray.500" m={0}>
            {workflowLabel}
          </Text>
          <IconButton
            aria-label={sprintf(__("View %s", "zaplane"), folder?.title)}
            size="sm"
            variant="ghost"
            onClick={() => goToFolder(folder?.id)}
          >
            <Icon as={FiEye} boxSize={4} />
          </IconButton>
        </Flex>
      </Box>


      <WPModal
        title={__("Rename Folder", "zaplane")}
        isOpen={isRenameOpen}
        onRequestClose={() => setIsRenameOpen(false)}
        size="large"
      >
        <Input
         className='zaplane-input'
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder={__("Enter folder name", "zaplane")}
          mb={4}
        />
        <Flex justify="flex-end" gap={3}>
          <Button variant="outline" onClick={() => setIsRenameOpen(false)}>
            {__("Cancel", "zaplane")}
          </Button>
          <Button
            {...primaryBtn}
            onClick={handleRename}
            isLoading={isUpdating}
            isDisabled={!title.trim()}
          >
            {__("Update", "zaplane")}
          </Button>
        </Flex>
      </WPModal>


      <WPModal
        title={__("Delete Folder", "zaplane")}
        isOpen={isDeleteOpen}
        onRequestClose={() => setIsDeleteOpen(false)}
        size="large"
      >
        <Text mb={4}>
          {sprintf(
            __('Are you sure you want to delete "%s"? This action cannot be undone.', "zaplane"),
            folder?.title
          )}
        </Text>
        <Flex justify="flex-end" gap={3}>
          <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
            {__("Cancel", "zaplane")}
          </Button>
          <Button
            colorScheme="red"
            onClick={handleDelete}
            isLoading={isDeleting}
          >
            {__("Delete", "zaplane")}
          </Button>
        </Flex>
      </WPModal>
    </>
  );
};

export default FolderCard;