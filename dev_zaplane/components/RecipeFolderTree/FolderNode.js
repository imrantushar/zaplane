
import { Box, Button, HStack, Icon, Text } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FiChevronDown, FiChevronRight, FiFolder } from "react-icons/fi";
const FolderNode = ({ node, depth, expandedMap, setExpandedMap, selectedId, onSelect, search }) => {
  const hasChildren = Array.isArray(node.children) && node.children.length > 0;
  const isExpanded = !!expandedMap[node.id] || !!search;
  const isSelected = Number(selectedId) === Number(node.id);

  if (search && !node.title.toLowerCase().includes(search.toLowerCase()) &&
      !node.children?.some(c => c.title.toLowerCase().includes(search.toLowerCase()))) return null;

  return (
    <Box>
      <HStack spacing={1} px={2} py="6px" borderRadius="6px" cursor="pointer"
        bg={isSelected ? "var(--zaplane-bg-secondary)" : "transparent"}
        _hover={{ bg: "var(--zaplane-bg-secondary)" }}
        pl={`${8 + depth * 16}px`} onClick={() => onSelect(node.id)}>
        {hasChildren ? (
          <Icon as={isExpanded ? FiChevronDown : FiChevronRight} boxSize="14px" color="gray.400"
            onClick={(e) => { e.stopPropagation(); setExpandedMap(p => ({ ...p, [node.id]: !isExpanded })); }} />
        ) : <Box w="14px" />}
        <Icon as={FiFolder} boxSize="15px" color={isSelected ? "gray.700" : "gray.400"} />
        <Text className="zaplane-label" fontSize="13px" color={isSelected ? "gray.900" : "gray.600"} fontWeight={isSelected ? "500" : "400"}>
          {node.title}
        </Text>
      </HStack>
      {hasChildren && isExpanded && node.children.map(child => (
        <FolderNode key={child.id} node={child} depth={depth + 1} expandedMap={expandedMap}
          setExpandedMap={setExpandedMap} selectedId={selectedId} onSelect={onSelect} search={search} />
      ))}
    </Box>
  );
};
export default FolderNode