import { useEffect, useState } from "react";
import { Box, Button, HStack, Input, Text, VStack } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FiChevronDown, FiChevronRight, FiFolder } from "react-icons/fi";

const collectExpandableIds = (nodes = [], ids = {}) => {
  nodes.forEach((node) => {
    if (Array.isArray(node.children) && node.children.length > 0) {
      ids[node.id] = true;
      collectExpandableIds(node.children, ids);
    }
  });
  return ids;
};

const FolderNode = ({ node, depth, expandedMap, setExpandedMap, selectedId, onSelect }) => {
  const hasChildren = Array.isArray(node.children) && node.children.length > 0;
  const isExpanded = !!expandedMap[node.id];

  return (
    <Box pl={`${depth * 14}px`}>
      <HStack spacing={1} py={1}>
        {hasChildren ? (
          <Button
            variant="ghost"
            minW="18px"
            h="18px"
            p="0"
            onClick={() => setExpandedMap((prev) => ({ ...prev, [node.id]: !isExpanded }))}
          >
            {isExpanded ? <FiChevronDown /> : <FiChevronRight />}
          </Button>
        ) : (
          <Box w="18px" />
        )}
        <Button
          variant={Number(selectedId) === Number(node.id) ? "solid" : "ghost"}
          size="sm"
          leftIcon={<FiFolder />}
          onClick={() => onSelect(node.id)}
          justifyContent="flex-start"
          w="100%"
        >
          {node.title}
        </Button>
      </HStack>
      {hasChildren && isExpanded
        ? node.children.map((child) => (
            <FolderNode
              key={child.id}
              node={child}
              depth={depth + 1}
              expandedMap={expandedMap}
              setExpandedMap={setExpandedMap}
              selectedId={selectedId}
              onSelect={onSelect}
            />
          ))
        : null}
    </Box>
  );
};

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
              size="sm"
              onClick={() => {
                const title = newFolderName.trim();
                if (!title) {
                  return;
                }
                onCreateFolder({ title, parent_id: selectedFolderId });
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
