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

const FolderCard = ({ folder }) => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [title, setTitle] = useState(folder?.title || "");
  const [isOpen, setIsOpen] = useState(false);

  const children = folder?.children || [];
  const hasChildren = children.length > 0;

  const goToFolder = (id) =>
    navigate(`${route_path}admin.php?page=zaplane-recipes&action=edit&id=${id}`);


  const updateFolderName = async () => {
    if (!title?.trim()) return;
    await dispatch(updateRecipeFolder({ id: folder?.id, payload: { title } }));
    setIsOpen(false);
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
      >
        <Flex justify="space-between" align="flex-start" gap={3} mb={3}>
          <HStack spacing={2} minW={0} align="flex-start">
            <Icon as={FiFolder} boxSize={5} flexShrink={0} mt={0.5}  />
            <Text
              fontWeight="600"
              fontSize="15px"
              m={0}
              noOfLines={2}
              cursor="pointer"
            >
              {folder?.title}
            </Text>
          </HStack>

          <Flex align="center" gap={2}>

            <ZAPMenu
              isIcon
              items={[
                { label: "Edit", onClick: () => setIsOpen(true) },
              ]}
            />
          </Flex>
        </Flex>

        <Flex justify="space-between" align="flex-end">

            <Box
              fontSize="11px"
              borderRadius="6px"
              px={2}
              py="2px"
              fontWeight="700"
            >
              📂 {children.length ? children.length :0}
            </Box>
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
        isOpen={isOpen}
        onRequestClose={() => setIsOpen(false)}
        size="large"
      >
        <Input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder="Enter folder name"
          mb={4}
        />
        <Flex justify="flex-end" gap={3}>
          <Button variant="outline" onClick={() => setIsOpen(false)}>
            {__("Cancel", "zaplane")}
          </Button>
          <Button {...primaryBtn} onClick={updateFolderName} isDisabled={!title.trim()}>
            {__("Update", "zaplane")}
          </Button>
        </Flex>
      </WPModal>
    </>
  );
};

export default FolderCard;