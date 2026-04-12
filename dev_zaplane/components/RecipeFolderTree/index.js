import { useEffect, useState } from "react";
import { Box, Button, HStack, Icon, Input, Text, VStack } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FiChevronDown, FiChevronRight, FiFolder } from "react-icons/fi";
import { collectExpandableIds } from "./helper";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
import FolderNode from "./FolderNode";



const RecipeFolderTree = ({ folders = [], selectedFolderId = null, onSelectFolder = () => {}, onCreateFolder = null, showRootOption = true, rootLabel = __("All recipes", "zaplane") }) => {
  const [newFolderName, setNewFolderName] = useState("");
  const [search, setSearch] = useState("");
  const [expandedMap, setExpandedMap] = useState({});

  useEffect(() => { setExpandedMap(collectExpandableIds(folders)); }, [folders]);

  return (
    <Box border="0.5px solid var(--zaplane-border-color)" borderRadius="8px" overflow="hidden" bg="white">
      {/* Header */}
      <HStack px={3} py={2} borderBottom="0.5px solid var(--zaplane-border-color)" spacing={2}>
        <Icon as={FiFolder} boxSize="15px" color="var(--zaplane-text-muted)" />
        <Text className="zaplane-label">{__('Recipe folders','zaplane')}</Text>
      </HStack>

      {/* Search */}
      <Box px={3} py={2} borderBottom="0.5px solid var(--zaplane-border-color)">
        <Input size="sm" placeholder="Search folders…" value={search}
          onChange={(e) => setSearch(e.target.value)} bg="var(--zaplane-bg-secondary)" />
      </Box>

      {/* Tree */}
      <VStack align="stretch" spacing={0} p={1.5} maxH="360px" overflowY="auto">
        {showRootOption && (
          <HStack px={2} py={2} borderRadius="6px" bg={selectedFolderId === null ? "gray.900" : "transparent"}
            cursor="pointer" onClick={() => onSelectFolder(null)} spacing={2}>
            <Icon as={FiFolder} boxSize="15px" color={selectedFolderId === null ? "white" : "gray.500"} />
            <Text fontSize="13px" className="zaplane-label" fontWeight="500" color={selectedFolderId === null ? "white" : "gray.700"}>{rootLabel}</Text>
          </HStack>
        )}
        {folders.map(folder => (
          <FolderNode key={folder.id} node={folder} depth={0} expandedMap={expandedMap}
            setExpandedMap={setExpandedMap} selectedId={selectedFolderId} onSelect={onSelectFolder} search={search} />
        ))}
      </VStack>

      {/* Create footer */}
      {typeof onCreateFolder === "function" && (
        <Box px={3} py={2} borderTop="0.5px solid var(--zaplane-border-color)">
          <Text fontSize="11px" color="var(--zaplane-text-muted)" mb={1.5}>New folder in selected location</Text>
          <HStack spacing={2}>
            <Input size="sm" value={newFolderName} placeholder="Folder name…"
              onChange={(e) => setNewFolderName(e.target.value)} />
            <Button size="sm" variant="outline" isDisabled={!newFolderName.trim()}
              onClick={() => { const title = newFolderName.trim(); if (!title) return;
                const payload = { title }; if (selectedFolderId !== null) payload.parent_id = selectedFolderId;
                onCreateFolder(payload); setNewFolderName(""); }}>
              + New
            </Button>
          </HStack>
        </Box>
      )}
    </Box>
  );
};

export default RecipeFolderTree;
