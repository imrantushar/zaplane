import { useEffect, useState } from "react";
import { Box, Button, Flex, Input, Textarea, VStack } from "@chakra-ui/react";
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

const SaveAsRecipeModal = ({ isOpen, onClose, workflowId,}) => {
  const dispatch = useDispatch();
  const { folders, loadingFolders } = useSelector((state) => state.recipes);
  const [title, setTitle] = useState( "");
  const [folderId, setFolderId] = useState(null);

  // useEffect(() => {
  //   dispatch(getRecipeFolders());
  // }, []);

  const handleCreateFolder = async () => {
    if(!title.length)return
    const payload={
      title:title,
      // parent_id: ""

    }

    await dispatch(createRecipeFolder(payload));
    await dispatch(getRecipeFolders());
     onClose();
  };

  return (
    <WPModal
      title={__("Save as Recipe", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      size="large"
    >
      <VStack align="stretch" spacing={4}>
        <Input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder={__("Recipe title", "zaplane")}
        />
        <Flex justify="flex-end" gap={3}>
          <Button variant="outline" onClick={onClose}>
            {__("Cancel", "zaplane")}
          </Button>
          <Button
            {...primaryBtn}
            onClick={handleCreateFolder}
            isDisabled={!title.trim()}
          >
            {__("Create Recipe", "zaplane")}
          </Button>
        </Flex>
      </VStack>
    </WPModal>
  );
};

export default SaveAsRecipeModal;
