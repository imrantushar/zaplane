import { useEffect, useState } from "react";
import { Box, Button, HStack, Input, Text, VStack } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FiChevronDown, FiChevronRight, FiFolder } from "react-icons/fi";
import { collectExpandableIds } from "./helper";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
import FolderNode from "./FolderNode";



const RecipeFolderTree = ({
  folders = [],
  selectedFolderId = null,
  onSelectFolder = () => {},
  onCreateFolder = null,
  showRootOption = true,
  rootLabel = __("All recipes", "zaplane"),
}) => {
  const [newFolderName, setNewFolderName] = useState("");
  const [expandedMap, setExpandedMap] = useState({});

  useEffect(() => {
    setExpandedMap(collectExpandableIds(folders));
  }, [folders]);

  return (
    <VStack align="stretch" spacing={2}>
      {showRootOption ? (
        <Button
          variant={selectedFolderId === null ? "solid" : "ghost"}
          size="sm"
          justifyContent="flex-start"
          onClick={() => onSelectFolder(null)}
        >
          {rootLabel}
        </Button>
      ) : null}

      {folders.map((folder) => (
        <FolderNode
          key={folder.id}
          node={folder}
          depth={0}
          expandedMap={expandedMap}
          setExpandedMap={setExpandedMap}
          selectedId={selectedFolderId}
          onSelect={onSelectFolder}
        />
      ))}

      {typeof onCreateFolder === "function" ? (
        <Box pt={2}>
          <Text fontSize="12px" color="var(--zaplane-text-muted)" mb={1}>
            {__("Create folder in selected location", "zaplane")}
          </Text>
          <HStack>
            <Input
              size="sm"
              value={newFolderName}
              placeholder={__("New folder name", "zaplane")}
              onChange={(e) => setNewFolderName(e.target.value)}
            />
            <Button
              {...primaryBtn}
              onClick={() => {
                const title = newFolderName.trim();
                if (!title) {
                  return;
                }
                const payload = { title };
                if (selectedFolderId !== null) {
                  payload.parent_id = selectedFolderId;
                }
                onCreateFolder(payload);
                setNewFolderName("");
              }}
              isDisabled={!newFolderName.trim()}
            >
              {__("+ New", "zaplane")}
            </Button>
          </HStack>
        </Box>
      ) : null}
    </VStack>
  );
};

export default RecipeFolderTree;
