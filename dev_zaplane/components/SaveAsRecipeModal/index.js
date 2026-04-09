import { useEffect, useState } from "react";
import { Box, Button, Flex, Input, Textarea, VStack, Text } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import WPModal from "@ZAPComponents/Modal/WPModal";
import RecipeFolderTree from "@ZAPComponents/RecipeFolderTree";

import {
  createRecipeFolder,
  getRecipeFolders,
  workflowToRecipe,
} from "@ZAPRedux/Slices/recipeSlice/actions/recipe";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
import ZAPInput from "@ZAPComponents/ZAPInput";

const SaveAsRecipeModal = ({
  isOpen,
  onClose,
  workflowId,
  defaultTitle = "",
  initialFolderId = null,
}) => {
  const dispatch = useDispatch();
  const { folders, loadingFolders } = useSelector((state) => state.recipes);

  const [title, setTitle] = useState(defaultTitle);
  const [description, setDescription] = useState("");
  const [thumbnailId, setThumbnailId] = useState(null);
  const [thumbnailUrl, setThumbnailUrl] = useState(null); // for preview
  const [folderId, setFolderId] = useState(initialFolderId);
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setTitle(defaultTitle);
      setDescription("");
      setThumbnailId(null);
      setThumbnailUrl(null);
      setFolderId(initialFolderId);
      dispatch(getRecipeFolders());
    }
  }, [isOpen, defaultTitle, initialFolderId, dispatch]);

  const handleSave = async () => {
    if (!title.trim()) return;

    setCreating(true);
    try {
      await dispatch(
        workflowToRecipe({
          workflowId,
          folder_id: folderId,
          title,
          description: description.trim() || undefined,
          thumbnail_id: thumbnailId || undefined,
        })
      );

      await dispatch(getRecipeFolders());
      onClose();
    } catch (err) {
      console.error("Failed to create recipe:", err);
    }
    setCreating(false);
  };

  // Handler for WP media uploader selection
  const handleMediaSelect = (attachment) => {
    setThumbnailId(attachment.id);
    setThumbnailUrl(attachment.url);
  };

  const handleRemoveThumbnail = () => {
    setThumbnailId(null);
    setThumbnailUrl(null);
  };

  return (
    <WPModal
      title={__("Save as Recipe", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      size="large"
    >
      <VStack align="stretch" spacing={4}>
        <ZAPInput
          label={__("Title", "zaplane")}
          value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder={__("Recipe title", "zaplane")

            }
        />
        <ZAPInput
          label={__("Short description (optional)", "zaplane")}
           value={description}
           type="textarea"
            onChange={(e) => setDescription(e.target.value)}
            placeholder={__("Short description (optional)", "zaplane")}
        />


        {/* Thumbnail */}
        {/* <Box>
          <Text>{__("Thumbnail", "zaplane")}</Text>
          {thumbnailUrl ? (
            <Flex align="center" gap={3}>
              <Box
                as="img"
                src={thumbnailUrl}
                alt="Thumbnail preview"
                boxSize="72px"
                objectFit="cover"
                borderRadius="md"
                border="1px solid"
                borderColor="gray.200"
              />
              <Button size="sm" variant="ghost" colorScheme="red" onClick={handleRemoveThumbnail}>
                {__("Remove", "zaplane")}
              </Button>
            </Flex>
          ) : (
            <MediaUploader onSelect={handleMediaSelect}>
              {({ open }) => (
                <Button size="sm" variant="outline" onClick={open}>
                  {__("Select Image", "zaplane")}
                </Button>
              )}
            </MediaUploader>
          )}
        </Box> */}

        {/* Folder Tree */}
        <RecipeFolderTree
          folders={folders}
          selectedFolderId={folderId}
          onSelectFolder={(id) => setFolderId(id)}
          loading={loadingFolders}
        />

        {/* Actions */}
        <Flex justify="flex-end" gap={3}>
          <Button variant="outline" onClick={onClose}>
            {__("Cancel", "zaplane")}
          </Button>
          <Button
            {...primaryBtn}
            onClick={handleSave}
            isDisabled={!title.trim() || creating}
            isLoading={creating}
          >
            {__("Create Recipe", "zaplane")}
          </Button>
        </Flex>
      </VStack>
    </WPModal>
  );
};

export default SaveAsRecipeModal;