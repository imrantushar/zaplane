import { useState } from "react";
import { Box, Flex, Button } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch } from "react-redux";

import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPDivider from "@ZAPComponents/ZAPDivider";

import { createFolder, getFolders } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";

const CreateFolderModal = ({ isOpen, onClose }) => {
  const dispatch = useDispatch();
  const [folderName, setFolderName] = useState("");

  const handleCreate = async () => {
    if (!folderName.trim()) return;

    const res = await dispatch(
      createFolder({ title: folderName })
    );

    // ✅ success হলে list update
    if (res.meta.requestStatus === "fulfilled") {
      dispatch(getFolders()); // safe way
      setFolderName("");
      onClose();
    }
  };

  return (
    <WPModal
      title={__("Create Folder", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      size="medium"
    >
      <ZAPDivider mb="24px" />

      <Box>
        <ZAPInput
          label={__("Folder Name", "zaplane")}
          placeholder={__("Enter folder name", "zaplane")}
          value={folderName}
          onChange={(e) => setFolderName(e.target.value)}
        />

        <ZAPDivider mt="24px" />

        <Flex justify="flex-start" mt={5}>
          <Button variant="outline" mr={3} onClick={onClose}>
            {__("Cancel", "zaplane")}
          </Button>

          <Button
            {...primaryBtn}
            onClick={handleCreate}
            isDisabled={!folderName.trim()}
          >
            {__("Create", "zaplane")}
          </Button>
        </Flex>
      </Box>
    </WPModal>
  );
};

export default CreateFolderModal;